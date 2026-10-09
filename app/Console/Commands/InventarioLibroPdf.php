<?php

namespace App\Console\Commands;

use App\Services\Inventario\LibroInventarioPdf;
use App\Services\Inventario\LibroInventarioService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Genera el libro de entradas y salidas de inventario completo en PDF desde la consola y lo deja en
 * storage/app/descargas_cuadre/zips, donde la página «Descargas completas» lo lista para bajarlo con usuario y clave.
 * Usa el mismo generador ligero que la web (LibroInventarioPdf); desde la web también sale cualquier período.
 */
class InventarioLibroPdf extends Command
{
    protected $signature = 'inventario:libro-pdf
                            {--desde= : inicio del período (default: primer documento)}
                            {--hasta= : fin del período (default: último documento)}
                            {--tipos=FACTURA,NOTA,TRANSFERENCIA,AJUSTE : tipos de entrada a considerar}
                            {--solo-con-movimientos : omitir los productos sin movimientos en el período}
                            {--salida= : ruta del PDF (default storage/app/descargas_cuadre/zips/libro_inventario_<sucursal>_<desde>_a_<hasta>.pdf)}';

    protected $description = 'Genera el libro de entradas y salidas de inventario completo en PDF y lo deja en Descargas completas.';

    public function handle(LibroInventarioService $svc, LibroInventarioPdf $generador): int
    {
        ini_set('memory_limit', '-1');
        set_time_limit(0);
        $tipos = array_values(array_filter(array_map('trim', explode(',', strtoupper((string) $this->option('tipos'))))));
        $t0 = microtime(true);
        $libro = $svc->construir($this->option('desde') ?: null, $this->option('hasta') ?: null, $tipos, null, false);
        $productos = $this->option('solo-con-movimientos') ? array_filter($libro['productos'], fn ($p) => $p['movs'] > 0) : $libro['productos'];
        $empresa = DB::table('sucursals')->first();
        $this->info(sprintf('Período %s → %s | %d productos | entradas: %s', $libro['desde'], $libro['hasta'], count($productos), implode(',', $libro['tipos'])));

        $pdf = $generador->generar($libro, array_values($productos), $empresa);
        $salida = $this->option('salida') ?: storage_path(sprintf('app/descargas_cuadre/zips/libro_inventario_%s_%s_a_%s%s.pdf',
            strtolower((string) ($empresa->codigo ?? 'sucursal')), $libro['desde'], $libro['hasta'], $libro['tipos'] != LibroInventarioService::TIPOS_DEFECTO ? '_' . strtolower(implode('-', $libro['tipos'])) : ''));
        @mkdir(dirname($salida), 0775, true);
        file_put_contents($salida, $pdf);
        @chmod($salida, 0664);
        $this->info(sprintf('PDF: %s (%.1f MB) en %.0f s | memoria pico %.0f MB', $salida, filesize($salida) / 1048576, microtime(true) - $t0, memory_get_peak_usage(true) / 1048576));
        return self::SUCCESS;
    }
}
