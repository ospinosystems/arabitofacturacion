<?php

namespace App\Console\Commands;

use App\Services\Inventario\LibroInventarioService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Genera el libro de entradas y salidas de inventario completo en PDF desde la consola (PHP-FPM tiene 512 MB fijos y
 * DomPDF no cabe con miles de productos) y lo deja en storage/app/descargas_cuadre/zips, donde la página
 * «Descargas completas» lo lista para bajarlo con usuario y clave.
 */
class InventarioLibroPdf extends Command
{
    protected $signature = 'inventario:libro-pdf
                            {--desde= : inicio del período (default: primer documento)}
                            {--hasta= : fin del período (default: último documento)}
                            {--tipos=FACTURA : tipos de entrada: FACTURA,NOTA,TRANSFERENCIA}
                            {--solo-con-movimientos : omitir los productos sin movimientos en el período}
                            {--salida= : ruta del PDF (default storage/app/descargas_cuadre/zips/libro_inventario_<sucursal>_<desde>_a_<hasta>.pdf)}';

    protected $description = 'Genera el libro de entradas y salidas de inventario completo en PDF (consola, sin el límite de memoria web).';

    public function handle(LibroInventarioService $svc): int
    {
        ini_set('memory_limit', '-1');
        set_time_limit(0);
        $tipos = array_values(array_filter(array_map('trim', explode(',', strtoupper((string) $this->option('tipos'))))));
        $t0 = microtime(true);
        $libro = $svc->construir($this->option('desde') ?: null, $this->option('hasta') ?: null, $tipos, null, false);
        $productos = $this->option('solo-con-movimientos') ? array_filter($libro['productos'], fn ($p) => $p['movs'] > 0) : $libro['productos'];
        $empresa = DB::table('sucursals')->first();
        $this->info(sprintf('Período %s → %s | %d productos | entradas: %s', $libro['desde'], $libro['hasta'], count($productos), implode(',', $libro['tipos'])));

        $html = view('reportes.libro-inventario-pdf', compact('libro', 'productos', 'empresa'))->render();
        $pdf = Pdf::loadHTML($html)->setPaper('letter', 'landscape')
            ->setOptions(['isHtml5ParserEnabled' => true, 'defaultFont' => 'Helvetica', 'isFontSubsettingEnabled' => true, 'margin_left' => 8, 'margin_right' => 8, 'margin_top' => 8, 'margin_bottom' => 8]);
        $salida = $this->option('salida') ?: storage_path(sprintf('app/descargas_cuadre/zips/libro_inventario_%s_%s_a_%s%s.pdf',
            strtolower((string) ($empresa->codigo ?? 'sucursal')), $libro['desde'], $libro['hasta'], count($tipos) > 1 ? '_' . strtolower(implode('-', $libro['tipos'])) : ''));
        @mkdir(dirname($salida), 0775, true);
        file_put_contents($salida, $pdf->output());
        $this->info(sprintf('PDF: %s (%.1f MB) en %.0f s | memoria pico %.0f MB', $salida, filesize($salida) / 1048576, microtime(true) - $t0, memory_get_peak_usage(true) / 1048576));
        return self::SUCCESS;
    }
}
