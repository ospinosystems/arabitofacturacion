<?php

namespace App\Console\Commands;

use App\Models\cierres;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Exporta para un rango de fechas el mapping
 *   "PUNTO-{admin_cierre_id}-{index}"  →  id_usuario REAL del cajero
 *   "PINPAD-{admin_cierre_id}-{index}" →  id_usuario REAL del cajero
 *
 * Reconstruye el mismo orden de concatenación que `SyncProgressController`
 * usa al armar los lotes del cierre admin (tipo_cierre=1):
 *   - itera `cierres` tipo_cierre=0 del mismo día (orden natural del get())
 *   - para cada uno toma `metodosPago->subtipo IN (pinpad, otros_puntos)`
 *   - concatena `metadatos['lotes']` (pinpad) y `metadatos['puntos']` (otros)
 *
 * Para cada item asigna el id_usuario del cierre del cajero al que pertenece.
 * Si el metadato YA trae id_usuario (cierres nuevos post-fix), lo respeta.
 *
 * Salida: JSON con `items: [{idinsucursal, id_usuario, fecha, monto, loteserial, banco}]`
 * que `arabitocentral` consume vía `puntos:backfill-id-usuario --json=...`.
 *
 * Uso:
 *   php artisan puntos:exportar-id-usuario-real --desde=2026-05-01 --hasta=2026-05-31
 *   php artisan puntos:exportar-id-usuario-real --desde=2026-05-01 --hasta=2026-05-31 --out=/tmp/idu.json
 */
class ExportarPuntosIdUsuarioRealCommand extends Command
{
    protected $signature = 'puntos:exportar-id-usuario-real
        {--desde= : Fecha inicio (YYYY-MM-DD) — requerido}
        {--hasta= : Fecha fin (YYYY-MM-DD) — requerido}
        {--out= : Ruta del JSON de salida (default: storage/app/puntos-id-usuario-real-{ts}.json)}';

    protected $description = 'Genera mapping idinsucursal → id_usuario REAL para PUNTO X y PINPAD en un rango (para backfill en arabitocentral).';

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

        $out = $this->option('out') ?: storage_path('app/puntos-id-usuario-real-'.date('Ymd_His').'.json');

        // Recolectar admin cierres del rango (solo los que se sincronizan)
        $cierresAdmin = cierres::whereBetween('fecha', [$desde, $hasta])
            ->where('tipo_cierre', 1)
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        $this->info("Rango: {$desde} → {$hasta}");
        $this->info("Cierres admin encontrados: ".$cierresAdmin->count());

        $items = [];
        $contadores = ['puntos' => 0, 'pinpad' => 0, 'sin_data' => 0];

        foreach ($cierresAdmin as $admin) {
            // Re-walk EXACTO al consolidado: cierres cajeros de la misma fecha
            // (orden natural del get() — mismo que se usa en PedidosController:3567).
            $cajeros = cierres::where('fecha', $admin->fecha)
                ->where('tipo_cierre', 0)
                ->with(['metodosPago' => function ($q) {
                    $q->where('tipo_pago', 2)
                      ->whereIn('subtipo', ['pinpad', 'otros_puntos']);
                }])
                ->get();

            if ($cajeros->isEmpty()) {
                $contadores['sin_data']++;
                continue;
            }

            $idxPinpad = 0;
            $idxOtros  = 0;

            foreach ($cajeros as $cajero) {
                $idUsuarioCajero = (int) $cajero->id_usuario;

                $reg_pinpad = $cajero->metodosPago->where('subtipo', 'pinpad')->first();
                if ($reg_pinpad) {
                    $meta = is_string($reg_pinpad->metadatos)
                        ? json_decode($reg_pinpad->metadatos, true)
                        : (array) ($reg_pinpad->metadatos ?? []);
                    $lotes = $meta['lotes'] ?? [];
                    foreach ($lotes as $lote) {
                        if (is_object($lote)) $lote = json_decode(json_encode($lote), true);
                        $items[] = [
                            'idinsucursal' => 'PINPAD-'.$admin->id.'-'.$idxPinpad,
                            'id_usuario'   => (int) ($lote['id_usuario'] ?? $idUsuarioCajero),
                            'fecha'        => (string) $admin->fecha,
                            'monto'        => (float) ($lote['monto_bs'] ?? $lote['monto'] ?? 0),
                            'loteserial'   => (string) ($lote['terminal'] ?? $lote['lote'] ?? ''),
                            'banco'        => (string) ($lote['banco_nombre'] ?? $lote['banco'] ?? ''),
                            'tipo'         => 'PINPAD',
                        ];
                        $idxPinpad++;
                        $contadores['pinpad']++;
                    }
                }

                $reg_otros = $cajero->metodosPago->where('subtipo', 'otros_puntos')->first();
                if ($reg_otros) {
                    $meta = is_string($reg_otros->metadatos)
                        ? json_decode($reg_otros->metadatos, true)
                        : (array) ($reg_otros->metadatos ?? []);
                    $puntos = $meta['puntos'] ?? [];
                    foreach ($puntos as $p) {
                        if (is_object($p)) $p = json_decode(json_encode($p), true);
                        $items[] = [
                            'idinsucursal' => 'PUNTO-'.$admin->id.'-'.$idxOtros,
                            'id_usuario'   => (int) ($p['id_usuario'] ?? $idUsuarioCajero),
                            'fecha'        => (string) $admin->fecha,
                            'monto'        => (float) ($p['monto_real'] ?? $p['monto'] ?? 0),
                            'loteserial'   => (string) ($p['descripcion'] ?? $p['lote'] ?? ''),
                            'banco'        => (string) ($p['banco'] ?? ''),
                            'tipo'         => 'PUNTO X',
                        ];
                        $idxOtros++;
                        $contadores['puntos']++;
                    }
                }
            }
        }

        $dir = dirname($out);
        if (! is_dir($dir)) @mkdir($dir, 0775, true);
        file_put_contents($out, json_encode([
            'desde' => $desde,
            'hasta' => $hasta,
            'generated_at' => now()->toIso8601String(),
            'count' => count($items),
            'items' => $items,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->newLine();
        $this->info('═══ Resumen ═══');
        $this->line('  · PUNTO X items   : '.$contadores['puntos']);
        $this->line('  · PINPAD items    : '.$contadores['pinpad']);
        $this->line('  · Cierres sin data: '.$contadores['sin_data']);
        $this->info("JSON escrito: {$out}  (".$this->fmtBytes(filesize($out)).')');
        $this->line('  Siguiente paso (en central):');
        $this->line('  php artisan puntos:backfill-id-usuario --json='.$out.' --aplicar');

        return self::SUCCESS;
    }

    private function fmtBytes(int $b): string
    {
        if ($b < 1024) return $b.' B';
        if ($b < 1024 * 1024) return round($b / 1024, 1).' KB';
        return round($b / 1024 / 1024, 2).' MB';
    }
}
