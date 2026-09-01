<?php

namespace App\Console\Commands;

use App\Http\Controllers\sendCentral;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MUDANZA DE PEDIDOS: esta sucursal → Arábito Central → TitanioPOS.
 *
 * Hermano de {@see MigrarInventarioCommand}: mismo canal, mismo protocolo de
 * cuatro fases y la misma exigencia del 100%. Se corre el día de la mudanza,
 * justo después del inventario, para que TitanioPOS arranque con el histórico
 * de ventas y la tienda pueda hacer DEVOLUCIONES, CAMBIOS y GARANTÍAS sobre
 * facturas emitidas en el sistema viejo.
 *
 * Reemplaza al ETL `orders:migrate-sinapsis`, que corría DENTRO de TitanioPOS
 * leyendo la base de la sucursal por conexión directa: la tienda destino salía
 * de un `--store-id` suelto y los lotes se resolvían sin filtrar por tienda, así
 * que las órdenes de una sucursal terminaron colgando del inventario de otra
 * (Altagracia de Orituco). Acá la tienda destino queda determinada por el
 * ORIGEN del envío —`codigo_origen` + API key, que pone `sendCentral`—, no por
 * una resolución global: no hay forma de equivocarse de tienda.
 *
 * Viaja el pedido COMPLETO: cabecera, items, pagos y el cliente. Los pagos son
 * imprescindibles — sin ellos TitanioPOS puede cambiar y garantizar, pero no
 * puede devolver dinero, porque no sabe cómo pagó el cliente.
 *
 *   php artisan pedidos:migrar --dry-run           solo cuenta, no transmite
 *   php artisan pedidos:migrar                     transmite y verifica
 *   php artisan pedidos:migrar --referencia=XXX    reanuda una migración cortada
 */
class MigrarPedidosCommand extends Command
{
    protected $signature = 'pedidos:migrar
        {--desde=2026-01-01 : solo pedidos creados desde esta fecha}
        {--chunk=300 : pedidos por envío}
        {--referencia= : reanudar una migración ya iniciada}
        {--dry-run : solo cuenta lo que enviaría, no transmite}
        {--allow-partial : deja que TitanioPOS cargue lo que pueda (default: todo-o-nada)}
        {--espera=20 : segundos entre consultas de estado}';

    protected $description = 'Transmite los pedidos de esta sucursal a central y de ahí a TitanioPOS (mudanza).';

    public function handle(): int
    {
        @set_time_limit(0);
        $sc = new sendCentral();

        $desde = (string) $this->option('desde');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
            $this->error('--desde debe tener formato AAAA-MM-DD.');

            return self::FAILURE;
        }

        // El `id` local del pedido ES el `idinsucursal` con el que central lo
        // identifica, y su `uuid` es la llave de idempotencia en TitanioPOS.
        // Reenviar un pedido ya migrado no lo duplica en ningún tramo.
        $base = fn () => DB::table('pedidos')->where('created_at', '>=', $desde . ' 00:00:00');

        $total = $base()->count();
        if ($total === 0) {
            $this->error("No hay pedidos desde {$desde} en esta sucursal.");

            return self::FAILURE;
        }

        $items = DB::table('items_pedidos as ip')
            ->join('pedidos as p', 'p.id', '=', 'ip.id_pedido')
            ->where('p.created_at', '>=', $desde . ' 00:00:00')
            ->count();
        $pagos = DB::table('pago_pedidos as pp')
            ->join('pedidos as p', 'p.id', '=', 'pp.id_pedido')
            ->where('p.created_at', '>=', $desde . ' 00:00:00')
            ->count();

        $this->info("Pedidos desde {$desde}: {$total}  ({$items} items · {$pagos} pagos)");

        // Un pedido sin items no sirve para posventa: no hay nada que devolver
        // ni cambiar. Se avisa pero NO se excluye — el histórico igual vale, y
        // excluirlo rompería el conteo del 100% contra central.
        $sinItems = $base()->whereNotExists(function ($q) {
            $q->select(DB::raw(1))->from('items_pedidos')->whereColumn('items_pedidos.id_pedido', 'pedidos.id');
        })->count();
        if ($sinItems > 0) {
            $this->warn("   {$sinItems} pedido(s) sin items — viajan igual, como histórico.");
        }

