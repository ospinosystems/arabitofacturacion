<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Normaliza la tabla items_pedidos en un solo seeder, en este orden:
 *
 * 1. Reparar huérfanos: items cuyo id_producto no existe en inventarios se reemplazan
 *    por un producto del mismo precio_unitario (o monto/cantidad si falta precio).
 *
 * 2. Ajustar precio_unitario: precio_unitario = monto / cantidad donde cantidad != 0.
 *
 * 3. Ajustar tasa y monto_bs: según CSV de tasas BCV del día (created_at),
 *    monto_bs = tasa × monto. Usa database/data/Tasas_BCV_2023_2024_2025.csv por defecto.
 *
 * Uso:
 *   php artisan db:seed --class=NormalizarItemsPedidosSeeder
 *
 * Para indicar otra ruta del CSV de tasas (solo afecta el paso 3):
 *   php artisan tasas-bcv:seed "C:\ruta\Tasas BCV.csv"
 *   php artisan db:seed --class=NormalizarItemsPedidosSeeder
 * (o ejecutar antes solo el paso 3 con tasas-bcv:seed y luego este seeder para 1 y 2).
 */
class NormalizarItemsPedidosSeeder extends Seeder
{
    public function run(): void
    {
        $bar = $this->command ? $this->command->getOutput()->createProgressBar(3) : null;
        if ($bar) {
            $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
            $bar->setMessage('Iniciando...');
            $bar->start();
        }

        $this->repararHuerfanos($bar);
        if ($bar) {
            $bar->setMessage('Precio unitario');
            $bar->advance();
        }
        $this->ajustarPrecioUnitario();
        if ($bar) {
            $bar->setMessage('Tasa y monto_bs');
            $bar->advance();
        }
        $this->ajustarTasaYMontoBs($bar);
        if ($bar) {
            $bar->setMessage('Listo');
            $bar->finish();
            $this->command->newLine(2);
        }
    }

    /**
     * Items con id_producto que no existe en inventarios: reemplazar por un producto
     * del mismo precio (precio_unitario o monto/cantidad).
     */
    protected function repararHuerfanos($stepBar = null): void
    {
        $itemsHuerfanos = DB::table('items_pedidos')
            ->leftJoin('inventarios', 'inventarios.id', '=', 'items_pedidos.id_producto')
            ->whereNull('inventarios.id')
            ->select('items_pedidos.id', 'items_pedidos.id_producto', 'items_pedidos.precio_unitario', 'items_pedidos.monto', 'items_pedidos.cantidad')
            ->get();

        $total = $itemsHuerfanos->count();
        $bar = null;
        if ($this->command && $total > 0) {
            $bar = $this->command->getOutput()->createProgressBar($total);
            $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — Huérfanos');
            $bar->start();
        }

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
            if ($bar) {
                $bar->advance();
            }
        }

        if ($bar) {
            $bar->finish();
            $this->command->newLine();
        }
        if ($this->command) {
            $this->command->info('[1/3] Huérfanos (id_producto inexistente): ' . $itemsHuerfanos->count() . '. Reparados: ' . $reparados . '.');
            if ($sinPrecio > 0) {
                $this->command->warn("  Sin precio para buscar coincidencia: {$sinPrecio}.");
            }
            if ($sinCoincidencia > 0) {
                $this->command->warn("  Sin producto en inventarios con ese precio: {$sinCoincidencia}.");
            }
        }
    }

    /**
     * precio_unitario = monto / cantidad (solo donde cantidad no es 0 ni null).
     */
    protected function ajustarPrecioUnitario(): void
    {
        $updated = DB::table('items_pedidos')
            ->whereNotNull('cantidad')
            ->where('cantidad', '!=', 0)
            ->update([
                'precio_unitario' => DB::raw('ROUND(COALESCE(monto, 0) / cantidad, 4)'),
            ]);

        if ($this->command) {
            $this->command->info("[2/3] Precio unitario (monto/cantidad): {$updated} registros actualizados.");
        }
    }

    /**
     * Tasa y monto_bs según CSV BCV; monto_bs = tasa × monto.
     */
    protected function ajustarTasaYMontoBs($stepBar = null): void
    {
        $seederTasas = new TasasBcvItemsPedidosSeeder();
        $seederTasas->setCommand($this->command);
        $seederTasas->run($stepBar);
    }
}
