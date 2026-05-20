<?php

namespace App\Console\Commands;

use App\Http\Controllers\InventarioController;
use App\Http\Controllers\MovimientosInventariounitarioController;
use App\Models\inventario;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;

class TitanioImportarJson extends Command
{
    protected $signature = 'titanio:importar-json
                            {archivo : Ruta al archivo JSON exportado del navegador}
                            {--dry-run : No toca BD, solo muestra qué haría}
                            {--limit= : Limitar a N pedidos para pruebas}
                            {--uuid= : Importar solo el pedido con este UUID (id)}
                            {--caja= : Forzar numero de caja (default: daily_cash_count_id)}
                            {--sobrescribir : Si el pedido ya existe lo reimporta (por defecto se salta)}
                            {--reversar : Revertir lo importado (todos los uuids del JSON: repone inventario y borra pedido+items+pagos+refs)}';

    protected $description = 'Importa pedidos desde un backup JSON local de Titanio POS (Guacara) cuando la API no está disponible';

    private const ID_CLIENTE = 1;
    private const BANCO_DEFECTO = '0102'; // Banco de Venezuela

    /** @var array<int,int> numero_caja → usuarios.id */
    private array $cajaUserIdCache = [];

    /** @var array<string,int> codigo_barras → inventarios.id */
    private array $inventarioIdPorCodigoCache = [];

    public function handle(): int
    {
        $archivo = $this->argument('archivo');
        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $uuidFiltro = $this->option('uuid');
        $cajaForzar = $this->option('caja') !== null ? (int) $this->option('caja') : null;
        $sobrescribir = (bool) $this->option('sobrescribir');

        $sucursal = DB::table('sucursals')->first();
        if (! $sucursal || strtolower($sucursal->codigo) !== 'guacara') {
            $this->error('Esta instancia no es Guacara (codigo='.($sucursal->codigo ?? '?').'). Aborto.');
            return self::FAILURE;
        }

        if (! is_file($archivo)) {
            $this->error("No existe el archivo: $archivo");
            return self::FAILURE;
        }

        $raw = file_get_contents($archivo);
        $json = json_decode($raw, true);
        if (! is_array($json) || ! isset($json['data']['orders']) || ! is_array($json['data']['orders'])) {
            $this->error('JSON inválido: falta data.orders');
            return self::FAILURE;
        }

        $todas = $json['data']['orders'];
        $fechaJson = $json['data']['date'] ?? null;
        $this->info('Fecha del backup: '.($fechaJson ?: '?').' — orders en archivo: '.count($todas));

        if ($this->option('reversar')) {
            return $this->reversar($todas, $dryRun);
        }

        $orders = array_values(array_filter($todas, function ($o) {
            return ($o['status'] ?? '') === 'completed'
                && in_array($o['order_type'] ?? '', ['sale', 'exchange'], true);
        }));

        if ($uuidFiltro) {
            $orders = array_values(array_filter($orders, fn ($o) => ($o['id'] ?? '') === $uuidFiltro));
        }
        if ($limit !== null) {
            $orders = array_slice($orders, 0, $limit);
        }

        $this->info('Procesando '.count($orders).' pedidos (completed sale|exchange).');

        $stats = ['importados' => 0, 'sobreescritos' => 0, 'ya_existian' => 0, 'saltados' => 0, 'errores' => 0];
        $detalle = [];

        foreach ($orders as $order) {
            $uuid = $order['id'] ?? null;
            $short = $order['short_id'] ?? '?';
            if (! $uuid) {
                $stats['saltados']++;
                $detalle[] = "#$short: sin uuid";
                continue;
            }

            try {
                $res = $this->importarPedido($order, $cajaForzar, $sobrescribir, $dryRun);
                if ($res === 'overwrite') {
                    $stats['sobreescritos']++;
                } elseif ($res === 'ya_existia') {
                    $stats['ya_existian']++;
                } else {
                    $stats['importados']++;
                }
            } catch (\Throwable $e) {
                $stats['errores']++;
                $detalle[] = "#$short (uuid=$uuid): ".$e->getMessage();
                if ($this->getOutput()->isVerbose()) {
                    $this->error("Error en pedido $short: ".$e->getMessage()."\n".$e->getTraceAsString());
                }
            }
        }

        $this->newLine();
        $this->info('==== Resumen '.($dryRun ? '(DRY-RUN)' : '').' ====');
        foreach ($stats as $k => $v) {
            $this->line(str_pad($k, 16).": $v");
        }
        if (! empty($detalle)) {
            $this->newLine();
            $this->warn('Detalle ('.count($detalle).' entradas):');
            foreach ($detalle as $e) {
                $this->line(" - $e");
            }
        }

        return self::SUCCESS;
    }

