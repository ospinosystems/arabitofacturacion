<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePprEntregasTable extends Migration
{
    /**
     * PPR - Pendiente por Retirar. Registro de unidades entregadas por ítem de pedido.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ppr_entregas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_pedido');
            $table->unsignedInteger('id_item_pedido'); // items_pedidos.id
            $table->unsignedInteger('id_producto')->nullable(); // inventarios.id (referencia)
            $table->decimal('unidades_entregadas', 12, 4)->default(0);
            $table->unsignedInteger('id_usuario_portero')->nullable();
            $table->timestamps();

            $table->foreign('id_pedido')->references('id')->on('pedidos')->onDelete('cascade');
            $table->foreign('id_item_pedido')->references('id')->on('items_pedidos')->onDelete('cascade');
            $table->index(['id_pedido', 'id_item_pedido']);
            $table->index('id_usuario_portero');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ppr_entregas');
    }
}
