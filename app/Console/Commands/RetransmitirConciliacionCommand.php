<?php

namespace App\Console\Commands;

use App\Http\Controllers\sendCentral;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Retransmite hacia Arabito Central el histórico de la sucursal para tapar los
 * huecos que dejó el sync por cursor (lotes fallidos que nunca se reintentaron
 * porque el receptor igual avanzaba id_last_movs).
 *
 * Manda DOS ríos, por lotes y con reintentos:
 *   - movimientos: movimientos_inventariounitarios (el kardex local)
 *   - ventas:      items_pedidos + sus pedidos y pagos (estadísticas de venta)
 *
 * Va contra el receptor NUEVO de central (/retransmitirConciliacion), que
 * upserta con la misma lógica de siempre pero NO toca los cursores de
 * ultimainformacioncargada: el sync diario sigue intacto.
 *
 * Cada lote lleva un manifiesto (total de filas del rango) y central acumula
 * cuántas recibió en la tabla `retransmisiones`; allá el monitor
 * `retransmision:monitor` muestra el avance hasta el 100%.
 *
 * Es idempotente: se puede relanzar completo o retomar con --desde-id.
 */
class RetransmitirConciliacionCommand extends Command
{
    protected $signature = 'retransmitir:conciliacion
        {--desde= : Fecha inicial YYYY-MM-DD (por defecto hoy menos 1 año)}
        {--hasta= : Fecha final YYYY-MM-DD (por defecto hoy)}
        {--lote=1000 : Filas por petición (bajar si central da timeout o 413)}
        {--solo= : Transmitir solo un río: movimientos | ventas}
        {--desde-id=0 : Retomar desde este id local (aplica al río que corra)}
        {--dry-run : Solo contar y mostrar el plan de lotes, sin enviar}';

    protected $description = 'Retransmite a Central los movimientos de inventario y las ventas del último año (o el rango dado), por lotes, con manifiesto para verificar el 100%';

    /** @var sendCentral */
    private $send;

