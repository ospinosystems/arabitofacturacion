<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePosOperacionesRechazadasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pos_operaciones_rechazadas', function (Blueprint $table) {
            $table->increments('id');
            
            // Campos principales de la respuesta POS
            $table->string('message', 50)->nullable();
            $table->string('reference', 20)->nullable();
            $table->string('ordernumber', 20)->nullable();
            $table->bigInteger('sequence')->nullable();
            $table->string('approval', 20)->nullable();
            $table->integer('lote')->nullable();
            $table->string('responsecode', 10)->nullable();
            $table->string('datetime', 50)->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('commerce', 100)->nullable();
            $table->integer('cardtype')->nullable();
            $table->string('authid', 20)->nullable();
            $table->string('cardNumber', 50)->nullable();
            $table->string('cardholderid', 20)->nullable();
            $table->string('idmerchant', 50)->nullable();
            $table->string('terminal', 20)->nullable();
            $table->string('bank', 10)->nullable();
            $table->string('bankmessage', 50)->nullable();
            $table->string('tvr', 50)->nullable();
            $table->string('arqc', 50)->nullable();
            $table->string('tsi', 50)->nullable();
            $table->string('na', 50)->nullable();
            $table->string('aid', 50)->nullable();
            
            // Respuesta completa en JSON
            $table->text('json_response')->nullable();
            
            // Información adicional
            $table->integer('id_pedido')->unsigned()->nullable();
            $table->integer('id_usuario')->unsigned()->nullable();
            $table->integer('id_sucursal')->unsigned()->nullable();
            
            $table->timestamps();
            
            // Índices para búsquedas
            $table->index('reference');
            $table->index('responsecode');
            $table->index('id_pedido');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pos_operaciones_rechazadas');
    }
}
