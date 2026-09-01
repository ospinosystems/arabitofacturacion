<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conteo cíclico POR UBICACIÓN.
 *
 * Distinto del inventario cíclico que ya existe en arabitocentral: aquél cuenta
 * productos contra el stock general; éste cuenta una ubicación física contra
 * warehouse_inventory, a ciegas, y es lo que mantiene honesto al WMS.
 *
 * La frecuencia se deriva del ABC: las A se cuentan seguido porque son las que
 * más se tocan y por tanto las que más se descuadran.
 */
class CreateConteosCiclicosTables extends Migration
{
    public function up()
    {
        Schema::create('conteos_ciclicos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->unique();

            $table->enum('tipo', ['abc', 'zona', 'ubicaciones', 'producto'])->default('abc');
            $table->enum('estado', ['planificado', 'en_proceso', 'contado', 'ajustado', 'cancelado'])
                  ->default('planificado');

            // Conteo ciego: el contador no ve la cantidad de sistema. Sin esto el
            // conteo se convierte en confirmar lo que dice la pantalla.
            $table->boolean('ciego')->default(true);
            // Recuento obligatorio cuando hay diferencia, antes de ajustar.
            $table->boolean('exige_recuento')->default(true);

            $table->char('criterio_abc', 1)->nullable();
            $table->string('zona', 50)->nullable();

            $table->unsignedInteger('usuario_creador_id')->nullable();
            $table->unsignedInteger('usuario_conteo_id')->nullable();

            $table->date('fecha_programada')->nullable();
            $table->timestamp('iniciado_en')->nullable();
            $table->timestamp('finalizado_en')->nullable();
            $table->timestamp('ajustado_en')->nullable();

            // Resumen (se recalcula al cerrar)
            $table->unsignedInteger('total_lineas')->default(0);
            $table->unsignedInteger('lineas_contadas')->default(0);
            $table->unsignedInteger('lineas_con_diferencia')->default(0);
            $table->decimal('valor_diferencia', 18, 4)->default(0);
            // Exactitud = líneas sin diferencia / líneas contadas. El KPI del WMS.
            $table->decimal('exactitud_pct', 7, 4)->nullable();

            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index('estado');
            $table->index('fecha_programada');
            $table->index('criterio_abc');
        });

        Schema::create('conteo_ciclico_detalles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conteo_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedInteger('inventario_id')->nullable();
            $table->string('lote')->nullable();

            // Congelado al generar el conteo, para que el movimiento posterior no lo altere.
            $table->decimal('cantidad_sistema', 14, 4)->default(0);
            $table->decimal('cantidad_contada', 14, 4)->nullable();
            $table->decimal('cantidad_recuento', 14, 4)->nullable();
            $table->decimal('diferencia', 14, 4)->nullable();
            $table->decimal('valor_diferencia', 18, 4)->nullable();

            $table->enum('estado', ['pendiente', 'contado', 'en_recuento', 'ajustado', 'omitido'])
                  ->default('pendiente');

            // Producto encontrado en una ubicación donde el sistema no lo tenía.
            $table->boolean('es_hallazgo')->default(false);

            $table->unsignedInteger('usuario_id')->nullable();
            $table->timestamp('contado_en')->nullable();
            $table->unsignedBigInteger('warehouse_movement_id')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->foreign('conteo_id')->references('id')->on('conteos_ciclicos')->onDelete('cascade');
            $table->index(['conteo_id', 'estado']);
            $table->index('warehouse_id');
            $table->index('inventario_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('conteo_ciclico_detalles');
        Schema::dropIfExists('conteos_ciclicos');
    }
}
