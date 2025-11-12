<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTcdAsignacionesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tcd_asignaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tcd_orden_id');
            $table->unsignedBigInteger('tcd_orden_item_id');
            $table->unsignedBigInteger('pasillero_id'); // Usuario pasillero asignado
            $table->decimal('cantidad', 10, 4); // Cantidad asignada al pasillero
            $table->decimal('cantidad_procesada', 10, 4)->default(0); // Cantidad que el pasillero ya procesó
            $table->enum('estado', ['pendiente', 'en_proceso', 'completada'])->default('pendiente');
            $table->string('warehouse_codigo')->nullable(); // Código de ubicación cuando el pasillero la asigna
            $table->unsignedBigInteger('warehouse_id')->nullable(); // ID de ubicación
            $table->text('observaciones')->nullable();
            $table->timestamps();
            
            $table->foreign('tcd_orden_id')->references('id')->on('tcd_ordenes')->onDelete('cascade');
            $table->foreign('tcd_orden_item_id')->references('id')->on('tcd_orden_items')->onDelete('cascade');
            $table->index('tcd_orden_id');
            $table->index('pasillero_id');
            $table->index('estado');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tcd_asignaciones');
    }
}

