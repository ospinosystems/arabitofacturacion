<?php

namespace Database\Seeders;

use App\Models\WarehouseInventory;
use App\Models\WarehouseMovement;
use App\Models\inventario;
use App\Services\Wms\SlottingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Coloca stock de prueba en el almacén usando el propio motor de slotting.
 *
 * Sirve para dos cosas: dejar el WMS con datos para probar picking, conteo y TMS;
 * y de paso ejercitar el motor de sugerencia contra cientos de productos reales,
 * que es la mejor forma de ver si el scoring se comporta.
 *
 * A una parte de los productos se les asignan lotes con vencimiento escalonado,
 * para poder verificar que el picking FEFO saca primero lo que caduca antes.
 */
class StockDemoWmsSeeder extends Seeder
{
    /** Cuántos productos colocar. */
    private const PRODUCTOS = 400;

    /** Proporción de productos que llevan lote con vencimiento. */
    private const PROPORCION_CON_LOTE = 0.35;

    public function run()
    {
        if (DB::table('warehouses')->count() === 0) {
            $this->command->error('No hay ubicaciones. Ejecute primero LayoutAlmacenDemoSeeder.');
            return;
        }

        if (WarehouseInventory::count() > 0) {
            $this->command->warn('Ya hay stock cargado en ubicaciones. Seeder omitido.');
            return;
        }

        $this->command->info('Colocando stock de prueba con el motor de slotting...');

        $slotting = new SlottingService();

        // Se toman productos con clasificación ABC para que el slotting tenga señal,
        // mezclando las tres clases.
        $productos = inventario::query()
            ->join('producto_abc as abc', function ($j) {
                $j->on('abc.inventario_id', '=', 'inventarios.id')
                  ->where('abc.criterio', '=', config('wms.abc.criterio_slotting'));
            })
            ->where('inventarios.activo', 1)
            ->select('inventarios.*', 'abc.clase')
            ->orderBy('abc.ranking')
            ->limit(self::PRODUCTOS)
            ->get();

        if ($productos->isEmpty()) {
            $this->command->error('No hay productos clasificados. Ejecute primero: php artisan wms:abc-recalcular');
            return;
        }

        mt_srand(20260827); // determinista

        $colocados = 0;
        $conLote   = 0;
        $sinHueco  = 0;
        $lineas    = 0;

        foreach ($productos as $p) {
            // Cantidad plausible según rotación: los A se almacenan en más cantidad.
            $cantidad = [
                'A' => mt_rand(40, 200),
                'B' => mt_rand(15, 80),
                'C' => mt_rand(3, 25),
            ][$p->clase] ?? 20;

            $producto = inventario::find($p->id);
            $plan = $slotting->sugerirDistribucion($producto, $cantidad, ['max_ubicaciones' => 3]);

            if (empty($plan['asignaciones'])) {
                $sinHueco++;
                continue;
            }

            $usaLote = (mt_rand(0, 100) / 100) < self::PROPORCION_CON_LOTE;

            foreach ($plan['asignaciones'] as $i => $asig) {
                $lote = null;
                $vencimiento = null;

                if ($usaLote) {
                    // Vencimientos escalonados: algunos ya vencidos, otros próximos,
                    // otros lejanos. Es lo que hace verificable el FEFO.
                    $diasVence = [-20, 5, 15, 45, 120, 300, 540][mt_rand(0, 6)];
                    $lote = 'L' . str_pad((string) $p->id, 6, '0', STR_PAD_LEFT) . '-' . ($i + 1);
                    $vencimiento = now()->addDays($diasVence)->toDateString();
                    $conLote++;
                }

                $wi = WarehouseInventory::create([
                    'warehouse_id'      => $asig['warehouse_id'],
                    'inventario_id'     => $p->id,
                    'cantidad'          => $asig['cantidad'],
                    'cantidad_bloqueada' => 0,
                    'lote'              => $lote,
                    'fecha_vencimiento' => $vencimiento,
                    'fecha_entrada'     => now()->subDays(mt_rand(1, 180))->toDateString(),
                    'estado'            => 'disponible',
                    'observaciones'     => 'Stock de prueba (seeder WMS)',
                ]);

                WarehouseMovement::create([
                    'tipo'                 => 'entrada',
                    'inventario_id'        => $p->id,
                    'warehouse_origen_id'  => null,
                    'warehouse_destino_id' => $asig['warehouse_id'],
                    'cantidad'             => $asig['cantidad'],
                    'lote'                 => $lote,
                    'fecha_vencimiento'    => $vencimiento,
                    'usuario_id'           => null,
                    'documento_referencia' => 'SEED-WMS',
                    'observaciones'        => 'Carga inicial de prueba',
                    'fecha_movimiento'     => $wi->fecha_entrada,
                ]);

                $lineas++;
            }

            $colocados++;

            if ($colocados % 50 === 0) {
                $this->command->getOutput()->write('.');
            }
        }

        $this->command->newLine();
        $this->command->info("Productos colocados: {$colocados}");
        $this->command->line("  Líneas de inventario creadas: {$lineas}");
        $this->command->line("  Líneas con lote y vencimiento: {$conLote}");
        if ($sinHueco > 0) {
            $this->command->warn("  Sin ubicación disponible: {$sinHueco}");
        }
    }
}
