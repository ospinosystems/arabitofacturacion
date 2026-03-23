<?php

namespace App\Console\Commands;

use App\Http\Controllers\sendCentral;
use App\Models\items_pedidos;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Retransmite monto, precio_unitario y tasa de items_pedidos hacia Arabito Central
 * (tabla inventario_sucursal_estadisticas), para corregir datos históricos.
 */
class RetransmitirMontosEstadisticasCentral extends Command
{
    protected $signature = 'central:retransmitir-montos-estadisticas
        {--from-id=0 : Solo ítems con id estrictamente mayor a este valor}
        {--hasta-id= : Opcional: id máximo inclusive}
        {--desde-fecha= : Opcional: created_at >= YYYY-MM-DD (no puede ser anterior al límite de meses salvo --sin-limite-meses)}
        {--meses=5 : Ventana máxima hacia atrás: solo ventas con created_at dentro de los últimos N meses}
        {--sin-limite-meses : Quitar el tope por meses (solo para uso excepcional)}
        {--batch=500 : Tamaño de cada lote hacia central}
        {--dry-run : Solo contar y mostrar muestra, sin enviar}
        {--probar-conexion : Solo comprobar URL, código sucursal y API key contra GET /api/sync/status (no retransmite)}
        {--sin-filtro-pedido : Incluir todos los items con id_producto (por defecto se usa el mismo filtro que sendestadisticasVenta)}';

    protected $description = 'Actualiza en Central monto, precio_unitario y tasa de líneas de venta (inventario_sucursal_estadisticas), por defecto máx. 5 meses de venta';

