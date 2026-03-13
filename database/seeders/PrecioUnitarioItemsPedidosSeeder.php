<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Actualiza precio_unitario en items_pedidos: precio_unitario = monto / cantidad.
 * Solo actualiza registros donde cantidad no es 0 ni null (evita división por cero).
 *
 * Uso:
 *   php artisan db:seed --class=PrecioUnitarioItemsPedidosSeeder
 */
class PrecioUnitarioItemsPedidosSeeder extends Seeder
{
    public function run(): void
    {
        $updated = DB::table('items_pedidos')
            ->whereNotNull('cantidad')
            ->where('cantidad', '!=', 0)
            ->update([
                'precio_unitario' => DB::raw('ROUND(COALESCE(monto, 0) / cantidad, 4)'),
            ]);

        if ($this->command) {
            $this->command->info("items_pedidos: {$updated} registros actualizados (precio_unitario = monto / cantidad).");
        }
    }
}
