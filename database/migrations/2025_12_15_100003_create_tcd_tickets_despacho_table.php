<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTcdTicketsDespachoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tcd_tickets_despacho', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tcd_orden_id');
            $table->string('codigo_barras')->unique(); // Código de barras del ticket para escanear
            $table->enum('estado', ['generado', 'escaneado'])->default('generado');
            $table->unsignedBigInteger('usuario_despacho_id')->nullable(); // Usuario que escaneó el ticket
            $table->timestamp('fecha_escaneo')->nullable();
            $table->timestamps();
            
            $table->foreign('tcd_orden_id')->references('id')->on('tcd_ordenes')->onDelete('cascade');
            $table->index('codigo_barras');
            $table->index('tcd_orden_id');
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
        Schema::dropIfExists('tcd_tickets_despacho');
    }
}

