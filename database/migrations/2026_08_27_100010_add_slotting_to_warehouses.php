<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Atributos de slotting de la ubicación.
 *
 * El código A1-15-3 identifica la ubicación pero no dice dónde está físicamente.
 * Sin coordenadas ni distancia al muelle no se puede optimizar recorrido ni decidir
 * qué producto merece las ubicaciones "buenas" (cerca, a la altura de las manos).
 */
class AddSlottingToWarehouses extends Migration
{
    public function up()
    {
        Schema::table('warehouses', function (Blueprint $table) {
            // --- Posición física ---
            $table->decimal('coord_x', 10, 2)->nullable()->after('zona');
            $table->decimal('coord_y', 10, 2)->nullable()->after('coord_x');
            $table->decimal('distancia_muelle_m', 10, 2)->nullable()->after('coord_y');

            // Ergonomía: el nivel del rack determina el esfuerzo de tomar el producto.
            // 'dorada' = zona de oro (a la altura de las manos, sin escalera).
            $table->enum('accesibilidad', ['suelo', 'dorada', 'media', 'altura'])
                  ->default('media')->after('distancia_muelle_m');

            // --- Clase de rotación destino de la ubicación (se cruza con el ABC del producto) ---
            $table->char('clase_abc', 1)->nullable()->after('accesibilidad');

            // --- Dimensiones útiles del hueco ---
            $table->decimal('alto_util_cm', 8, 2)->nullable()->after('capacidad_unidades');
            $table->decimal('ancho_util_cm', 8, 2)->nullable()->after('alto_util_cm');
            $table->decimal('profundidad_util_cm', 8, 2)->nullable()->after('ancho_util_cm');

            // --- Reglas de mezcla y compatibilidad ---
            $table->boolean('permite_mezcla_productos')->default(true)->after('profundidad_util_cm');
            $table->boolean('permite_mezcla_lotes')->default(true)->after('permite_mezcla_productos');
            $table->boolean('refrigerada')->default(false)->after('permite_mezcla_lotes');
            $table->boolean('admite_peligrosos')->default(false)->after('refrigerada');
            $table->boolean('bloqueada_para_putaway')->default(false)->after('admite_peligrosos');

            // Orden de recorrido del pasillero (menor = se visita antes).
            $table->unsignedInteger('prioridad_picking')->default(100)->after('bloqueada_para_putaway');

            $table->index('clase_abc');
            $table->index('refrigerada');
            $table->index('bloqueada_para_putaway');
            $table->index('prioridad_picking');
        });
    }

    public function down()
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropIndex(['clase_abc']);
            $table->dropIndex(['refrigerada']);
            $table->dropIndex(['bloqueada_para_putaway']);
            $table->dropIndex(['prioridad_picking']);
            $table->dropColumn([
                'coord_x', 'coord_y', 'distancia_muelle_m', 'accesibilidad', 'clase_abc',
                'alto_util_cm', 'ancho_util_cm', 'profundidad_util_cm',
                'permite_mezcla_productos', 'permite_mezcla_lotes', 'refrigerada',
                'admite_peligrosos', 'bloqueada_para_putaway', 'prioridad_picking',
            ]);
        });
    }
}
