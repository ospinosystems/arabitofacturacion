<?php

namespace App\Console\Commands;

use App\Services\Wms\AbcClassificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class WmsAbcRecalcular extends Command
{
    protected $signature = 'wms:abc-recalcular
                            {--dias= : Días de historial a analizar (por defecto config wms.abc.dias_analisis)}
                            {--desde= : Fecha inicio YYYY-MM-DD (tiene prioridad sobre --dias)}
                            {--hasta= : Fecha fin YYYY-MM-DD}';

    protected $description = 'Recalcula la clasificación ABC del inventario (Pareto) desde la demanda real';

    public function handle()
    {
        $dias = $this->option('dias');

        // Se instancia a mano y no por inyección: --dias tiene que llegar al constructor.
        $servicio = new AbcClassificationService(
            $dias ? ['dias_analisis' => (int) $dias] : []
        );

        $hasta = $this->option('hasta') ? Carbon::parse($this->option('hasta')) : null;
        $desde = $this->option('desde') ? Carbon::parse($this->option('desde')) : null;

        $this->info('Recalculando clasificación ABC...');

        $resultado = $servicio->recalcular($desde, $hasta);

        if (isset($resultado['error'])) {
            $this->error($resultado['error']);
            return 1;
        }

        $this->line("Periodo: {$resultado['periodo'][0]} a {$resultado['periodo'][1]} ({$resultado['dias_analizados']} días)");
        $this->line("Productos con demanda: {$resultado['productos']}");
        $this->newLine();

        $filas = [];
        foreach ($resultado['resumen'] as $criterio => $r) {
            $filas[] = [
                $criterio,
                $r['A'] ?? 0,
                $r['B'] ?? 0,
                $r['C'] ?? 0,
                $r['total'] ?? 0,
                $r['cambios'] ?? 0,
            ];
        }

        $this->table(['Criterio', 'A', 'B', 'C', 'Total', 'Cambios de clase'], $filas);

        $criterioSlotting = config('wms.abc.criterio_slotting');
        $this->newLine();
        $this->line("Distribución del criterio usado para slotting ('{$criterioSlotting}'):");

        $dist = [];
        foreach ($servicio->distribucion($criterioSlotting) as $d) {
            $dist[] = [$d['clase'], $d['productos'], $d['productos_pct'] . '%', $d['participacion_pct'] . '%'];
        }
        $this->table(['Clase', 'Productos', '% del catálogo', '% de la actividad'], $dist);

        return 0;
    }
}
