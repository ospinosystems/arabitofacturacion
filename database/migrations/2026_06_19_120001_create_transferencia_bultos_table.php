<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bulto (paquete físico) de una transferencia. Una transferencia puede tener varios bultos.
 * Al cerrarse se genera un código de barras único para la etiqueta. En el despacho se escanea
 * bulto por bulto y cada uno da salida a la mercancía que contiene.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('transferencia_bultos', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('id_transferencia');
            $table->foreign('id_transferencia')->references('id')->on('transferencias_inventarios')
                ->onDelete('cascade')->onUpdate('cascade');

            $table->integer('numero'); // correlativo por orden: 1..N
            $table->string('codigo_barras')->unique();

            $table->enum('estado', ['abierto', 'cerrado', 'despachado'])->default('abierto')->index();

            $table->integer('cerrado_por')->nullable();
            $table->timestamp('cerrado_at')->nullable();
            $table->integer('despachado_por')->nullable();
            $table->timestamp('despachado_at')->nullable();

            $table->timestamps();
            $table->index('id_transferencia');
        });
    }

    public function down()
    {
        Schema::dropIfExists('transferencia_bultos');
    }
};
