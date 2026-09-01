<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de cada sugerencia de ubicación y de lo que el pasillero realmente hizo.
 *
 * Es la pieza que convierte el motor de reglas en algo que puede aprender: cada vez
 * que alguien ignora la sugerencia y guarda en otro sitio, eso es una etiqueta. Con
 * unos miles de filas se pueden reajustar los pesos del scoring contra la realidad.
 */
class CreatePutawaySugerenciasTable extends Migration
{
    public function up()
    {
        Schema::create('putaway_sugerencias', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('inventario_id');
            $table->decimal('cantidad', 14, 4)->default(0);

            $table->enum('contexto', ['tcr', 'manual', 'api', 'reubicacion'])->default('manual');
            $table->string('referencia', 100)->nullable(); // id de asignación TCR, documento, etc.

            // Candidatas completas con su desglose de score, para poder explicar y auditar.
            $table->json('candidatas')->nullable();

            $table->unsignedBigInteger('warehouse_sugerido_id')->nullable();
            $table->decimal('score_sugerido', 10, 4)->nullable();

            $table->unsignedBigInteger('warehouse_elegido_id')->nullable();
            $table->decimal('score_elegido', 10, 4)->nullable();
            // Posición de la elegida dentro del ranking sugerido (1 = se aceptó la primera).
            $table->unsignedSmallInteger('posicion_elegida')->nullable();

            $table->boolean('fue_aceptada')->nullable();
            $table->string('motivo_override', 255)->nullable();

            // Snapshot de las señales usadas, para que el reentrenamiento no dependa
            // de que el ABC o el stock de hoy sigan igual.
            $table->char('clase_abc', 1)->nullable();
            $table->boolean('datos_fisicos_estimados')->default(true);

            $table->unsignedInteger('usuario_id')->nullable();
            $table->timestamp('decidido_en')->nullable();
            $table->timestamps();

            $table->index('inventario_id');
            $table->index('fue_aceptada');
            $table->index('contexto');
            $table->index(['contexto', 'referencia']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('putaway_sugerencias');
    }
}
