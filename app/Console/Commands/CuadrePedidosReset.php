<?php

namespace App\Console\Commands;

use App\Services\Cuadre\CuadreResetService;
use Illuminate\Console\Command;

/**
 * Resetea el cuadre diario (numero_factura, maquina_fiscal, valido) en un rango de fechas y
 * RESTAURA los ítems/pagos ajustados a partir de la auditoría `cuadre_ajustes`.
 */
class CuadrePedidosReset extends Command
{
    protected $signature = 'cuadre:pedidos-reset
                            {--fecha_desde= : Desde esta fecha (YYYY-MM-DD, inclusive)}
                            {--fecha_hasta= : Hasta esta fecha (YYYY-MM-DD, inclusive)}
                            {--si : No pedir confirmación}
                            {--dry-run : Solo contar, sin modificar}';

    protected $description = 'Resetea cuadre diario (restaurando ajustes auditados) para re-ejecutar cuadre:pedidos-diario.';

    public function handle(CuadreResetService $reset): int
    {
        $fechaDesde = $this->option('fecha_desde') ? trim((string) $this->option('fecha_desde')) : null;
        $fechaHasta = $this->option('fecha_hasta') ? trim((string) $this->option('fecha_hasta')) : null;
        $dryRun = (bool) $this->option('dry-run');

        $ids = $reset->idsEnRango($fechaDesde, $fechaHasta);
        $total = count($ids);

        if ($total === 0) {
            $this->info('No hay pedidos válidos que resetear.');
            return Command::SUCCESS;
        }

        $this->info('Se resetearán ' . $total . ' pedidos' . ($fechaDesde || $fechaHasta ? " (rango {$fechaDesde} → {$fechaHasta})" : ' (TODOS)') . '.');
        if ($dryRun) {
            $this->warn('Dry-run: no se modificará la BD.');
            return Command::SUCCESS;
        }

        if (!$this->option('si') && !$this->confirm('¿Continuar?', true)) {
            return Command::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $stats = $reset->resetear($ids, function ($hechos) use ($bar) {
            $bar->setProgress($hechos);
        });
        $bar->finish();
        $this->newLine();

        $this->info(sprintf(
            'Listo. Pedidos reseteados: %d | ajustes restaurados: %d | pagos creados por el cuadre eliminados: %d | ítems de ajuste antiguos borrados: %d',
            $stats['pedidos'], $stats['ajustes_revertidos'], $stats['pagos_eliminados'], $stats['items_legacy_borrados']
        ));
        return Command::SUCCESS;
    }
}
