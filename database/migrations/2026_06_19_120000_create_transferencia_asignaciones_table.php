<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asignación de una línea de transferencia a un pasillero (usuario tipo 8).
 * Es la "orden de recolección": qué producto/cantidad debe ir a buscar cada pasillero,
 * y cuánto realmente recolectó (cantidad_recolectada).
 *
 * Nota de tipos: transferencias_inventarios.id es int(11) SIGNED (no unsigned), por eso
 * la FK usa integer(); las demás referencias quedan como integer indexado sin FK.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('transferencia_asignaciones', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('id_transferencia');
            $table->foreign('id_transferencia')->references('id')->on('transferencias_inventarios')
                ->onDelete('cascade')->onUpdate('cascade');

            $table->integer('id_transferencia_item')->index();
            $table->integer('id_producto')->index();
            $table->integer('pasillero_id')->index();

            $table->decimal('cantidad', 13, 3);
            $table->decimal('cantidad_recolectada', 13, 3)->default(0);

            $table->enum('estado', ['pendiente', 'en_proceso', 'recolectada'])->default('pendiente')->index();
            $table->string('warehouse_codigo')->nullable();
            $table->text('observaciones')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('transferencia_asignaciones');
    }
};
