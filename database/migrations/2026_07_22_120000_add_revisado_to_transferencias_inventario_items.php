<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca de "revisado" por ítem en una orden en preparación (Plan B): el DICI imprime la
 * lista de picking, busca físicamente cada producto en almacén y va marcando lo revisado
 * (manual o automáticamente al editar la cantidad) para saber qué le falta antes de dar salida.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('transferencias_inventario_items', 'revisado')) {
            Schema::table('transferencias_inventario_items', function (Blueprint $table) {
                $table->boolean('revisado')->default(false)->after('cantidad_original_stock_inventario');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('transferencias_inventario_items', 'revisado')) {
            Schema::table('transferencias_inventario_items', function (Blueprint $table) {
                $table->dropColumn('revisado');
            });
        }
    }
};