    private function importarPedido(array $order, ?int $cajaForzar, bool $sobrescribir, bool $dryRun): string
    {
        $uuid = $order['id'];

        if (! $sobrescribir && DB::table('pedidos')->where('uuid', $uuid)->exists()) {
            return 'ya_existia';
        }

        $items = $order['items'] ?? [];
        $pagos = $order['payments'] ?? [];
        $createdAt = $this->parseFecha($order['created_at'] ?? null);
        $tasa = (float) ($order['exchange_rate'] ?? 0);

        if (empty($items)) {
            throw new \RuntimeException('sin items');
        }

        $numeroCaja = $cajaForzar ?? ($order['daily_cash_count_id'] ?? null);
        if ($numeroCaja === null) {
            throw new \RuntimeException('sin numero_caja (ni --caja ni daily_cash_count_id)');
        }
        $idVendedor = $this->resolverIdVendedor((int) $numeroCaja);

        if ($tasa <= 0) {
            $tasa = $this->inferirTasaFallback();
        }

        $itemsRows = [];
        foreach ($items as $it) {
            $code = $it['code'] ?? null;
            $idProd = $code ? $this->resolverIdInventario((string) $code) : 0;
            $idProdFinal = $idProd > 0 ? $idProd : null;

            $cantidadAbs = (float) ($it['quantity'] ?? 0);
            $direction = $it['direction'] ?? 'out';
            $cantidad = $direction === 'in' ? -abs($cantidadAbs) : $cantidadAbs;

            $precio = (float) ($it['price_ref'] ?? 0);
            $subtotalRef = (float) ($it['subtotal_ref'] ?? 0);
            $descuento = max(0, round(abs($cantidad) * $precio - $subtotalRef, 4));
            $signo = $cantidad < 0 ? -1 : 1;
            $monto = round($signo * $subtotalRef, 4);

            $itemsRows[] = [
                'id_producto' => $idProdFinal,
                'cantidad' => $cantidad,
                'descuento' => $descuento,
                'monto' => $monto,
                'tasa' => $tasa,
                'precio_unitario' => $precio,
                'condicion' => 0,
                'entregado' => 0,
            ];
        }

        $pagosRows = [];
        $referenciasRows = [];
        foreach ($pagos as $p) {
            $method = (string) ($p['method'] ?? '');
            $amountRef = (float) ($p['amount_ref'] ?? 0);
            $amountBs = (float) ($p['amount_bs'] ?? 0);

            switch ($method) {
                case '6': // pinpad
                    if (($p['pinpad_status'] ?? '') !== 'approved') {
                        break;
                    }
                    $pinpad = is_array($p['pinpad_data'] ?? null) ? $p['pinpad_data'] : null;
                    $ref = $pinpad['reference'] ?? ($p['reference'] ?? null);
                    $pagosRows[] = [
                        'tipo' => '2',
                        'moneda' => 'bs',
                        'monto' => $amountRef,
                        'monto_original' => $amountBs ?: round($amountRef * $tasa, 4),
                        'referencia' => $ref ? substr(str_pad((string) $ref, 4, '0', STR_PAD_LEFT), -4) : null,
                        'pos_message' => $pinpad['message'] ?? null,
                        'pos_lote' => isset($pinpad['lote']) ? (int) $pinpad['lote'] : null,
                        'pos_responsecode' => $pinpad['responsecode'] ?? null,
                        'pos_amount' => isset($pinpad['amount']) ? (float) $pinpad['amount'] : null,
                        'pos_terminal' => $pinpad['terminal'] ?? null,
                        'pos_json_response' => $pinpad ? json_encode($pinpad) : null,
                    ];
                    break;

                case '2': // efectivo USD
                    $pagosRows[] = [
                        'tipo' => '3',
                        'moneda' => 'dolar',
                        'monto' => $amountRef,
                        'monto_original' => $amountRef,
                    ];
                    break;

                case '5': // efectivo Bs
                    $pagosRows[] = [
                        'tipo' => '3',
                        'moneda' => 'bs',
                        'monto' => $amountRef,
                        'monto_original' => $amountBs ?: round($amountRef * $tasa, 4),
                    ];
                    break;

                case '3': // transferencia / pago móvil
                    $pagosRows[] = [
                        'tipo' => '1',
                        'moneda' => null,
                        'monto' => $amountRef,
                        'monto_original' => null,
                    ];
                    $referenciasRows[] = [
                        'tipo' => '1',
                        'categoria' => 'central',
                        'estatus' => 'aprobada',
                        'monto' => $amountBs ?: round($amountRef * $tasa, 4),
                        'descripcion' => $p['reference'] ?? null,
                        'banco' => self::BANCO_DEFECTO,
                        'banco_origen' => self::BANCO_DEFECTO,
                        'cedula' => null,
                        'telefono' => $p['phone'] ?? null,
                        'fecha_pago' => $p['pay_date'] ?? null,
                    ];
                    break;

                default:
                    throw new \RuntimeException("method de pago desconocido: $method");
            }
        }

        if ($dryRun) {
            $totalUsd = array_sum(array_column($itemsRows, 'monto'));
            $totalPagos = array_sum(array_column($pagosRows, 'monto'));
            $this->line(sprintf(
                '[DRY] uuid=%s items=%d pagos=%d total_usd=%.4f pagado=%.4f tasa=%.4f',
                $uuid, count($itemsRows), count($pagosRows), $totalUsd, $totalPagos, $tasa
            ));
            return DB::table('pedidos')->where('uuid', $uuid)->exists() ? 'overwrite' : 'importado';
        }

        return DB::transaction(function () use ($uuid, $itemsRows, $pagosRows, $referenciasRows, $createdAt, $idVendedor) {
            $existed = DB::table('pedidos')->where('uuid', $uuid)->first();
            if ($existed) {
                $this->reponerInventarioPedido($existed->id, $idVendedor);
                DB::table('pedidos')->where('id', $existed->id)->delete();
            }

            $now = now();
            $fechaPedido = $createdAt ?: $now;

            $idPedido = DB::table('pedidos')->insertGetId([
                'uuid' => $uuid,
                'estado' => 1,
                'push' => 0,
                'sincronizado' => 0,
                'is_printing' => 0,
                'export' => 0,
                'retencion' => 0,
                'ticked' => 0,
                'fiscal' => 0,
                'id_cliente' => self::ID_CLIENTE,
                'id_vendedor' => $idVendedor,
                'fecha_factura' => $fechaPedido,
                'created_at' => $fechaPedido,
                'updated_at' => $now,
            ]);

            Session::put('id_usuario', $idVendedor);
            $movCtrl = new MovimientosInventariounitarioController;

            $hasItemsMontoBs = Schema::hasColumn('items_pedidos', 'monto_bs');
            foreach ($itemsRows as $row) {
                $row['id_pedido'] = $idPedido;
                $row['push'] = 0;
                $row['sincronizado'] = 0;
                $row['created_at'] = $fechaPedido;
                $row['updated_at'] = $now;
                if ($hasItemsMontoBs) {
                    $row['monto_bs'] = round($row['monto'] * ($row['tasa'] ?: 1), 4);
                }
                DB::table('items_pedidos')->insert($row);

                $idProd = $row['id_producto'];
                if ($idProd === null) {
                    continue;
                }
                $cantidad = (float) $row['cantidad'];
                $inv = inventario::find($idProd);
                if (! $inv) {
                    continue;
                }
                $ct1 = (float) $inv->cantidad;
                $ctFinal = round($ct1 - $cantidad, 4);
                $inv->cantidad = $ctFinal;
                $inv->save();
                $movCtrl->setNewCtMov([
                    'id_producto' => $idProd,
                    'cantidadafter' => $ctFinal,
                    'ct1' => $ct1,
                    'id_pedido' => $idPedido,
                    'origen' => 'IMPORT.TITANIO.JSON #'.$idPedido,
                ]);
            }

            $hasPagosMontoBs = Schema::hasColumn('pago_pedidos', 'monto_bs');
            foreach ($pagosRows as $row) {
                $row['id_pedido'] = $idPedido;
                $row['cuenta'] = 1;
                $row['push'] = 0;
                $row['sincronizado'] = 0;
                $row['created_at'] = $fechaPedido;
                $row['updated_at'] = $now;
                if ($hasPagosMontoBs) {
                    $row['monto_bs'] = $row['monto_original'] ?? null;
                }
                DB::table('pago_pedidos')->insert($row);
            }

            foreach ($referenciasRows as $row) {
                $row['id_pedido'] = $idPedido;
                $row['uuid_pedido'] = $uuid;
                $row['push'] = 0;
                $row['sincronizado'] = 0;
                $row['created_at'] = $fechaPedido;
                $row['updated_at'] = $now;
                DB::table('pagos_referencias')->insert($row);
            }

            return $existed ? 'overwrite' : 'importado';
        }, 5);
    }

