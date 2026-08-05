<?php

namespace App\Console\Commands;

use App\Http\Controllers\sendCentral;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MUDANZA DE INVENTARIO: esta sucursal → Arábito Central → TitanioPOS.
 *
 * Toma el inventario COMPLETO de la tienda, lo transmite a central por chunks,
 * central actualiza el suyo producto por producto y lo inyecta en TitanioPOS.
 * Al final verifica que TitanioPOS asimiló el 100% de las filas.
 *
 * Pensado para el día de la mudanza a TitanioPOS: evita traspasos por SQL y el
 * desfase de inventario mientras dura el proceso (que es largo).
 *
 * Usa `sendCentral::requestToCentral()`, el mismo canal que el resto de la
 * sincronización: ya resuelve codigo_origen, API key, versión y reintentos.
 *
 *   php artisan inventario:migrar --dry-run          solo cuenta, no transmite
 *   php artisan inventario:migrar                    transmite y verifica
 *   php artisan inventario:migrar --referencia=XXX   reanuda una migración cortada
 */
class MigrarInventarioCommand extends Command
{
    protected $signature = 'inventario:migrar
        {--chunk=1000 : filas por envío}
        {--referencia= : reanudar una migración ya iniciada}
        {--dry-run : solo cuenta lo que enviaría, no transmite}
        {--allow-partial : deja que TitanioPOS cargue lo que pueda (default: todo-o-nada)}
        {--espera=20 : segundos entre consultas de estado}';

    protected $description = 'Transmite el inventario COMPLETO de esta sucursal a central y de ahí a TitanioPOS (mudanza).';

