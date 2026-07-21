<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Índices para acelerar el módulo "Salud de Inventario" (analítica de ventas):
 * agregaciones de items_pedidos por rango de fecha y por producto, filtrando pedidos cobrados.
 * Con guardas de existencia para ser idempotente.
 */
return new class extends Migration
{
    private function tieneIndice(string $tabla, string $indice): bool
    {
        return count(DB::select("SHOW INDEX FROM `{$tabla}` WHERE Key_name = ?", [$indice])) > 0;
    }

    public function up(): void
    {
        if (!$this->tieneIndice('items_pedidos', 'items_pedidos_created_at_idx')) {
            DB::statement('CREATE INDEX items_pedidos_created_at_idx ON items_pedidos (created_at)');
        }
        if (!$this->tieneIndice('items_pedidos', 'items_pedidos_prod_created_idx')) {
            DB::statement('CREATE INDEX items_pedidos_prod_created_idx ON items_pedidos (id_producto, created_at)');
        }
        if (!$this->tieneIndice('pedidos', 'pedidos_estado_created_idx')) {
            DB::statement('CREATE INDEX pedidos_estado_created_idx ON pedidos (estado, created_at)');
        }
    }

    public function down(): void
    {
        foreach ([
            ['items_pedidos', 'items_pedidos_created_at_idx'],
            ['items_pedidos', 'items_pedidos_prod_created_idx'],
            ['pedidos', 'pedidos_estado_created_idx'],
        ] as [$tabla, $indice]) {
            if ($this->tieneIndice($tabla, $indice)) {
                DB::statement("DROP INDEX `{$indice}` ON `{$tabla}`");
            }
        }
    }
};
