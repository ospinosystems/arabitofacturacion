<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FillMontoBs extends Command
{
    protected $signature = 'monto-bs:fill
                            {--all : Actualizar todos los items_pedidos (también los que ya tienen monto_bs)}';

    protected $description = 'Llena monto_bs en items_pedidos (monto × tasa) y actualiza pago_pedidos.monto_bs con la suma de ítems';

    public function handle(): int
    {
        $updateAll = $this->option('all');

        // 1) items_pedidos: monto_bs = monto * COALESCE(NULLIF(tasa, 0), 1)
        $countItems = DB::table('items_pedidos')
            ->when(! $updateAll, fn ($q) => $q->whereNull('monto_bs'))
            ->count();

        if ($countItems === 0 && ! $updateAll) {
            $this->info('No hay registros en items_pedidos con monto_bs NULL. Usa --all para recalcular todos.');
        } else {
            $updated = DB::table('items_pedidos')
                ->when(! $updateAll, fn ($q) => $q->whereNull('monto_bs'))
                ->update([
                    'monto_bs' => DB::raw('ROUND(COALESCE(monto, 0) * COALESCE(NULLIF(tasa, 0), 1), 4)'),
                ]);
            $this->info("items_pedidos: {$updated} registros actualizados (monto_bs = monto × tasa).");
        }

        // 2) pago_pedidos.monto_bs = suma de items_pedidos.monto_bs del pedido
        if (! Schema::hasColumn('pago_pedidos', 'monto_bs')) {
            $this->warn('La tabla pago_pedidos no tiene columna monto_bs. Omisión.');
            return Command::SUCCESS;
        }

        $updatedPagos = DB::update("
            UPDATE pago_pedidos p
            INNER JOIN (
                SELECT id_pedido, SUM(COALESCE(monto_bs, 0)) AS total_bs
                FROM items_pedidos
                GROUP BY id_pedido
            ) s ON s.id_pedido = p.id_pedido
            SET p.monto_bs = s.total_bs
        ");
        $this->info("pago_pedidos: monto_bs actualizado según suma de ítems (registros afectados: {$updatedPagos}).");

        return Command::SUCCESS;
    }
}