        // Devoluciones cuya venta original quedó FUERA del corte: del otro lado
        // no se pueden enlazar a su padre y se descartan. Vale saberlo antes de
        // arrancar, no al final.
        $huerfanas = $base()
            ->whereNotNull('isdevolucionOriginalid')
            ->where('isdevolucionOriginalid', '>', 0)
            ->whereNotExists(function ($q) use ($desde) {
                $q->select(DB::raw(1))->from('pedidos as orig')
                    ->whereColumn('orig.id', 'pedidos.isdevolucionOriginalid')
                    ->where('orig.created_at', '>=', $desde . ' 00:00:00');
            })->count();
        if ($huerfanas > 0) {
            $this->warn("   {$huerfanas} devolución(es) cuya venta original es anterior a {$desde}: TitanioPOS las descartará.");
        }

        if ($this->option('dry-run')) {
            $this->info("DRY-RUN: se enviarían {$total} pedidos. No se transmitió nada.");

            return self::SUCCESS;
        }

        // ─── 1. Iniciar en central ───────────────────────────────────────────
        $ref = $this->option('referencia') ?: ($sc->getOrigen() . '-ped-' . now()->format('Ymd-His'));
        $r = $sc->requestToCentral('POST', '/migracion/pedidos/iniciar', [
            'total_esperado' => $total,
            'referencia'     => $ref,
            'desde'          => $desde,
        ], ['timeout' => 120]);

        if (! $r->successful() || ! ($r->json()['estado'] ?? false)) {
            $this->error('No se pudo iniciar la migración: ' . mb_substr($r->body(), 0, 300));

            return self::FAILURE;
        }
        $migId = $r->json()['id'];
        $this->info("Migración de pedidos #{$migId} · referencia {$ref}"
            . (($r->json()['reanudada'] ?? false) ? '  (REANUDADA)' : ''));
        Log::info("[pedidos:migrar] iniciada #{$migId} ref={$ref} esperados={$total}");

        // ─── 2. Transmitir por chunks ────────────────────────────────────────
        $tam = max(50, (int) $this->option('chunk'));
        $barra = $this->output->createProgressBar((int) ceil($total / $tam));
        $barra->start();

        $recibidos = 0;
        $rechazados = [];
        $ok = true;

        $base()->orderBy('id')->chunk($tam, function ($filas) use ($sc, $migId, $ref, &$recibidos, &$rechazados, &$ok, $barra) {
            $payload = $this->armarPayload($filas);

            // Reintentos: un envío largo puede toparse con un corte de red.
            for ($intento = 1; $intento <= 3; $intento++) {
                $r = $sc->requestToCentral('POST', '/migracion/pedidos/chunk', [
                    'id'      => $migId,
                    'pedidos' => $payload,
                ], ['timeout' => 300]);

                if ($r->successful() && ($r->json()['estado'] ?? false)) {
                    $recibidos = $r->json()['total_recibido'];
                    foreach (($r->json()['rechazados'] ?? []) as $x) {
                        $rechazados[] = $x;
                    }
                    $barra->advance();

                    return true;
                }
                sleep(3 * $intento);
            }

            $this->newLine();
            $this->error("Chunk falló tras 3 intentos. Reanuda con:  php artisan pedidos:migrar --referencia={$ref}");
            $ok = false;

            return false;   // corta el recorrido
        });

        $barra->finish();
        $this->newLine(2);

        if (! $ok) {
            return self::FAILURE;
        }

        $this->info("Recibidos en central: {$recibidos} de {$total}");
        if ($rechazados) {
            $this->warn(count($rechazados) . ' pedido(s) rechazados por central:');
            foreach (array_slice($rechazados, 0, 10) as $x) {
                $this->line('   ' . json_encode($x));
            }
        }

        // ─── 3. Cerrar: central exige el 100% y transmite a TitanioPOS ───────
        //
        // Se llama en bucle a propósito. Central entrega el histórico a
        // TitanioPOS por tandas y devuelve `completo: false` mientras le queden:
        // cientos de tandas seguidas se pasarían del timeout de PHP. Cada
        // llamada retoma en la tanda siguiente, así que ninguna se repite.
        $this->info('Cerrando y transmitiendo a TitanioPOS…');
        $d = [];

        for ($vuelta = 1; $vuelta <= 200; $vuelta++) {
            $r = $sc->requestToCentral('POST', '/migracion/pedidos/cerrar', [
                'id'            => $migId,
                'allow_partial' => $this->option('allow-partial') ? 1 : 0,
            ], ['timeout' => 1800]);

            $d = $r->json();
            if (! $r->successful() || ! ($d['estado'] ?? false)) {
                $this->error('CERRAR falló: ' . ($d['msj'] ?? mb_substr($r->body(), 0, 300)));
                $this->warn("Reintenta con:  php artisan pedidos:migrar --referencia={$ref}");

                return self::FAILURE;
            }

            if ($d['completo'] ?? false) {
                break;
            }

            $this->line('  ' . ($d['msj'] ?? 'transmitiendo…'));
        }

