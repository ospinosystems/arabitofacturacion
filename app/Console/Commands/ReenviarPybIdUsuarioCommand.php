<?php

namespace App\Console\Commands;

use App\Http\Controllers\sendCentral;
use App\Models\cierres;
use App\Models\sucursal;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Reenvía SOLO `id_usuario` y `nombre_usuario` de los pyb PUNTO X / PINPAD ya
 * sincronizados, sin retransmitir el cierre completo.
 *
 * Reconstruye el mismo orden de concatenación con el que `SyncProgressController`
 * arma el sync de cierres consolidados (admin, tipo_cierre=1):
 *   - itera `cierres` tipo_cierre=0 del mismo día (orden natural del get())
 *   - para cada uno toma `metodosPago->subtipo IN (pinpad, otros_puntos)`
 *   - concatena `metadatos['lotes']` (pinpad) y `metadatos['puntos']` (otros)
 *
 * El `idinsucursal` resultante (`PUNTO-{admin_id}-{idx}` / `PINPAD-{admin_id}-{idx}`)
 * matchea con el que ya tienen los pyb en central. El endpoint en central
 * (`POST /sync/actualizar-pyb-usuarios`) hace `UPDATE` de SOLO esas 2 columnas
 * — sin tocar monto, banco, fecha, liquidaciones ni vínculos.
 *
 * Uso:
 *   php artisan puntos:reenviar-id-usuario --desde=2026-05-01 --hasta=2026-05-31         # dry-run (NO envía)
 *   php artisan puntos:reenviar-id-usuario --desde=2026-05-01 --hasta=2026-05-31 --aplicar
 */
class ReenviarPybIdUsuarioCommand extends Command
{
    protected $signature = 'puntos:reenviar-id-usuario
        {--desde= : Fecha inicio (YYYY-MM-DD) — requerido}
        {--hasta= : Fecha fin (YYYY-MM-DD) — requerido}
        {--aplicar : POST real al endpoint de central. Sin esto solo arma y reporta.}';

    protected $description = 'Reenvía id_usuario/nombre_usuario reales de cada cajero al pyb correspondiente en central (sin retransmitir cierres).';

