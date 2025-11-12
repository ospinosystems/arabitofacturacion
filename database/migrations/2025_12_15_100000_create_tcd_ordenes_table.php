<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTcdOrdenesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tcd_ordenes', function (Blueprint $table) {
            $table->id();
            $table->string('numero_orden')->unique(); // TCD #xxxx
            $table->unsignedBigInteger('chequeador_id'); // Usuario que creó la orden
            $table->enum('estado', ['borrador', 'asignada', 'en_proceso', 'completada', 'despachada', 'cancelada'])->default('borrador');
            $table->text('observaciones')->nullable();
            $table->timestamp('fecha_despacho')->nullable(); // Fecha cuando se escaneó el ticket de despacho
            $table->timestamps();
            
            $table->index('numero_orden');
            $table->index('chequeador_id');
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
        Schema::dropIfExists('tcd_ordenes');
    }
}

