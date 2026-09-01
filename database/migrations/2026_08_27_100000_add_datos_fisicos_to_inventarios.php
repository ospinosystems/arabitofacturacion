<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datos físicos (cubicaje) del producto.
 *
 * Son la base de todo el WMS: sin peso ni volumen no hay slotting, ni control real
 * de capacidad de ubicación, ni planificación de carga en el TMS.
 *
 * `datos_fisicos_fuente` distingue lo estimado de lo medido. Mientras sea 'estimado'
 * las sugerencias de ubicación deben advertirlo: la lógica es válida, los números no.
 */
class AddDatosFisicosToInventarios extends Migration
{
    public function up()
    {
        Schema::table('inventarios', function (Blueprint $table) {
            // --- Unidad de venta ---
            $table->decimal('peso_kg', 12, 4)->nullable()->after('bulto');
            $table->decimal('largo_cm', 8, 2)->nullable()->after('peso_kg');
            $table->decimal('ancho_cm', 8, 2)->nullable()->after('largo_cm');
            $table->decimal('alto_cm', 8, 2)->nullable()->after('ancho_cm');
            // Volumen persistido para poder sumar/ordenar en SQL sin recalcular.
            $table->decimal('volumen_m3', 14, 8)->nullable()->after('alto_cm');

            // --- Bulto / caja ---
            // `bulto` ya existía como entero suelto; aquí se le da semántica y peso/volumen propios.
            $table->unsignedInteger('unidades_por_bulto')->nullable()->after('volumen_m3');
            $table->decimal('peso_bulto_kg', 12, 4)->nullable()->after('unidades_por_bulto');
            $table->decimal('volumen_bulto_m3', 14, 8)->nullable()->after('peso_bulto_kg');

            // --- Paletizado ---
            $table->unsignedInteger('bultos_por_capa')->nullable()->after('volumen_bulto_m3');
            $table->unsignedInteger('capas_por_paleta')->nullable()->after('bultos_por_capa');

            // --- Restricciones de manejo ---
            $table->boolean('apilable')->default(true)->after('capas_por_paleta');
            $table->unsignedSmallInteger('max_apilamiento')->nullable()->after('apilable');
            $table->boolean('fragil')->default(false)->after('max_apilamiento');
            $table->boolean('requiere_refrigeracion')->default(false)->after('fragil');
            $table->boolean('peligroso')->default(false)->after('requiere_refrigeracion');

            // --- Trazabilidad del dato físico ---
            $table->enum('datos_fisicos_fuente', ['estimado', 'medido', 'proveedor'])
                  ->default('estimado')->after('peligroso');
            $table->timestamp('datos_fisicos_medido_en')->nullable()->after('datos_fisicos_fuente');

            $table->index('requiere_refrigeracion');
            $table->index('datos_fisicos_fuente');
        });
    }

    public function down()
    {
        Schema::table('inventarios', function (Blueprint $table) {
            $table->dropIndex(['requiere_refrigeracion']);
            $table->dropIndex(['datos_fisicos_fuente']);
            $table->dropColumn([
                'peso_kg', 'largo_cm', 'ancho_cm', 'alto_cm', 'volumen_m3',
                'unidades_por_bulto', 'peso_bulto_kg', 'volumen_bulto_m3',
                'bultos_por_capa', 'capas_por_paleta',
                'apilable', 'max_apilamiento', 'fragil', 'requiere_refrigeracion', 'peligroso',
                'datos_fisicos_fuente', 'datos_fisicos_medido_en',
            ]);
        });
    }
}
