<?php

namespace App\Console\Commands;

use App\Services\Cuadre\VentasDetalleExporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Genera el CSV detallado de ventas completo (una fila por línea de factura) desde la consola y lo deja en
 * storage/app/descargas_cuadre/zips, donde "Descargas completas" lo lista para bajarlo con usuario y clave.
 * La misma exportación existe en la web (Exportar CSV detallado) acotada por fechas; este comando evita los límites
 * de tiempo y memoria de PHP-FPM cuando el período completo es muy grande.
 */
class CuadreVentasDetalleCsv extends Command
{
    protected $signature = 'cuadre:ventas-detalle-csv
                            {--desde= : inicio del período (default: primera venta)}
                            {--hasta= : fin del período (default: última venta)}
                            {--maquina= : solo una máquina fiscal}
                            {--formato=excel : excel (separador ; y decimal coma) o plano (separador , y punto)}
                            {--salida= : ruta del CSV (default storage/app/descargas_cuadre/zips/ventas_detalle_<sucursal>_<desde>_a_<hasta>.csv)}';

    protected $description = 'Genera el CSV detallado de ventas (fecha, máquina fiscal, factura, producto, cantidad, precio, tasa) completo en consola.';

    public function handle(VentasDetalleExporter $exporter): int
    {
        ini_set('memory_limit', '-1');
        set_time_limit(0);
        $t0 = microtime(true);
        [$d1, $d2] = $exporter->rango();
        $desde = $this->option('desde') ?: $d1;
        $hasta = $this->option('hasta') ?: $d2;
        $maquina = $this->option('maquina') ?: null;
        $formato = $this->option('formato') === 'plano' ? 'plano' : 'excel';
        $empresa = DB::table('sucursals')->first();
        $salida = $this->option('salida') ?: storage_path(sprintf('app/descargas_cuadre/zips/ventas_detalle_%s_%s_a_%s%s%s.csv',
            strtolower((string) ($empresa->codigo ?? 'sucursal')), $desde, $hasta,
            $maquina ? '_' . preg_replace('/[^A-Za-z0-9]/', '', $maquina) : '', $formato === 'plano' ? '_plano' : ''));
        @mkdir(dirname($salida), 0775, true);
        $tmp = $salida . '.parcial';
        $out = fopen($tmp, 'w');
        $tot = $exporter->escribir($out, $exporter->filas($desde, $hasta, $maquina), $formato);
        fclose($out);
        rename($tmp, $salida);
        @chmod($salida, 0664);
        $this->info(sprintf('CSV: %s (%.1f MB) | %s -> %s | %d filas, %d facturas | USD %s | Bs %s | %.0f s | memoria pico %.0f MB',
            $salida, filesize($salida) / 1048576, $desde, $hasta, $tot['filas'], $tot['facturas'],
            number_format($tot['usd'], 2, ',', '.'), number_format($tot['bs'], 2, ',', '.'), microtime(true) - $t0, memory_get_peak_usage(true) / 1048576));
        return self::SUCCESS;
    }
}
