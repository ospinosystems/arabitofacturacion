<?php

namespace App\Console\Commands;

use App\Services\Cuadre\CuadreCsvReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Verificación independiente del cuadre: compara, grupo por grupo (fecha + máquina fiscal del archivo objetivo),
 * lo que realmente quedó en la BD con lo que pide el libro: facturas del rango presentes, suma en Bs, números
 * faltantes/repetidos, pedidos válidos fuera de todo rango y fechas de los pedidos frente a la fecha del grupo.
 * No usa el reporte del cuadre: lee la BD directamente.
 */
class CuadreVerificarCommand extends Command
{
    protected $signature = 'cuadre:verificar
                            {archivo : CSV/XLSX de montos objetivo (el mismo usado por el cuadre, p. ej. objetivo_anaco.csv)}
                            {--salida= : CSV con el detalle por grupo (default: storage/app/cuadre-completo/verificacion_<fecha>.csv)}
                            {--tolerancia-bs=2 : Diferencia absoluta aceptada por grupo}
                            {--tolerancia-pct=0.05 : Diferencia relativa aceptada por grupo (%)}
                            {--max-dias-atras=3 : Días de antigüedad tolerados en pedidos absorbidos de días sin objetivo}';

    protected $description = 'Verifica día por día y monto por monto el cuadre aplicado en la BD contra el archivo objetivo.';

