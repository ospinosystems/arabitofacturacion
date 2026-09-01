<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clasificación ABC (análisis de Pareto) del inventario.
 *
 * Se guarda una fila por producto y criterio. El criterio importa: un producto puede
 * ser A en valor (caro, poca venta) y C en popularidad (casi nunca se toca). Para
 * slotting manda la popularidad; para política de stock manda el valor.
 *
 *  - valor       : consumo valorizado (cantidad x costo). Clásico de Pareto/Dickie.
 *  - unidades    : volumen de salida en unidades.
 *  - popularidad : número de líneas de pedido (cuántas veces hay que ir a buscarlo).
 *  - combinado   : matriz de las tres, para decisión de ubicación.
 */
class CreateProductoAbcTable extends Migration
{
    public function up()
    {
        Schema::create('producto_abc', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('inventario_id');

            $table->enum('criterio', ['valor', 'unidades', 'popularidad', 'combinado']);

            $table->date('periodo_inicio');
            $table->date('periodo_fin');

            // Métricas crudas del periodo (se guardan las tres siempre, para poder auditar)
            $table->decimal('unidades', 18, 4)->default(0);
            $table->decimal('valor', 18, 4)->default(0);
            $table->unsignedInteger('lineas')->default(0);

            // Métrica efectivamente usada para ordenar según `criterio`
            $table->decimal('metrica', 18, 4)->default(0);

            $table->decimal('participacion_pct', 9, 6)->default(0);
            $table->decimal('acumulado_pct', 9, 6)->default(0);

            $table->char('clase', 1);
            $table->unsignedInteger('ranking')->default(0);

            $table->timestamp('calculado_en')->nullable();
            $table->timestamps();

            // Una clasificación vigente por producto y criterio.
            $table->unique(['inventario_id', 'criterio']);
            $table->index(['criterio', 'clase']);
            $table->index('clase');
        });

        // Historial: sólo se registra cuando la clase efectivamente cambia.
        // Sirve para detectar productos que se están volviendo A (hay que reubicarlos).
        Schema::create('producto_abc_historial', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('inventario_id');
            $table->enum('criterio', ['valor', 'unidades', 'popularidad', 'combinado']);
            $table->char('clase_anterior', 1)->nullable();
            $table->char('clase_nueva', 1);
            $table->decimal('metrica', 18, 4)->default(0);
            $table->date('periodo_inicio');
            $table->date('periodo_fin');
            $table->timestamp('calculado_en')->nullable();
            $table->timestamps();

            $table->index(['inventario_id', 'criterio']);
            $table->index('calculado_en');
        });
    }

    public function down()
    {
        Schema::dropIfExists('producto_abc_historial');
        Schema::dropIfExists('producto_abc');
    }
}