    private function reversar(array $orders, bool $dryRun): int
    {
        $uuids = array_values(array_filter(array_map(fn ($o) => $o['id'] ?? null, $orders)));

        $pedidos = DB::table('pedidos')
            ->whereIn('uuid', $uuids)
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('movimientos_inventariounitarios as m')
                  ->whereColumn('m.id_pedido', 'pedidos.id')
                  ->where('m.origen', 'LIKE', 'IMPORT.TITANIO%');
            })
            ->get(['id', 'uuid', 'id_vendedor']);

        $this->info('Pedidos a reversar: '.$pedidos->count());
        if ($pedidos->isEmpty()) {
            return self::SUCCESS;
        }

        if ($dryRun) {
            foreach ($pedidos as $p) {
                $items = DB::table('items_pedidos')->where('id_pedido', $p->id)->whereNotNull('id_producto')->count();
                $this->line("[DRY] pedido_id=$p->id uuid=$p->uuid items=$items");
            }
            $this->info('==== DRY-RUN reversar ====');
            return self::SUCCESS;
        }

        $stats = ['reversados' => 0, 'errores' => 0];
        foreach ($pedidos as $p) {
            try {
                DB::transaction(function () use ($p) {
                    $this->reponerInventarioPedido((int) $p->id, (int) $p->id_vendedor);
                    DB::table('pedidos')->where('id', $p->id)->delete();
                }, 5);
                $stats['reversados']++;
            } catch (\Throwable $e) {
                $stats['errores']++;
                $this->error("Error reversando pedido $p->id (uuid=$p->uuid): ".$e->getMessage());
            }
        }
        $this->newLine();
        $this->info('==== Resumen reversar ====');
        foreach ($stats as $k => $v) {
            $this->line(str_pad($k, 16).": $v");
        }
        return self::SUCCESS;
    }

    private function reponerInventarioPedido(int $idPedido, int $idVendedor): void
    {
        $items = DB::table('items_pedidos')->where('id_pedido', $idPedido)->whereNotNull('id_producto')->get(['id_producto', 'cantidad']);
        if ($items->isEmpty()) {
            return;
        }
        Session::put('id_usuario', $idVendedor);
        $invCtrl = new InventarioController;
        foreach ($items as $it) {
            $inv = inventario::find($it->id_producto);
            if (! $inv) {
                continue;
            }
            $ct1 = (float) $inv->cantidad;
            $ctFinal = round($ct1 + (float) $it->cantidad, 4);
            $invCtrl->descontarInventario((int) $it->id_producto, $ctFinal, $ct1, $idPedido, 'IMPORT.TITANIO.REPONER');
        }
    }

    private function resolverIdVendedor(int $numeroCaja): int
    {
        if (isset($this->cajaUserIdCache[$numeroCaja])) {
            return $this->cajaUserIdCache[$numeroCaja];
        }
        $usuario = 'caja'.$numeroCaja;
        $id = (int) DB::table('usuarios')->where('usuario', $usuario)->value('id');
        if ($id <= 0) {
            throw new \RuntimeException("Usuario '$usuario' no existe en BD local");
        }
        return $this->cajaUserIdCache[$numeroCaja] = $id;
    }

    private function parseFecha(?string $iso): ?string
    {
        if (! $iso) {
            return null;
        }
        try {
            return Carbon::parse($iso)->timezone(config('app.timezone'))->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function resolverIdInventario(string $codigoBarras): int
    {
        if (isset($this->inventarioIdPorCodigoCache[$codigoBarras])) {
            return $this->inventarioIdPorCodigoCache[$codigoBarras];
        }
        $id = (int) DB::table('inventarios')->where('codigo_barras', $codigoBarras)->value('id');
        return $this->inventarioIdPorCodigoCache[$codigoBarras] = $id;
    }

    private function inferirTasaFallback(): float
    {
        $tasa = (float) DB::table('monedas')->where('tipo', 1)->orderBy('id', 'desc')->value('valor');
        return $tasa > 0 ? $tasa : 1.0;
    }
}