    public function handle(): int
    {
        $desde = (string) $this->option('desde');
        $hasta = (string) $this->option('hasta');
        if (! $desde || ! $hasta) {
            $this->error('Faltan --desde y --hasta (YYYY-MM-DD).');
            return self::FAILURE;
        }
        try {
            $desde = Carbon::parse($desde)->format('Y-m-d');
            $hasta = Carbon::parse($hasta)->format('Y-m-d');
        } catch (\Throwable $e) {
            $this->error('Fechas inválidas: '.$e->getMessage());
            return self::FAILURE;
        }
        if ($desde > $hasta) [$desde, $hasta] = [$hasta, $desde];
        $aplicar = (bool) $this->option('aplicar');

        // Mapa id_usuario → nombre desde la tabla usuarios local
        $nombrePorId = DB::table('usuarios')->pluck('nombre', 'id')->all();

        // Cierres admin del rango (los únicos que se sincronizan)
        $cierresAdmin = cierres::whereBetween('fecha', [$desde, $hasta])
            ->where('tipo_cierre', 1)
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        $this->info("Rango: {$desde} → {$hasta}  ·  modo: ".($aplicar ? 'APLICAR' : 'DRY-RUN'));
        $this->info("Cierres admin encontrados: ".$cierresAdmin->count());

        $items = [];
        foreach ($cierresAdmin as $admin) {
            $cajeros = cierres::where('fecha', $admin->fecha)
                ->where('tipo_cierre', 0)
                ->with(['metodosPago' => function ($q) {
                    $q->where('tipo_pago', 2)
                      ->whereIn('subtipo', ['pinpad', 'otros_puntos']);
                }])
                ->get();
            if ($cajeros->isEmpty()) continue;

            $idxPinpad = 0;
            $idxOtros  = 0;
            foreach ($cajeros as $cajero) {
                $idUsuarioCajero = (int) $cajero->id_usuario;

                $reg_pinpad = $cajero->metodosPago->where('subtipo', 'pinpad')->first();
                if ($reg_pinpad) {
                    $meta = is_string($reg_pinpad->metadatos)
                        ? json_decode($reg_pinpad->metadatos, true)
                        : (array) ($reg_pinpad->metadatos ?? []);
                    foreach (($meta['lotes'] ?? []) as $lote) {
                        if (is_object($lote)) $lote = json_decode(json_encode($lote), true);
                        $idu = (int) ($lote['id_usuario'] ?? $idUsuarioCajero);
                        $items[] = [
                            'idinsucursal'   => 'PINPAD-'.$admin->id.'-'.$idxPinpad,
                            'id_usuario'     => $idu,
                            'nombre_usuario' => $nombrePorId[$idu] ?? null,
                        ];
                        $idxPinpad++;
                    }
                }

                $reg_otros = $cajero->metodosPago->where('subtipo', 'otros_puntos')->first();
                if ($reg_otros) {
                    $meta = is_string($reg_otros->metadatos)
                        ? json_decode($reg_otros->metadatos, true)
                        : (array) ($reg_otros->metadatos ?? []);
                    foreach (($meta['puntos'] ?? []) as $p) {
                        if (is_object($p)) $p = json_decode(json_encode($p), true);
                        $idu = (int) ($p['id_usuario'] ?? $idUsuarioCajero);
                        $items[] = [
                            'idinsucursal'   => 'PUNTO-'.$admin->id.'-'.$idxOtros,
                            'id_usuario'     => $idu,
                            'nombre_usuario' => $nombrePorId[$idu] ?? null,
                        ];
                        $idxOtros++;
                    }
                }
            }
        }

        $this->info('Items armados: '.count($items));
        if (empty($items)) {
            $this->warn('Nada que enviar.');
            return self::SUCCESS;
        }

        // Muestra resumen por cajero
        $porUsuario = [];
        foreach ($items as $it) {
            $key = $it['id_usuario'].' · '.($it['nombre_usuario'] ?? '?');
            $porUsuario[$key] = ($porUsuario[$key] ?? 0) + 1;
        }
        $this->newLine();
        $this->line('Distribución por cajero:');
        foreach ($porUsuario as $k => $n) $this->line("  · {$k}: {$n} pyb");
        $this->newLine();

        if (! $aplicar) {
            $this->warn('DRY-RUN. Agregá --aplicar para enviar al endpoint de central.');
            return self::SUCCESS;
        }

        $sc = new sendCentral();
        $url = rtrim($sc->path(), '/').'/api/sync/actualizar-pyb-usuarios';
        $codigo = sucursal::first()->codigo;
        $apiKey = $sc->getCentralApiKey();
        $headers = $apiKey ? ['X-Sucursal-Api-Key' => $apiKey] : [];

        $this->info("POST {$url}  (codigo_origen={$codigo})");

        $response = Http::timeout(180)
            ->withHeaders($headers)
            ->retry(2, 1000)
            ->post($url, [
                'codigo_origen' => $codigo,
                'items'         => $items,
            ]);

        if (! $response->ok()) {
            $this->error('HTTP '.$response->status().': '.$response->body());
            return self::FAILURE;
        }

        $r = $response->json() ?: [];
        if (empty($r['estado'])) {
            $this->error('Central respondió error: '.($r['mensaje'] ?? json_encode($r)));
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('═══ Respuesta de central ═══');
        $this->line('  · Actualizados   : '.($r['actualizados'] ?? 0));
        $this->line('  · Ya correctos   : '.($r['ya_correctos'] ?? 0));
        $this->line('  · No encontrados : '.($r['no_encontrados'] ?? 0));
        if (! empty($r['errores'])) {
            $this->warn('  · Errores        : '.count($r['errores']));
            foreach (array_slice($r['errores'], 0, 5) as $e) $this->line('     - '.$e);
        }

        return self::SUCCESS;
    }
}
