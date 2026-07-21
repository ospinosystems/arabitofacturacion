<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mercancía contenida dentro de un bulto. Se rastrea contra la línea original de la
 * transferencia (id_transferencia_item) para poder calcular lo empacado vs lo solicitado
 * (la diferencia = excluido / no encontrado).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('transferencia_bulto_items', function (Blueprint $table) {
            $table->increments('id');

            // id_bulto referencia transferencia_bultos.id (increments = int unsigned), por eso unsignedInteger.
            $table->unsignedInteger('id_bulto');
            $table->foreign('id_bulto')->references('id')->on('transferencia_bultos')
                ->onDelete('cascade')->onUpdate('cascade');

            $table->integer('id_transferencia_item')->index();
            $table->integer('id_producto')->index();
            $table->decimal('cantidad', 13, 3);

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('transferencia_bulto_items');
    }
};