        if (! ($d['completo'] ?? false)) {
            $this->error('La transmisión a TitanioPOS no terminó tras 200 vueltas.');
            $this->warn("Reintenta con:  php artisan pedidos:migrar --referencia={$ref}");

            return self::FAILURE;
        }

        $this->info('  transmitidos a TitanioPOS: ' . ($d['total_transmitido'] ?? '?')
            . ' en ' . ($d['tandas'] ?? '?') . ' tanda(s)');
        Log::info("[pedidos:migrar] transmitida ref={$ref} lote=" . ($d['import_id'] ?? '?'));

        // ─── 4. Esperar y VERIFICAR el 100% ──────────────────────────────────
        $espera = max(5, (int) $this->option('espera'));
        $this->info("Esperando a que TitanioPOS procese (consulta cada {$espera}s)…");

        for ($i = 1; $i <= 360; $i++) {
            sleep($espera);
            $r = $sc->requestToCentral('POST', '/migracion/pedidos/estado', ['id' => $migId], ['timeout' => 120]);
            $d = $r->json();

            if (! ($d['estado'] ?? false)) {
                $this->warn('  consultando… ' . ($d['msj'] ?? mb_substr($r->body(), 0, 120)));
                continue;
            }
            if (! ($d['finalizado'] ?? false)) {
                $this->line("  [{$i}] " . ($d['titanio_status'] ?? 'pendiente')
                    . '  ' . ($d['insertados'] ?? 0) . '/' . ($d['enviados'] ?? '?'));
                continue;
            }

            $this->newLine();
            if ($d['verificado'] ?? false) {
                $this->info('══════════════════════════════════════════════');
                $this->info('  ✓ PEDIDOS MIGRADOS Y VERIFICADOS AL 100%');
                $this->info("     Enviados:  {$d['enviados']}");
                $this->info("     Creados:   {$d['creados']}   (ya estaban: {$d['ya_estaban']})");
                $this->info('     Items: ' . ($d['items'] ?? '—') . ' · Pagos: ' . ($d['pagos'] ?? '—'));
                $descartados = (int) ($d['summary']['items_skipped'] ?? 0)
                    + (int) ($d['summary']['payments_skipped'] ?? 0)
                    + (int) ($d['summary']['missing_parent'] ?? 0);
                if ($descartados > 0) {
                    $this->warn("     {$descartados} línea(s) descartada(s) — detalle en la referencia {$ref}.");
                }
                $this->info('══════════════════════════════════════════════');
                Log::info("[pedidos:migrar] VERIFICADA ref={$ref} creados={$d['creados']}");

                return self::SUCCESS;
            }

            $this->error('══════════════════════════════════════════════');
            $this->error('  ✗ LA MIGRACIÓN NO QUEDÓ COMPLETA');
            $this->error('     Estado TitanioPOS: ' . ($d['titanio_status'] ?? '?'));
            $this->error("     Enviados: {$d['enviados']} · Asimilados: {$d['insertados']} · Faltantes: {$d['faltantes']}");
            if ($d['error_message'] ?? null) {
                $this->error('     ' . $d['error_message']);
            }
            $this->error('══════════════════════════════════════════════');
            Log::error("[pedidos:migrar] INCOMPLETA ref={$ref}: " . json_encode($d));

            return self::FAILURE;
        }

        $this->warn('Se agotó la espera. La carga sigue en TitanioPOS; consulta el estado con:');
        $this->line("   php artisan pedidos:migrar --referencia={$ref}");

