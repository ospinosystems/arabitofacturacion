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

/**
 * Importa pedidos desde la API de Titanio POS a la BD local de una sucursal.
 *
 * Parámetros de la sucursal (por opción o por .env):
 *   --store-id  / TITANIO_STORE_ID  : storeId de la tienda en Titanio (Guacara = 14).
 *   --sucursal  / TITANIO_SUCURSAL  : código esperado en sucursals.codigo; aborta si la BD es de otra sucursal.
 *   TITANIO_SECRET                  : secreto de la API (x-secret).
 *
 * Rango: --fecha (un día) o --desde/--hasta (varios días, inclusive). Idempotente por uuid: si el pedido ya
 * existe se repone inventario, se borra y se reinserta.
 */
class TitanioImportarPedidos extends Command
{
    protected $signature = 'titanio:importar
                            {--fecha= : Fecha YYYY-MM-DD (default hoy). Ignorada si se usa --desde/--hasta}
                            {--desde= : Primer día del rango (YYYY-MM-DD, inclusive)}
                            {--hasta= : Último día del rango (YYYY-MM-DD, inclusive; default hoy)}
                            {--store-id= : storeId en Titanio POS (default env TITANIO_STORE_ID; Guacara=14)}
                            {--sucursal= : Código esperado de la sucursal en la BD local (default env TITANIO_SUCURSAL)}
                            {--dry-run : No toca BD, solo muestra qué haría}
                            {--limit= : Limitar a N pedidos por día para pruebas}
                            {--uuid= : Importar solo el pedido con este UUID}
                            {--detener-si-falla : Abortar el rango al primer día con error HTTP (default: seguir y reportar)}
                            {--reversar : Revertir lo importado en la(s) fecha(s) (repone inventario + borra pedidos)}';

    protected $description = 'Importa pedidos desde Titanio POS (API) a la BD local de la sucursal';

    private const TITANIO_URL = 'https://www.titanio-pos.com/api/orders/raw';
    private const TITANIO_SECRET_DEFAULT = 'qwerty20-26$$';
    private const STORE_ID_GUACARA = 14;
    private const ID_CLIENTE = 1;

    /** @var array<int,int> numero_caja → usuarios.id */
    private array $cajaUserIdCache = [];

    /** @var array<int,bool> inventarios.id → existe */
    private array $productoExisteCache = [];

    private int $storeId = 0;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $uuidFiltro = $this->option('uuid');

        // ── Sucursal / store ──────────────────────────────────────────────────────────────
        $sucursal = DB::table('sucursals')->first();
        $codigoBd = strtolower((string) ($sucursal->codigo ?? ''));
        $sucursalEsperada = strtolower(trim((string) ($this->option('sucursal') ?: env('TITANIO_SUCURSAL', ''))));

        if ($sucursalEsperada !== '' && $codigoBd !== $sucursalEsperada) {
            $this->error("Esta BD es de la sucursal '{$codigoBd}', se esperaba '{$sucursalEsperada}'. Aborto.");
            return self::FAILURE;
        }

        $storeIdOpt = $this->option('store-id') ?: env('TITANIO_STORE_ID', '');
        if ($storeIdOpt === '' || $storeIdOpt === null) {
            if ($codigoBd === 'guacara') {
                $storeIdOpt = self::STORE_ID_GUACARA;
            } else {
                $this->error("Indique --store-id=<storeId de '{$codigoBd}' en Titanio POS> (o TITANIO_STORE_ID en .env).");
                return self::FAILURE;
            }
        }
        $this->storeId = (int) $storeIdOpt;
        if ($this->storeId <= 0) {
            $this->error('store-id inválido: ' . var_export($storeIdOpt, true));
            return self::FAILURE;
        }
        if ($this->storeId === self::STORE_ID_GUACARA && $codigoBd !== 'guacara') {
            $this->error("storeId 14 es Guacara pero esta BD es '{$codigoBd}'. Indique el --store-id correcto.");
            return self::FAILURE;
        }
        $this->info("Sucursal BD: {$codigoBd} | storeId Titanio: {$this->storeId}");

        // ── Fechas ────────────────────────────────────────────────────────────────────────
        $fechas = $this->resolverFechas();
        if ($fechas === null) {
            return self::FAILURE;
        }

        if ($this->option('reversar')) {
            $rc = self::SUCCESS;
            foreach ($fechas as $f) {
                if ($this->reversar($f, $dryRun) !== self::SUCCESS) {
                    $rc = self::FAILURE;
                }
            }
            return $rc;
        }