    public function handle(CuadreCsvReader $reader): int
    {
        $path = $this->argument('archivo');
        if (!is_file($path)) {
            $this->error("Archivo no encontrado: {$path}");
            return Command::FAILURE;
        }
        $tolBs = (float) $this->option('tolerancia-bs');
        $tolPct = (float) $this->option('tolerancia-pct');
        $maxDiasAtras = (int) $this->option('max-dias-atras');

        $grupos = $reader->agregarPorDiaMaquina($reader->leerNormalizado($path));
        if (empty($grupos)) {
            $this->error('El archivo objetivo no produjo grupos.');
            return Command::FAILURE;
        }
        $this->info('Grupos objetivo (fecha + máquina): ' . count($grupos));

        // ── BD: todos los pedidos válidos con su monto en Bs ─────────────────────────────────
        $this->line('Leyendo pedidos válidos de la BD…');
        $porMaquina = [];   // maquina → nf → [id, fecha, bs]
        $duplicados = [];
        $totalValidos = 0;
        DB::table('pedidos')
            ->where('valido', true)
            ->whereNotNull('numero_factura')
            ->selectRaw("id, COALESCE(maquina_fiscal,'') as maquina, CAST(numero_factura AS UNSIGNED) as nf, DATE(COALESCE(fecha_factura, created_at)) as fecha")
            ->orderBy('id')
            ->chunk(5000, function ($rows) use (&$porMaquina, &$duplicados, &$totalValidos) {
                $ids = $rows->pluck('id')->all();
                $montos = DB::table('items_pedidos')->whereIn('id_pedido', $ids)
                    ->selectRaw('id_pedido, SUM(COALESCE(monto_bs, monto * COALESCE(NULLIF(tasa,0),1), 0)) as bs')
                    ->groupBy('id_pedido')->pluck('bs', 'id_pedido')->all();
                foreach ($rows as $r) {
                    $totalValidos++;
                    $m = mb_strtoupper(trim((string) $r->maquina));
                    $nf = (int) $r->nf;
                    if (isset($porMaquina[$m][$nf])) {
                        $duplicados[] = [$m, $nf, $porMaquina[$m][$nf]['id'], $r->id];
                        continue;
                    }
                    $porMaquina[$m][$nf] = ['id' => (int) $r->id, 'fecha' => (string) $r->fecha, 'bs' => (float) ($montos[$r->id] ?? 0), 'usado' => false];
                }
            });
        $this->info("Pedidos válidos en BD: {$totalValidos} | máquinas: " . implode(', ', array_keys($porMaquina)));

        // ── Comparación por grupo ────────────────────────────────────────────────────────────
        $salida = $this->option('salida') ?: storage_path('app/cuadre-completo/verificacion_' . date('Ymd_His') . '.csv');
        @mkdir(dirname($salida), 0777, true);
        $fh = fopen($salida, 'w');
        fputcsv($fh, ['fecha', 'maquina_fiscal', 'factura_inicio', 'factura_fin', 'facturas_objetivo', 'facturas_en_bd', 'faltantes', 'objetivo_bs', 'real_bs', 'diferencia_bs', 'diferencia_pct', 'fechas_pedidos', 'pedidos_fecha_distinta', 'estado']);

        $porMes = [];
        $problemas = [];
        $ok = 0;
        foreach ($grupos as $g) {
            $m = mb_strtoupper(trim((string) $g['maquina_fiscal']));
            $ini = (int) $g['factura_inicio'];
            $fin = (int) $g['factura_fin'];
            $objetivo = (float) $g['total_venta'];
            $real = 0.0;
            $enBd = 0;
            $faltantes = [];
            $fechas = [];
            $fechaDistinta = 0;
            $limiteAtras = date('Y-m-d', strtotime($g['fecha'] . " -{$maxDiasAtras} days"));
            for ($n = $ini; $n <= $fin; $n++) {
                $p = $porMaquina[$m][$n] ?? null;
                if ($p === null) {
                    $faltantes[] = $n;
                    continue;
                }
                $porMaquina[$m][$n]['usado'] = true;
                $enBd++;
                $real += $p['bs'];
                $fechas[$p['fecha']] = ($fechas[$p['fecha']] ?? 0) + 1;
                if ($p['fecha'] > $g['fecha'] || $p['fecha'] < $limiteAtras) {
                    $fechaDistinta++;
                }
            }
            $diff = $real - $objetivo;
            $pct = $objetivo != 0 ? abs($diff) / abs($objetivo) * 100 : (abs($diff) > 0 ? 100 : 0);
            $estado = 'ok';
            if ($enBd === 0) {
                $estado = 'sin_pedidos';
            } elseif (!empty($faltantes)) {
                $estado = 'incompleto';
            } elseif (abs($diff) > $tolBs && $pct > $tolPct) {
                $estado = 'monto_distinto';
            } elseif ($fechaDistinta > 0) {
                $estado = 'fecha_distinta';
            }
            if ($estado === 'ok') {
                $ok++;
            } else {
                $problemas[] = sprintf('%s | %-12s | %d-%d | obj %s | real %s | dif %s (%.3f%%) | faltan %d | fechas %s | %s',
                    $g['fecha'], $m, $ini, $fin, number_format($objetivo, 2, ',', '.'), number_format($real, 2, ',', '.'),
                    number_format($diff, 2, ',', '.'), $pct, count($faltantes), implode(' ', array_keys($fechas)), $estado);
            }
            ksort($fechas);
            fputcsv($fh, [
                $g['fecha'], $m, $ini, $fin, $fin - $ini + 1, $enBd,
                count($faltantes) > 20 ? count($faltantes) . ' (' . implode(' ', array_slice($faltantes, 0, 20)) . '…)' : implode(' ', $faltantes),
                round($objetivo, 2), round($real, 2), round($diff, 2), round($pct, 4),
                implode(' ', array_map(fn ($f, $c) => "$f:$c", array_keys($fechas), $fechas)), $fechaDistinta, $estado,
            ]);
            $mes = substr($g['fecha'], 0, 7);
            $porMes[$mes] = $porMes[$mes] ?? ['grupos' => 0, 'ok' => 0, 'obj' => 0.0, 'real' => 0.0, 'fact_obj' => 0, 'fact_bd' => 0];
            $porMes[$mes]['grupos']++;
            $porMes[$mes]['ok'] += $estado === 'ok' ? 1 : 0;
            $porMes[$mes]['obj'] += $objetivo;
            $porMes[$mes]['real'] += $real;
            $porMes[$mes]['fact_obj'] += $fin - $ini + 1;
            $porMes[$mes]['fact_bd'] += $enBd;
        }

        // ── Pedidos válidos fuera de todo rango objetivo ─────────────────────────────────────
        $huerfanos = [];
        $huerfanosBs = 0.0;
        foreach ($porMaquina as $m => $nfs) {
            foreach ($nfs as $nf => $p) {
                if (!$p['usado']) {
                    $huerfanos[] = "$m #$nf ({$p['fecha']}, " . number_format($p['bs'], 2, ',', '.') . ' Bs)';
                    $huerfanosBs += $p['bs'];
                }
            }
        }
        if (!empty($huerfanos) || !empty($duplicados)) {
            fputcsv($fh, []);
            foreach ($huerfanos as $h) fputcsv($fh, ['FUERA_DE_RANGO', $h]);
            foreach ($duplicados as $d) fputcsv($fh, ['DUPLICADO', $d[0], $d[1], 'pedidos ' . $d[2] . ' y ' . $d[3]]);
        }
        fclose($fh);

        // ── Resumen ──────────────────────────────────────────────────────────────────────────
        ksort($porMes);
        $filas = [];
        foreach ($porMes as $mes => $d) {
            $diff = $d['real'] - $d['obj'];
            $filas[] = [$mes, $d['ok'] . '/' . $d['grupos'], $d['fact_bd'] . '/' . $d['fact_obj'],
                number_format($d['obj'], 2, ',', '.'), number_format($d['real'], 2, ',', '.'),
                number_format($diff, 2, ',', '.') . ' (' . number_format($d['obj'] != 0 ? $diff / $d['obj'] * 100 : 0, 3) . '%)'];
        }
        $this->table(['Mes', 'Grupos OK', 'Facturas BD/obj', 'Objetivo Bs', 'Real en BD Bs', 'Diferencia'], $filas);
        $this->info(sprintf('Grupos: %d | OK (todas las facturas presentes y monto dentro de ±%s Bs o ±%s%%): %d | con observaciones: %d', count($grupos), $tolBs, $tolPct, $ok, count($problemas)));
        if (!empty($problemas)) {
            $this->warn('Grupos con observaciones (primeros 60):');
            foreach (array_slice($problemas, 0, 60) as $p) $this->line('  ' . $p);
        }
        if (!empty($duplicados)) {
            $this->error('Números de factura repetidos por máquina: ' . count($duplicados));
        } else {
            $this->info('Sin números de factura repetidos por máquina.');
        }
        if (!empty($huerfanos)) {
            $this->warn(sprintf('Pedidos válidos fuera de todo rango objetivo: %d (%s Bs). Primeros: %s', count($huerfanos), number_format($huerfanosBs, 2, ',', '.'), implode('; ', array_slice($huerfanos, 0, 10))));
        } else {
            $this->info('Todos los pedidos válidos pertenecen a un rango del objetivo.');
        }
        $this->info('Detalle por grupo: ' . $salida);
        return (empty($problemas) && empty($duplicados) && empty($huerfanos)) ? Command::SUCCESS : Command::FAILURE;
    }
}