        return self::FAILURE;
    }

    /**
     * Arma el payload de un lote de pedidos.
     *
     * Todo lo que TitanioPOS necesita viaja acá dentro: del otro lado no se
     * consulta ninguna base de la sucursal. Los items traen la descripción y el
     * iva de la ficha para poder crear el producto si no existiera, y las
     * devoluciones traen el `uuid` de su venta original —esa es la única forma
     * de enlazarlas allá, donde el id local del pedido ya no significa nada.
     *
     * Items, pagos, clientes y fichas se resuelven con UNA consulta por lote
     * cada uno: con decenas de miles de pedidos, hacerlo por pedido es la
     * diferencia entre minutos y horas.
     */
    private function armarPayload($filas): array
    {
        $ids = $filas->pluck('id')->all();

        $itemsPorPedido = DB::table('items_pedidos')->whereIn('id_pedido', $ids)->get()->groupBy('id_pedido');
        $pagosPorPedido = DB::table('pago_pedidos')->whereIn('id_pedido', $ids)->get()->groupBy('id_pedido');

        $idsCliente = array_values(array_filter($filas->pluck('id_cliente')->unique()->all()));
        $clientes = $idsCliente
            ? DB::table('clientes')->whereIn('id', $idsCliente)->get()->keyBy('id')
            : collect();

        // Ficha del producto de cada línea del lote (descripción e iva).
        $idsProducto = $itemsPorPedido->flatten(1)->pluck('id_producto')->filter()->unique()->all();
        $productos = $idsProducto
            ? DB::table('inventarios')->whereIn('id', $idsProducto)->get(['id', 'descripcion', 'iva'])->keyBy('id')
            : collect();

        // uuid de la venta original de cada devolución del lote.
        $idsPadre = array_values(array_filter($filas->pluck('isdevolucionOriginalid')->unique()->all()));
        $padres = $idsPadre
            ? DB::table('pedidos')->whereIn('id', $idsPadre)->pluck('uuid', 'id')
            : collect();

        return $filas->map(function ($p) use ($itemsPorPedido, $pagosPorPedido, $clientes, $productos, $padres) {
            $cliente = $clientes->get($p->id_cliente);

            return [
                'idinsucursal'           => $p->id,
                'uuid'                   => $p->uuid,
                'numero_factura'         => $p->numero_factura,
                'estado'                 => $p->estado,
                'isdevolucionOriginalid' => $p->isdevolucionOriginalid,
                // Sin esto la devolución no se puede enlazar a su venta.
                'parent_uuid'            => $p->isdevolucionOriginalid
                    ? ($padres[$p->isdevolucionOriginalid] ?? null)
                    : null,
                'fecha_factura'          => $p->fecha_factura ?? null,
                'id_vendedor'            => $p->id_vendedor ?? null,
                'created_at'             => $p->created_at,
                'updated_at'             => $p->updated_at ?? $p->created_at,

                'cliente' => $cliente ? [
                    'identificacion' => $cliente->identificacion ?? null,
                    'nombre'         => $cliente->nombre ?? null,
                    'correo'         => $cliente->correo ?? null,
                    'telefono'       => $cliente->telefono ?? null,
                    'direccion'      => $cliente->direccion ?? null,
                    'created_at'     => $cliente->created_at ?? null,
                    'updated_at'     => $cliente->updated_at ?? null,
                ] : null,

                'items' => collect($itemsPorPedido->get($p->id, []))->map(function ($i) use ($productos) {
                    $ficha = $productos->get($i->id_producto);

                    return [
                        // `id_producto` es el id LOCAL del inventario, que es
                        // exactamente el `source_id` con que viajó la mudanza de
                        // inventario. Del otro lado se resuelve DENTRO de la
                        // tienda destino, nunca contra el catálogo global.
                        'id_producto'     => $i->id_producto,
                        'descripcion'     => $ficha->descripcion ?? null,
                        'iva'             => $ficha->iva ?? 0,
                        'lote'            => $i->lote ?? null,
                        'cantidad'        => $i->cantidad,
                        'descuento'       => $i->descuento ?? 0,
                        'monto'           => $i->monto ?? null,
                        'monto_bs'        => $i->monto_bs ?? null,
                        'tasa'            => $i->tasa ?? null,
                        'precio_unitario' => $i->precio_unitario ?? null,
                        'condicion'       => $i->condicion ?? null,
                        'created_at'      => $i->created_at ?? null,
                        'updated_at'      => $i->updated_at ?? null,
                    ];
                })->values()->all(),

                'pagos' => collect($pagosPorPedido->get($p->id, []))->map(fn ($g) => [
                    'tipo'              => $g->tipo,
                    'monto'             => $g->monto,
                    'monto_original'    => $g->monto_original ?? null,
                    'monto_bs'          => $g->monto_bs ?? null,
                    'moneda'            => $g->moneda ?? null,
                    'referencia'        => $g->referencia ?? null,
                    'pos_lote'          => $g->pos_lote ?? null,
                    'pos_terminal'      => $g->pos_terminal ?? null,
                    'pos_responsecode'  => $g->pos_responsecode ?? null,
                    'pos_amount'        => $g->pos_amount ?? null,
                    'pos_json_response' => $g->pos_json_response ?? null,
                    'created_at'        => $g->created_at ?? null,
                    'updated_at'        => $g->updated_at ?? null,
                ])->values()->all(),
            ];
        })->values()->all();
    }
}