        $this->info('Días a importar: ' . count($fechas) . ' (' . $fechas[0] . ' → ' . $fechas[count($fechas) - 1] . ')');

        $totales = ['importados' => 0, 'sobreescritos' => 0, 'saltados' => 0, 'errores' => 0, 'total_usd' => 0.0];
        $fechasFallidas = [];
        $detalleGlobal = [];
        $inicio = microtime(true);

        foreach ($fechas as $i => $fecha) {
            $this->line(sprintf('<comment>[%d/%d] %s</comment> consultando Titanio POS…', $i + 1, count($fechas), $fecha));
            $orders = $this->consultarDia($fecha, $error);
            if ($orders === null) {
                $fechasFallidas[] = $fecha . ': ' . $error;
                $this->error("  ✗ {$fecha}: {$error}");
                if ($this->option('detener-si-falla')) {
                    break;
                }
                continue;
            }

            if ($uuidFiltro) {
                $orders = array_values(array_filter($orders, fn ($o) => ($o['uuid'] ?? '') === $uuidFiltro));
            }
            if ($limit !== null) {
                $orders = array_slice($orders, 0, $limit);
            }

            $stats = ['importados' => 0, 'sobreescritos' => 0, 'saltados' => 0, 'errores' => 0, 'total_usd' => 0.0];
            $detalle = [];
            foreach ($orders as $order) {
                $uuid = $order['uuid'] ?? null;
                $tid = $order['id'] ?? '?';
                if (! $uuid) {
                    $stats['saltados']++;
                    $detalle[] = "$fecha #$tid: sin uuid";
                    continue;
                }
                $tipoOrden = $order['type'] ?? '';
                if ($tipoOrden !== 'sale' && $tipoOrden !== 'exchange') {
                    $stats['saltados']++;
                    $detalle[] = "$fecha #$tid (uuid=$uuid) type=$tipoOrden no soportado";
                    continue;
                }

                try {
                    $res = $this->importarPedido($order, $dryRun);
                    if ($res['resultado'] === 'overwrite') {
                        $stats['sobreescritos']++;
                    } else {
                        $stats['importados']++;
                    }
                    $stats['total_usd'] += $res['total_usd'];
                } catch (\Throwable $e) {
                    $stats['errores']++;
                    $detalle[] = "$fecha #$tid (uuid=$uuid): ".$e->getMessage();
                    if ($this->getOutput()->isVerbose()) {
                        $this->error("Error en pedido $tid: ".$e->getMessage()."\n".$e->getTraceAsString());
                    }
                }
            }

            $this->line(sprintf(
                '  → %d pedidos en API | importados %d | sobreescritos %d | saltados %d | errores %d | total %.2f USD',
                count($orders), $stats['importados'], $stats['sobreescritos'], $stats['saltados'], $stats['errores'], $stats['total_usd']
            ));
            foreach ($stats as $k => $v) {
                $totales[$k] += $v;
            }
            $detalleGlobal = array_merge($detalleGlobal, $detalle);
        }

        $this->newLine();
        $this->info('==== Resumen '.($dryRun ? '(DRY-RUN) ' : '').'storeId='.$this->storeId.' | '.count($fechas).' día(s) | '.round(microtime(true) - $inicio).' s ====');
        foreach ($totales as $k => $v) {
            $this->line(str_pad($k, 16).': '.($k === 'total_usd' ? number_format($v, 2) : $v));
        }
        if (! empty($detalleGlobal)) {
            $this->newLine();
            $this->warn('Detalle ('.count($detalleGlobal).' entradas):');
            foreach (array_slice($detalleGlobal, 0, 200) as $e) {
                $this->line(" - $e");
            }
            if (count($detalleGlobal) > 200) {
                $this->line(' - … ('.(count($detalleGlobal) - 200).' más; ver storage/logs)');
            }
            \Log::warning('TitanioImportarPedidos: detalle de pedidos saltados/errores', ['detalle' => $detalleGlobal]);
        }
        if (! empty($fechasFallidas)) {
            $this->newLine();
            $this->error('Días NO importados por error de API (vuelva a correr el comando con --desde/--hasta sobre ellos):');
            foreach ($fechasFallidas as $f) {
                $this->line(" - $f");
            }
            return self::FAILURE;
        }