    public function handle(): int
    {
        $fromId = (int) $this->option('from-id');
        $hastaId = $this->option('hasta-id');
        $hastaId = $hastaId !== null && $hastaId !== '' ? (int) $hastaId : null;
        $desdeFecha = $this->option('desde-fecha') ?: null;
        $meses = max(1, (int) $this->option('meses'));
        $sinLimiteMeses = (bool) $this->option('sin-limite-meses');
        $batch = max(1, (int) $this->option('batch'));
        $dryRun = (bool) $this->option('dry-run');
        $probarConexion = (bool) $this->option('probar-conexion');
        $sinFiltroPedido = (bool) $this->option('sin-filtro-pedido');

        $send = new sendCentral();

        if ($probarConexion) {
            return $this->runProbarConexion($send);
        }

        if ($dryRun) {
            $this->warn('=== MODO DRY-RUN: no se envía nada a Central. Quita --dry-run para retransmitir. ===');
        } else {
            $this->info('=== MODO ENVÍO REAL: se llamará a /api/sync/batch por lotes ===');
        }

        $apiKey = $send->getCentralApiKey();
        if ($apiKey === null || $apiKey === '') {
            $this->error('No hay API key de Central (archivo storage/app/central_api_key.txt o CENTRAL_API_KEY en .env). Sin ella, Central responde 401 y no se actualiza nada.');
            if (!$dryRun) {
                return self::FAILURE;
            }
        }

        $query = $this->baseQuery($sinFiltroPedido);

        if ($fromId > 0) {
            $query->where('id', '>', $fromId);
        }
        if ($hastaId !== null) {
            $query->where('id', '<=', $hastaId);
        }

        if (!$sinLimiteMeses) {
            $limiteInferior = Carbon::now()->subMonths($meses)->startOfDay();
            if ($desdeFecha) {
                $userDesde = Carbon::parse($desdeFecha)->startOfDay();
                if ($userDesde->lt($limiteInferior)) {
                    $this->warn(
                        "--desde-fecha ({$userDesde->toDateString()}) es anterior al límite de {$meses} meses; "
                        . "se usa {$limiteInferior->toDateString()} 00:00:00."
                    );
                    $inicioVentana = $limiteInferior;
                } else {
                    $inicioVentana = $userDesde;
                }
            } else {
                $inicioVentana = $limiteInferior;
            }
            $query->where('created_at', '>=', $inicioVentana);
            $this->line("Ventana de ventas: created_at >= {$inicioVentana->toDateTimeString()} (últimos {$meses} meses como máximo).");
        } elseif ($desdeFecha) {
            $query->where('created_at', '>=', $desdeFecha . ' 00:00:00');
            $this->warn('Sin límite por meses (--sin-limite-meses): se respeta solo --desde-fecha si la indicaste.');
        } else {
            $this->warn('Sin límite por meses: se procesan todos los ítems que cumplan el resto de filtros.');
        }

        $total = (clone $query)->count();
        $this->info("Ítems a procesar: {$total}" . ($sinFiltroPedido ? ' (sin filtro de pedido)' : ' (mismo alcance que estadísticas de venta)'));

        if ($total === 0) {
            return self::SUCCESS;
        }

        if ($dryRun) {
            $muestra = (clone $query)->orderBy('id')->limit(5)->get(['id', 'monto', 'precio_unitario', 'tasa', 'created_at']);
            $this->table(['id', 'monto', 'precio_unitario', 'tasa', 'created_at'], $muestra->map(fn ($r) => [
                $r->id,
                $r->monto,
                $r->precio_unitario,
                $r->tasa,
                $r->created_at,
            ])->toArray());
            $this->warn('Dry-run: no se envió nada a central.');

            return self::SUCCESS;
        }

        $urlBase = rtrim($send->path(), '/');
        $this->info('Sucursal (codigo_origen): ' . $send->getOrigen());
        $this->info("Destino: {$urlBase}/api/sync/batch (tabla_destino inventario_sucursal_estadisticas_montos: monto, precio_unitario, tasa)");

        $enviados = 0;
        $actualizadosCentral = 0;
        $errores = 0;
        $loteNum = 0;

        try {
            $query->orderBy('id')->chunkById($batch, function ($rows) use ($send, &$enviados, &$actualizadosCentral, &$errores, &$loteNum) {
                $loteNum++;
                $data = $rows->map(function ($r) {
                    return [
                        'id' => $r->id,
                        'monto' => $r->monto,
                        'precio_unitario' => $r->precio_unitario,
                        'tasa' => $r->tasa,
                    ];
                })->values()->all();

                try {
                    $response = $send->requestToCentral('post', '/api/sync/batch', [
                        'tabla' => 'items_pedidos',
                        'tabla_destino' => 'inventario_sucursal_estadisticas_montos',
                        'data' => base64_encode(gzcompress(json_encode($data))),
                        'batch_size' => count($data),
                    ], ['timeout' => 180]);
                } catch (ConnectionException $e) {
                    $errores++;
                    $this->error("Lote {$loteNum}: error de red — {$e->getMessage()}");
                    Log::error('retransmitir-montos-estadisticas: ConnectionException', ['e' => $e->getMessage()]);

                    return false;
                }

                $enviados += count($data);

                if (!$response->ok()) {
                    $errores++;
                    $snippet = substr($response->body(), 0, 800);
                    $this->error("Lote {$loteNum}: HTTP {$response->status()} (primer id " . ($data[0]['id'] ?? '?') . ')');
                    $this->line($snippet);
                    Log::error('retransmitir-montos-estadisticas: error HTTP', [
                        'status' => $response->status(),
                        'body' => substr($response->body(), 0, 2000),
                    ]);

                    return false;
                }

                $json = $response->json();
                if (!is_array($json)) {
                    $errores++;
                    $this->error("Lote {$loteNum}: respuesta no es JSON. Cuerpo (recorte): " . substr($response->body(), 0, 500));

                    return false;
                }

                if (!($json['estado'] ?? false)) {
                    $errores++;
                    $this->error('Lote ' . $loteNum . ': Central estado=false — ' . ($json['mensaje'] ?? $json['message'] ?? json_encode($json)));
                    Log::error('retransmitir-montos-estadisticas: central estado false', ['resp' => $json]);

                    return false;
                }

                $actualizadosCentral += (int) ($json['actualizados'] ?? 0);
                $this->line("  Lote {$loteNum}: registros " . count($data) . ", actualizados en central " . ($json['actualizados'] ?? '?'));

                return true;
            }, 'id');
        } catch (Throwable $e) {
            $this->error('Error fatal: ' . $e->getMessage());
            Log::error('retransmitir-montos-estadisticas: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return self::FAILURE;
        }

        $this->info("Listo. Ítems enviados (payload): {$enviados}, lotes con error: {$errores}. Suma actualizados reportada por central: {$actualizadosCentral}");

        return $errores > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function runProbarConexion(sendCentral $send): int
    {
        $urlBase = rtrim($send->path(), '/');
        $codigo = $send->getOrigen();
        $key = $send->getCentralApiKey();

        $this->info("URL Central: {$urlBase}");
        $this->info("codigo_origen: {$codigo}");
        $this->info('API key: ' . ($key !== null && $key !== '' ? '(configurada, longitud ' . strlen($key) . ')' : 'NO CONFIGURADA — obtendrás 401'));

        try {
            $response = $send->requestToCentral('get', '/api/sync/status', [], ['timeout' => 45]);
        } catch (ConnectionException $e) {
            $this->error('No hubo conexión: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->line('HTTP ' . $response->status());
        $body = $response->body();
        $this->line(strlen($body) > 1200 ? substr($body, 0, 1200) . '...' : $body);

        if ($response->ok()) {
            $json = $response->json();
            if (is_array($json) && ($json['estado'] ?? false)) {
                $this->info('Conexión y autenticación OK. Puedes ejecutar el comando sin --dry-run.');

                return self::SUCCESS;
            }
        }

        $this->warn('Si ves 401 Unauthorized, configura la misma API key que en Central (storage/app/central_api_key.txt o CENTRAL_API_KEY).');
        $this->warn('Si ves 404, revisa la URL en sendCentral::path() y que Central tenga desplegada la ruta /api/sync/status.');

        return self::FAILURE;
    }

    /**
     * Mismo criterio que sendCentral::sendestadisticasVenta para id_pedido.
     */
    private function baseQuery(bool $sinFiltroPedido)
    {
        $q = items_pedidos::query()->whereNotNull('id_producto');

        if ($sinFiltroPedido) {
            return $q;
        }

        return $q->whereIn('id_pedido', function ($query) {
            $query->select('id')
                ->from('pedidos')
                ->where(function ($q) {
                    $q->whereIn('id', function ($subQuery) {
                        $subQuery->select('id_pedido')
                            ->from('pago_pedidos')
                            ->where('tipo', '<>', 4);
                    })
                    ->orWhereNotIn('id', function ($subQuery) {
                        $subQuery->select('id_pedido')
                            ->from('pago_pedidos');
                    });
                });
        });
    }
}