    public function handle(): int
    {
        $desde = $this->option('desde') ?: Carbon::now()->subYear()->toDateString();
        $hasta = $this->option('hasta') ?: Carbon::now()->toDateString();
        $lote = max(50, (int) $this->option('lote'));
        $solo = $this->option('solo') ?: null;
        $desdeId = (int) $this->option('desde-id');
        $dryRun = (bool) $this->option('dry-run');

        if ($solo !== null && ! in_array($solo, ['movimientos', 'ventas'], true)) {
            $this->error("--solo debe ser 'movimientos' o 'ventas'.");
            return self::FAILURE;
        }
        if (Carbon::parse($desde)->gt(Carbon::parse($hasta))) {
            $this->error("--desde ($desde) es posterior a --hasta ($hasta).");
            return self::FAILURE;
        }

        $this->send = new sendCentral();
        $apiKey = $this->send->getCentralApiKey();
        if (! $dryRun && ($apiKey === null || $apiKey === '')) {
            $this->error('No hay API key de Central: sin ella el receptor responde 401. (storage/app/central_api_key.txt o CENTRAL_API_KEY)');
            return self::FAILURE;
        }

        $this->info("Rango: $desde → $hasta | lote: $lote" . ($dryRun ? ' | DRY-RUN (no se envía nada)' : ''));

        $ok = true;
        if ($solo === null || $solo === 'movimientos') {
            $ok = $this->retransmitirMovimientos($desde, $hasta, $lote, $desdeId, $dryRun) && $ok;
        }
        if ($solo === null || $solo === 'ventas') {
            // --desde-id solo aplica cuando se corre un río puntual: los ids de
            // kardex y de items no tienen nada que ver entre sí.
            $cursorVentas = ($solo === 'ventas') ? $desdeId : 0;
            $ok = $this->retransmitirVentas($desde, $hasta, $lote, $cursorVentas, $dryRun) && $ok;
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Río 1: kardex (movimientos_inventariounitarios → movsinventarios)
    // ─────────────────────────────────────────────────────────────────────

    private function retransmitirMovimientos(string $desde, string $hasta, int $lote, int $cursor, bool $dryRun): bool
    {
        $base = function () use ($desde, $hasta) {
            return DB::table('movimientos_inventariounitarios')
                ->whereBetween('created_at', ["$desde 00:00:00", "$hasta 23:59:59"]);
        };

        $manifiesto = (clone $base())->count();
        $suma = (float) (clone $base())->sum('cantidad');
        $totalLotes = max(1, (int) ceil($manifiesto / $lote));

        $this->newLine();
        $this->info("── MOVIMIENTOS: $manifiesto filas (Σ cantidad = $suma) en $totalLotes lotes ──");
        if ($manifiesto === 0) {
            $this->warn('Nada que retransmitir en el rango.');
            return true;
        }
        if ($dryRun) {
            return true;
        }

        $enviados = 0;
        $numLote = 0;
        // Si se retoma con --desde-id, los lotes ya enviados igual cuentan en el
        // total: el número de lote se calcula por filas restantes, central solo
        // lo usa para saber cuándo llegó el último.
        if ($cursor > 0) {
            $yaFuera = (clone $base())->where('id', '<=', $cursor)->count();
            $enviados = $yaFuera;
            $numLote = (int) floor($yaFuera / $lote);
            $this->warn("Retomando desde id > $cursor ($yaFuera filas ya cubiertas antes).");
        }

        while (true) {
            $filas = (clone $base())
                ->where('id', '>', $cursor)
                ->orderBy('id')
                ->limit($lote)
                ->get(['id', 'id_producto', 'id_pedido', 'id_usuario', 'cantidad', 'cantidadafter', 'origen', 'created_at']);

            if ($filas->isEmpty()) {
                break;
            }

            $cursor = (int) $filas->last()->id;
            $numLote++;
            $enviados += $filas->count();

            // El receptor de central reusa sendlasmovs_movs, que espera el
            // gzip+base64 del ARRAY de filas pelado (mismo formato del sync).
            $payload = base64_encode(gzcompress(json_encode($filas->map(function ($f) {
                return [
                    'id' => (int) $f->id,
                    'id_producto' => $f->id_producto,
                    'id_pedido' => $f->id_pedido,
                    'id_usuario' => $f->id_usuario,
                    'cantidad' => $f->cantidad,
                    'cantidadafter' => $f->cantidadafter,
                    'origen' => $f->origen,
                    'created_at' => (string) $f->created_at,
                ];
            })->values()->all())));

            $resp = $this->enviarLote([
                'tipo' => 'movimientos',
                'lote' => $numLote,
                'total_lotes' => $totalLotes,
                'manifiesto_total' => $manifiesto,
                'manifiesto_suma' => $suma,
                'desde' => $desde,
                'hasta' => $hasta,
                'enviados' => $filas->count(),
                'payload' => $payload,
            ], "movimientos lote $numLote/$totalLotes (hasta id $cursor)");

            if ($resp === null) {
                $this->error("ABORTADO. Retomar con: php artisan retransmitir:conciliacion --solo=movimientos --desde=$desde --hasta=$hasta --lote=$lote --desde-id=$cursor");
                return false;
            }

            $pct = round($enviados * 100 / $manifiesto, 1);
            $this->line(sprintf('  lote %d/%d — %d/%d (%s%%) — central acumula %s de %s',
                $numLote, $totalLotes, $enviados, $manifiesto, $pct,
                $resp['recibidos'] ?? '?', $resp['manifiesto'] ?? '?'));
        }

        return $this->cierreRio('movimientos', $enviados, $manifiesto);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Río 2: ventas (items_pedidos + pedidos + pagos → estadísticas)
    // ─────────────────────────────────────────────────────────────────────

    private function retransmitirVentas(string $desde, string $hasta, int $lote, int $cursor, bool $dryRun): bool
    {
        // Mismo universo que sendestadisticasVenta, con UNA ampliación: también
        // entran los pedidos cuyo único pago es crédito (tipo 4) con monto > 0
        // — créditos a clientes foráneos, que central SÍ cuenta como venta. El
        // sync original los dejaba fuera y por eso faltan estadísticas allá.
        // Las transferencias disfrazadas de venta (tipo 4, monto 0) siguen
        // excluidas, igual que siempre.
        $base = function () use ($desde, $hasta) {
            return DB::table('items_pedidos')
                ->whereBetween('created_at', ["$desde 00:00:00", "$hasta 23:59:59"])
                ->whereNotNull('id_producto')
                ->whereIn('id_pedido', function ($q) {
                    $q->select('id')->from('pedidos')->where(function ($w) {
                        $w->whereIn('id', function ($s) {
                            $s->select('id_pedido')->from('pago_pedidos')
                                ->where(function ($p) {
                                    $p->where('tipo', '<>', 4)->orWhere('monto', '>', 0);
                                });
                        })->orWhereNotIn('id', function ($s) {
                            $s->select('id_pedido')->from('pago_pedidos');
                        });
                    });
                });
        };

        $manifiesto = (clone $base())->count();
        $totalLotes = max(1, (int) ceil($manifiesto / $lote));

        $this->newLine();
        $this->info("── VENTAS: $manifiesto líneas de venta en $totalLotes lotes ──");
        if ($manifiesto === 0) {
            $this->warn('Nada que retransmitir en el rango.');
            return true;
        }
        if ($dryRun) {
            return true;
        }

        $enviados = 0;
        $numLote = 0;
        if ($cursor > 0) {
            $yaFuera = (clone $base())->where('id', '<=', $cursor)->count();
            $enviados = $yaFuera;
            $numLote = (int) floor($yaFuera / $lote);
            $this->warn("Retomando desde id > $cursor ($yaFuera líneas ya cubiertas antes).");
        }

        while (true) {
            $items = (clone $base())
                ->where('id', '>', $cursor)
                ->orderBy('id')
                ->limit($lote)
                ->get([
                    'id', 'id_pedido', 'cantidad', 'id_producto', 'created_at', 'lote',
                    'abono', 'monto', 'tasa', 'tasa_cop', 'precio_unitario',
                    'descuento', 'entregado', 'condicion',
                ]);

            if ($items->isEmpty()) {
                break;
            }

            $cursor = (int) $items->last()->id;
            $numLote++;
            $enviados += $items->count();

            $pedidoIds = $items->pluck('id_pedido')->unique()->values();
            $pedidos = DB::table('pedidos')->whereIn('id', $pedidoIds)->get();
            $pagos = DB::table('pago_pedidos')->whereIn('id_pedido', $pedidoIds)->get();

            // Mismo sobre {items, pedidos, pagos} que arma sendestadisticasVenta:
            // central lo procesa con procesarDatosSucursal tal cual.
            $payload = base64_encode(gzcompress(json_encode([
                'items' => $items->values()->all(),
                'pedidos' => $pedidos->values()->all(),
                'pagos' => $pagos->values()->all(),
            ])));

            $resp = $this->enviarLote([
                'tipo' => 'ventas',
                'lote' => $numLote,
                'total_lotes' => $totalLotes,
                'manifiesto_total' => $manifiesto,
                'desde' => $desde,
                'hasta' => $hasta,
                'enviados' => $items->count(),
                'payload' => $payload,
            ], "ventas lote $numLote/$totalLotes (hasta id $cursor)");

            if ($resp === null) {
                $this->error("ABORTADO. Retomar con: php artisan retransmitir:conciliacion --solo=ventas --desde=$desde --hasta=$hasta --lote=$lote --desde-id=$cursor");
                return false;
            }

            $pct = round($enviados * 100 / $manifiesto, 1);
            $this->line(sprintf('  lote %d/%d — %d/%d (%s%%) — central acumula %s de %s',
                $numLote, $totalLotes, $enviados, $manifiesto, $pct,
                $resp['recibidos'] ?? '?', $resp['manifiesto'] ?? '?'));
        }

        return $this->cierreRio('ventas', $enviados, $manifiesto);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Transporte
    // ─────────────────────────────────────────────────────────────────────

    /**
     * POST con 3 intentos. Devuelve el JSON del receptor, o null si el lote no
     * pudo entrar (el llamador aborta e imprime cómo retomar: como todo es
     * upsert, relanzar jamás duplica).
     */
    private function enviarLote(array $params, string $etiqueta): ?array
    {
        $esperas = [5, 20, 60];
        for ($intento = 1; $intento <= 3; $intento++) {
            try {
                $resp = $this->send->requestToCentral('post', '/retransmitirConciliacion', $params, ['timeout' => 180]);
                $json = $resp->json();
                if ($resp->successful() && is_array($json) && ! empty($json['ok'])) {
                    return $json;
                }
                $detalle = is_array($json) ? json_encode($json) : substr((string) $resp->body(), 0, 300);
                $this->warn("  $etiqueta: central respondió " . $resp->status() . " → $detalle (intento $intento/3)");
            } catch (Throwable $e) {
                $this->warn("  $etiqueta: " . $e->getMessage() . " (intento $intento/3)");
            }
            if ($intento < 3) {
                sleep($esperas[$intento - 1]);
            }
        }

        return null;
    }

    private function cierreRio(string $rio, int $enviados, int $manifiesto): bool
    {
        if ($enviados >= $manifiesto) {
            $this->info("✔ $rio: $enviados/$manifiesto transmitidos (100%). Verificar en central: php artisan retransmision:monitor");
            return true;
        }
        $this->error("✘ $rio: $enviados/$manifiesto — el rango cambió durante la corrida o quedaron filas fuera. Relanzar el comando (es idempotente).");
        return false;
    }
}