    public function handle(): int
    {
        @set_time_limit(0);
        $sc = new sendCentral();

        // Inventario local. El `id` local ES el `idinsucursal` que central usa.
        $base = DB::table('inventarios')->select([
            'id as idinsucursal',
            'codigo_barras',
            'codigo_proveedor',
            'descripcion',
            'iva',
            'cantidad',
            'precio_base',
            'precio',
            'created_at',
            'updated_at',
        ]);

        $total = (clone $base)->count();
        if ($total === 0) {
            $this->error('El inventario local está vacío.');
            return self::FAILURE;
        }

        // El código de barras es OBLIGATORIO: sin él central rechaza la fila y
        // TitanioPOS no puede resolver el producto. En esta base es UNIQUE y NOT
        // NULL, así que no debería haber ninguno — se valida igual por seguridad.
        $sinCodigo = (clone $base)->where(function ($q) {
            $q->whereNull('codigo_barras')->orWhere('codigo_barras', '');
        })->count();

        $this->info("Inventario local: {$total} productos"
            . ($sinCodigo ? "  ({$sinCodigo} SIN código de barras → NO se migran)" : ''));

        if ($sinCodigo > 0) {
            foreach ((clone $base)->where(function ($q) {
                $q->whereNull('codigo_barras')->orWhere('codigo_barras', '');
            })->limit(10)->get() as $p) {
                $this->line("   id={$p->idinsucursal}  {$p->descripcion}");
            }
            if (! $this->confirm('Esos productos quedarán fuera. ¿Continuar?', false)) {
                return self::FAILURE;
            }
        }

        $aEnviar = $total - $sinCodigo;
        $conCodigo = (clone $base)->whereNotNull('codigo_barras')->where('codigo_barras', '!=', '');

        if ($this->option('dry-run')) {
            $this->info("DRY-RUN: se enviarían {$aEnviar} productos. No se transmitió nada.");
            return self::SUCCESS;
        }

        // ─── 1. Iniciar en central ───────────────────────────────────────────
        $ref = $this->option('referencia') ?: ($sc->getOrigen() . '-' . now()->format('Ymd-His'));
        $r = $sc->requestToCentral('POST', '/migracion/inventario/iniciar', [
            'total_esperado' => $aEnviar,
            'referencia'     => $ref,
        ], ['timeout' => 120]);

        if (! $r->successful() || ! ($r->json()['estado'] ?? false)) {
            $this->error('No se pudo iniciar la migración: ' . mb_substr($r->body(), 0, 300));
            return self::FAILURE;
        }
        $migId = $r->json()['id'];
        $this->info("Migración #{$migId} · referencia {$ref}"
            . (($r->json()['reanudada'] ?? false) ? '  (REANUDADA)' : ''));
        Log::info("[inventario:migrar] iniciada #{$migId} ref={$ref} esperados={$aEnviar}");

        // ─── 2. Transmitir por chunks ────────────────────────────────────────
        $tam = max(100, (int) $this->option('chunk'));
        $barra = $this->output->createProgressBar((int) ceil($aEnviar / $tam));
        $barra->start();

        $recibidos = 0; $rechazados = []; $ok = true;

        $conCodigo->orderBy('id')->chunk($tam, function ($filas) use ($sc, $migId, $ref, &$recibidos, &$rechazados, &$ok, $barra) {
            $items = $filas->map(fn ($f) => (array) $f)->all();

            // Reintentos: un envío largo puede toparse con un corte de red.
            for ($intento = 1; $intento <= 3; $intento++) {
                $r = $sc->requestToCentral('POST', '/migracion/inventario/chunk', [
                    'id'    => $migId,
                    'items' => $items,
                ], ['timeout' => 300]);

                if ($r->successful() && ($r->json()['estado'] ?? false)) {
                    $recibidos = $r->json()['total_recibido'];
                    foreach (($r->json()['rechazados'] ?? []) as $x) $rechazados[] = $x;
                    $barra->advance();
                    return true;
                }
                sleep(3 * $intento);
            }

            $this->newLine();
            $this->error("Chunk falló tras 3 intentos. Reanuda con:  php artisan inventario:migrar --referencia={$ref}");
            $ok = false;
            return false;   // corta el recorrido
        });

        $barra->finish();
        $this->newLine(2);

        if (! $ok) return self::FAILURE;

        $this->info("Recibidos en central: {$recibidos} de {$aEnviar}");
        if ($rechazados) {
            $this->warn(count($rechazados) . ' fila(s) rechazadas por central:');
            foreach (array_slice($rechazados, 0, 10) as $x) $this->line('   ' . json_encode($x));
        }

        // ─── 3. Cerrar: central exige el 100% y transmite a TitanioPOS ───────
        $this->info('Cerrando y transmitiendo a TitanioPOS…');
        $r = $sc->requestToCentral('POST', '/migracion/inventario/cerrar', [
            'id'            => $migId,
            'allow_partial' => $this->option('allow-partial') ? 1 : 0,
        ], ['timeout' => 900]);

        $d = $r->json();
        if (! $r->successful() || ! ($d['estado'] ?? false)) {
            $this->error('CERRAR falló: ' . ($d['msj'] ?? mb_substr($r->body(), 0, 300)));
            $this->warn("No se transmitió a TitanioPOS. Reanuda con --referencia={$ref}");
            return self::FAILURE;
        }

        $this->info('  central actualizado: ' . json_encode($d['central'] ?? []));
        $this->info('  carga TitanioPOS: ' . ($d['import_id'] ?? '?'));
        Log::info("[inventario:migrar] transmitida ref={$ref} import=" . ($d['import_id'] ?? '?'));

        // ─── 4. Esperar y VERIFICAR el 100% ──────────────────────────────────
        $espera = max(5, (int) $this->option('espera'));
        $this->info("Esperando a que TitanioPOS procese (consulta cada {$espera}s)…");

        for ($i = 1; $i <= 90; $i++) {
            sleep($espera);
            $r = $sc->requestToCentral('POST', '/migracion/inventario/estado', ['id' => $migId], ['timeout' => 120]);
            $d = $r->json();

            if (! ($d['estado'] ?? false)) {
                $this->warn('  consultando… ' . ($d['msj'] ?? mb_substr($r->body(), 0, 120)));
                continue;
            }
            if (! ($d['finalizado'] ?? false)) {
                $this->line("  [{$i}] " . ($d['titanio_status'] ?? 'pendiente') . '…');
                continue;
            }

            $this->newLine();
            if ($d['verificado'] ?? false) {
                $this->info('══════════════════════════════════════════════');
                $this->info('  ✓ MIGRACIÓN VERIFICADA AL 100%');
                $this->info("     Enviados:   {$d['enviados']}");
                $this->info("     Insertados: {$d['insertados']}");
                $this->info('     Lote TitanioPOS: ' . ($d['lot'] ?? '—'));
                $this->info('══════════════════════════════════════════════');
                Log::info("[inventario:migrar] VERIFICADA ref={$ref} insertados={$d['insertados']}");
                return self::SUCCESS;
            }

            $this->error('══════════════════════════════════════════════');
            $this->error('  ✗ LA CARGA NO QUEDÓ COMPLETA');
            $this->error('     Estado TitanioPOS: ' . ($d['titanio_status'] ?? '?'));
            $this->error("     Enviados: {$d['enviados']} · Insertados: {$d['insertados']} · Faltantes: {$d['faltantes']}");
            if ($d['error_message'] ?? null) $this->error('     ' . $d['error_message']);
            if ($d['errors'] ?? null) $this->line('     errores: ' . json_encode($d['errors']));
            $this->error('══════════════════════════════════════════════');
            Log::error("[inventario:migrar] INCOMPLETA ref={$ref}: " . json_encode($d));
            return self::FAILURE;
        }

        $this->warn('Se agotó la espera. La carga sigue en TitanioPOS; consulta el estado con:');
        $this->line("   php artisan inventario:migrar --referencia={$ref}");
        return self::FAILURE;
    }
}