        return $totales['errores'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return string[]|null lista de fechas YYYY-MM-DD
     */
    private function resolverFechas(): ?array
    {
        $desde = $this->option('desde');
        $hasta = $this->option('hasta');
        if ($desde || $hasta) {
            try {
                $d = Carbon::parse($desde ?: $hasta)->startOfDay();
                $h = Carbon::parse($hasta ?: Carbon::today()->toDateString())->startOfDay();
            } catch (\Throwable $e) {
                $this->error('Fechas inválidas: '.$e->getMessage());
                return null;
            }
            if ($h->lt($d)) {
                $this->error('--hasta es anterior a --desde.');
                return null;
            }
            if ($d->diffInDays($h) > 400) {
                $this->error('Rango mayor a 400 días; divídalo.');
                return null;
            }
            $fechas = [];
            for ($f = $d->copy(); $f->lte($h); $f->addDay()) {
                $fechas[] = $f->toDateString();
            }
            return $fechas;
        }
        $fecha = $this->option('fecha') ?: Carbon::today()->toDateString();
        try {
            return [Carbon::parse($fecha)->toDateString()];
        } catch (\Throwable $e) {
            $this->error('Fecha inválida: '.$fecha);
            return null;
        }
    }

    /**
     * Consulta la API para un día. Devuelve la lista de pedidos o null (y $error) si falló.
     */
    private function consultarDia(string $fecha, ?string &$error = null): ?array
    {
        $error = null;
        $secret = (string) env('TITANIO_SECRET', self::TITANIO_SECRET_DEFAULT);
        $intentos = 3;
        for ($intento = 1; $intento <= $intentos; $intento++) {
            try {
                $resp = Http::withHeaders([
                    'x-secret' => $secret,
                    'Accept' => 'application/json',
                ])->timeout(180)->post(self::TITANIO_URL, [
                    'fecha' => $fecha,
                    'storeId' => $this->storeId,
                ]);
            } catch (\Throwable $e) {
                $error = 'Error HTTP: '.$e->getMessage();
                if ($intento < $intentos) {
                    sleep(2 * $intento);
                    continue;
                }
                return null;
            }

            if (! $resp->successful()) {
                $error = 'HTTP '.$resp->status().': '.substr($resp->body(), 0, 300);
                if ($resp->status() >= 500 && $intento < $intentos) {
                    sleep(2 * $intento);
                    continue;
                }
                return null;
            }

            $json = $resp->json();
            if (! is_array($json) || empty($json['success']) || ! isset($json['data']) || ! is_array($json['data'])) {
                $error = 'Respuesta inválida: '.substr($resp->body(), 0, 300);
                return null;
            }
            return $json['data'];
        }
        return null;
    }

    /**
     * @return array{resultado: string, total_usd: float}
     */
    private function importarPedido(array $order, bool $dryRun): array
    {
        $uuid = $order['uuid'];
        $items = $order['items'] ?? [];
        $pagos = $order['pagos'] ?? [];
        $createdAt = $this->parseFecha($order['created_at'] ?? null);

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
            if (! $this->productoExiste($idProd)) {
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

        $totalUsd = (float) array_sum(array_column($itemsRows, 'monto'));

        if ($dryRun) {
            $totalPagos = array_sum(array_column($pagosRows, 'monto'));
            $this->line(sprintf(
                '[DRY] uuid=%s fecha=%s items=%d pagos=%d total_usd=%.4f pagado=%.4f tasa=%.4f',
                $uuid, $createdAt ?? '?', count($itemsRows), count($pagosRows), $totalUsd, $totalPagos, $tasa
            ));
            $existed = DB::table('pedidos')->where('uuid', $uuid)->exists();
            return ['resultado' => $existed ? 'overwrite' : 'importado', 'total_usd' => $totalUsd];
        }

        $resultado = DB::transaction(function () use ($uuid, $itemsRows, $pagosRows, $referenciasRows, $createdAt, $idVendedor) {
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

        return ['resultado' => $resultado, 'total_usd' => $totalUsd];
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
        $this->info('==== Resumen reversar '.$fecha.' ====');
        foreach ($stats as $k => $v) {
            $this->line(str_pad($k, 16).": $v");
        }
        return $stats['errores'] > 0 ? self::FAILURE : self::SUCCESS;
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

    private function productoExiste(int $idProd): bool
    {
        if (! isset($this->productoExisteCache[$idProd])) {
            $this->productoExisteCache[$idProd] = DB::table('inventarios')->where('id', $idProd)->exists();
        }
        return $this->productoExisteCache[$idProd];
    }

    /**
     * Normaliza la fecha de la API a 'Y-m-d H:i:s' en la zona horaria de la app. Si viene con zona (Z, +00:00)
     * se convierte; si viene sin zona se asume la de la app. Si no se puede parsear devuelve null.
     */
    private function parseFecha(?string $valor): ?string
    {
        if (! $valor) {
            return null;
        }
        try {
            return Carbon::parse($valor)->timezone(config('app.timezone'))->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
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
