<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Repara items_pedidos cuyo id_producto no existe en inventarios: busca en inventarios
 * un producto con el mismo precio_unitario y reemplaza id_producto por ese id.
 *
 * Uso:
 *   php artisan db:seed --class=RepararIdProductoItemsPedidosSeeder
 */
class RepararIdProductoItemsPedidosSeeder extends Seeder
{
    public function run(): void
    {
        $itemsHuerfanos = DB::table('items_pedidos')
            ->leftJoin('inventarios', 'inventarios.id', '=', 'items_pedidos.id_producto')
            ->whereNull('inventarios.id')
            ->select('items_pedidos.id', 'items_pedidos.id_producto', 'items_pedidos.precio_unitario', 'items_pedidos.monto', 'items_pedidos.cantidad')
            ->get();

        $reparados = 0;
        $sinPrecio = 0;
        $sinCoincidencia = 0;

        foreach ($itemsHuerfanos as $item) {
            $precio = $item->precio_unitario;
            if ($precio === null || $precio === '') {
                if ($item->cantidad != 0 && $item->cantidad !== null) {
                    $precio = round((float) $item->monto / (float) $item->cantidad, 4);
                }
            } else {
                $precio = round((float) $precio, 4);
            }

            if ($precio === null || $precio === '') {
                $sinPrecio++;
                continue;
            }

            $producto = DB::table('inventarios')
                ->whereRaw('ROUND(COALESCE(precio, 0), 4) = ?', [$precio])
                ->orderBy('id')
                ->first();

            if ($producto === null) {
                $sinCoincidencia++;
                continue;
            }

            DB::table('items_pedidos')->where('id', $item->id)->update(['id_producto' => $producto->id]);
            $reparados++;
        }

        if ($this->command) {
            $this->command->info("items_pedidos con id_producto inexistente: " . $itemsHuerfanos->count() . ".");
            $this->command->info("Reparados (id_producto actualizado por coincidencia de precio): {$reparados}.");
            if ($sinPrecio > 0) {
                $this->command->warn("Sin precio_unitario para buscar coincidencia: {$sinPrecio}.");
            }
            if ($sinCoincidencia > 0) {
                $this->command->warn("Sin producto en inventarios con ese precio: {$sinCoincidencia}.");
            }
        }
    }
}
