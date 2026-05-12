<?php

namespace App\Console\Commands;

use App\Http\Controllers\InventarioController;
use App\Models\inventario;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;

class TitanioImportarPedidos extends Command
{
    protected $signature = 'titanio:importar
                            {--fecha= : Fecha YYYY-MM-DD (default hoy)}
                            {--dry-run : No toca BD, solo muestra qué haría}
                            {--limit= : Limitar a N pedidos para pruebas}
                            {--uuid= : Importar solo el pedido con este UUID}
                            {--reversar : Revertir lo importado en la fecha (repone inventario + borra pedidos)}';

    protected $description = 'Importa pedidos desde Titanio POS (storeId=14 Guacara) a la BD local';

    private const TITANIO_URL = 'https://www.titanio-pos.com/api/orders/raw';
    private const TITANIO_SECRET = 'qwerty20-26$$';
    private const STORE_ID = 14;
    private const ID_CLIENTE = 1;

    /** @var array<int,int> numero_caja → usuarios.id */
    private array $cajaUserIdCache = [];

    public function handle(): int
    {
        $fecha = $this->option('fecha') ?: Carbon::today()->toDateString();
        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $uuidFiltro = $this->option('uuid');

        $sucursal = DB::table('sucursals')->first();
        if (! $sucursal || strtolower($sucursal->codigo) !== 'guacara') {
            $this->error('Esta instancia no es Guacara (codigo='.($sucursal->codigo ?? '?').'). Aborto.');
            return self::FAILURE;
        }

        if ($this->option('reversar')) {
            return $this->reversar($fecha, $dryRun);
        }

        $this->info("Consultando Titanio POS fecha=$fecha storeId=".self::STORE_ID.'...');
        try {
            $resp = Http::withHeaders([
                'x-secret' => self::TITANIO_SECRET,
                'Accept' => 'application/json',
            ])->timeout(120)->post(self::TITANIO_URL, [
                'fecha' => $fecha,
                'storeId' => self::STORE_ID,
            ]);
        } catch (\Throwable $e) {
            $this->error('Error HTTP: '.$e->getMessage());
            return self::FAILURE;
        }

        if (! $resp->successful()) {
            $this->error('HTTP '.$resp->status().': '.substr($resp->body(), 0, 500));
            return self::FAILURE;
        }

        $json = $resp->json();
        if (! is_array($json) || empty($json['success']) || ! isset($json['data'])) {
            $this->error('Respuesta inválida: '.substr($resp->body(), 0, 500));
            return self::FAILURE;
        }

        $orders = $json['data'];
        if ($uuidFiltro) {
            $orders = array_values(array_filter($orders, fn ($o) => ($o['uuid'] ?? '') === $uuidFiltro));
        }
        if ($limit !== null) {
            $orders = array_slice($orders, 0, $limit);
        }

        $this->info('Procesando '.count($orders).' pedidos.');

        $stats = ['importados' => 0, 'sobreescritos' => 0, 'saltados' => 0, 'errores' => 0];
        $detalle = [];

        foreach ($orders as $order) {
            $uuid = $order['uuid'] ?? null;
            $tid = $order['id'] ?? '?';
            if (! $uuid) {
                $stats['saltados']++;
                $detalle[] = "#$tid: sin uuid";
                continue;
            }
            $tipoOrden = $order['type'] ?? '';
            if ($tipoOrden !== 'sale' && $tipoOrden !== 'exchange') {
                $stats['saltados']++;
                $detalle[] = "#$tid (uuid=$uuid) type=$tipoOrden no soportado";
                continue;
            }

            try {
                $res = $this->importarPedido($order, $dryRun);
                if ($res === 'overwrite') {
                    $stats['sobreescritos']++;
                } else {
                    $stats['importados']++;
                }
            } catch (\Throwable $e) {
                $stats['errores']++;
                $detalle[] = "#$tid (uuid=$uuid): ".$e->getMessage();
                if ($this->getOutput()->isVerbose()) {
                    $this->error("Error en pedido $tid: ".$e->getMessage()."\n".$e->getTraceAsString());
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

    private function importarPedido(array $order, bool $dryRun): string
    {
        $uuid = $order['uuid'];
        $items = $order['items'] ?? [];
        $pagos = $order['pagos'] ?? [];
        $createdAt = $order['created_at'] ?? null;

        if (empty($items)) {
            throw new \RuntimeException('sin items');
        }

        $numeroCaja = $order['numero_caja'] ?? null;
        if ($numeroCaja === null) {
            throw new \RuntimeException('sin numero_caja');
        }
        $idVendedor = $this->resolverIdVendedor((int) $numeroCaja);

        $tasa = $this->inferirTasa($pagos);

        $itemsRows = [];
        foreach ($items as $it) {
            $idProd = (int) ($it['source_id'] ?? 0);
            if ($idProd <= 0) {
                throw new \RuntimeException('item sin source_id');
            }
            if (! DB::table('inventarios')->where('id', $idProd)->exists()) {
                throw new \RuntimeException("inventarios.id=$idProd no existe (producto: ".($it['product_name'] ?? '?').')');
            }

            $cantidad = (float) ($it['quantity'] ?? 0);
            if (($it['direction'] ?? 'out') === 'in') {
                $cantidad = -abs($cantidad);
            }
            $precio = (float) ($it['price'] ?? 0);
            $descuento = isset($it['discounts']) ? max(0, (float) $it['discounts']) : 0.0;
            $monto = round($cantidad * $precio - $descuento, 4);

            $itemsRows[] = [
                'id_producto' => $idProd,
                'cantidad' => $cantidad,
                'descuento' => $descuento,
                'monto' => $monto,
                'tasa' => $tasa,
                'precio_unitario' => $precio,
                'condicion' => (($it['condition'] ?? 'good') === 'good') ? 0 : 1,
                'entregado' => 0,
            ];
        }

        $pagosRows = [];
        $referenciasRows = [];
        foreach ($pagos as $p) {
            $payType = $p['payment_type_name'] ?? null;
            $amount = (float) ($p['amount'] ?? 0);
            $amountBs = (float) ($p['reference']['amount_in_ves'] ?? 0);
            $ref = $p['reference']['reference'] ?? null;
            $payDate = $p['reference']['pay_date'] ?? null;
            $pinpad = is_array($p['pinpad_response'] ?? null) ? $p['pinpad_response'] : null;

            switch ($payType) {
                case 'pinpad':
                case 'debito':
                    $pagosRows[] = [
                        'tipo' => '2',
                        'moneda' => 'bs',
                        'monto' => $amount,
                        'monto_original' => $amountBs ?: round($amount * $tasa, 4),
                        'referencia' => $ref ? substr(str_pad($ref, 4, '0', STR_PAD_LEFT), -4) : null,
                        'pos_message' => $pinpad['message'] ?? null,
                        'pos_lote' => isset($pinpad['lote']) ? (int) $pinpad['lote'] : null,
                        'pos_responsecode' => $pinpad['responsecode'] ?? null,
                        'pos_amount' => isset($pinpad['amount']) ? (float) $pinpad['amount'] : null,
                        'pos_terminal' => $pinpad['terminal'] ?? null,
                        'pos_json_response' => $pinpad ? json_encode($pinpad) : null,
                    ];
                    break;

                case 'efectivo_usd':
                    $pagosRows[] = [
                        'tipo' => '3',
                        'moneda' => 'dolar',
                        'monto' => $amount,
                        'monto_original' => $amount,
                    ];
                    break;

                case 'efectivo_ves':
                    $pagosRows[] = [
                        'tipo' => '3',
                        'moneda' => 'bs',
                        'monto' => $amount,
                        'monto_original' => $amountBs ?: round($amount * $tasa, 4),
                    ];
                    break;

                case 'transferencia':
                    $pagosRows[] = [
                        'tipo' => '1',
                        'moneda' => null,
                        'monto' => $amount,
                        'monto_original' => null,
                    ];
                    $refData = is_array($p['reference'] ?? null) ? $p['reference'] : [];
                    $cliRef = is_array($refData['client'] ?? null) ? $refData['client'] : [];
                    $referenciasRows[] = [
                        'tipo' => '1',
                        'categoria' => 'central',
                        'estatus' => 'aprobada',
                        'monto' => $amountBs ?: round($amount * $tasa, 4),
                        'descripcion' => $refData['payment_reference'] ?? null,
                        'banco' => $refData['bank_code'] ?? null,
                        'banco_origen' => $cliRef['bank'] ?? null,
                        'cedula' => $cliRef['ci'] ?? null,
                        'telefono' => $cliRef['phone'] ?? null,
                        'fecha_pago' => $payDate,
                    ];
                    break;

                default:
                    throw new \RuntimeException("tipo de pago desconocido: ".($payType ?? 'null'));
            }
        }

        if ($dryRun) {
            $totalUsd = array_sum(array_column($itemsRows, 'monto'));
            $totalPagos = array_sum(array_column($pagosRows, 'monto'));
            $this->line(sprintf(
                '[DRY] uuid=%s items=%d pagos=%d total_usd=%.4f pagado=%.4f tasa=%.4f',
                $uuid, count($itemsRows), count($pagosRows), $totalUsd, $totalPagos, $tasa
            ));
            $existed = DB::table('pedidos')->where('uuid', $uuid)->exists();
            return $existed ? 'overwrite' : 'importado';
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
            $invCtrl = new InventarioController;

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

                $idProd = (int) $row['id_producto'];
                $cantidad = (float) $row['cantidad'];
                $inv = inventario::find($idProd);
                $ct1 = (float) $inv->cantidad;
                $ctFinal = round($ct1 - $cantidad, 4);
                $invCtrl->descontarInventario($idProd, $ctFinal, $ct1, $idPedido, 'IMPORT.TITANIO');
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

    private function reversar(string $fecha, bool $dryRun): int
    {
        $f1 = "$fecha 00:00:00";
        $f2 = "$fecha 23:59:59";

        $pedidos = DB::table('pedidos')
            ->whereBetween('fecha_factura', [$f1, $f2])
            ->whereNotNull('uuid')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('movimientos_inventariounitarios as m')
                  ->whereColumn('m.id_pedido', 'pedidos.id')
                  ->where('m.origen', 'LIKE', 'IMPORT.TITANIO%');
            })
            ->get(['id', 'uuid', 'id_vendedor']);

        $this->info('Pedidos a reversar (fecha='.$fecha.'): '.$pedidos->count());
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

    private function inferirTasa(array $pagos): float
    {
        foreach ($pagos as $p) {
            $usd = (float) ($p['amount'] ?? 0);
            $bs = (float) ($p['reference']['amount_in_ves'] ?? 0);
            if ($usd > 0 && $bs > 0) {
                return round($bs / $usd, 4);
            }
            if (($p['payment_type_name'] ?? '') === 'pinpad' && isset($p['pinpad_response']['amount'])) {
                $posBs = ((float) $p['pinpad_response']['amount']) / 100.0;
                if ($usd > 0 && $posBs > 0) {
                    return round($posBs / $usd, 4);
                }
            }
        }
        $tasa = (float) DB::table('monedas')->where('tipo', 1)->orderBy('id', 'desc')->value('valor');
        return $tasa > 0 ? $tasa : 1.0;
    }
}
