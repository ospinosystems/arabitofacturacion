<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Índice para filtro por fecha_factura en pedidos
        Schema::table('pedidos', function (Blueprint $table) {
            $table->index('fecha_factura', 'idx_pedidos_fecha_factura');
        });

        // Índice compuesto para items_pedidos (producto + pedido)
        Schema::table('items_pedidos', function (Blueprint $table) {
            $table->index(['id_producto', 'id_pedido'], 'idx_items_pedidos_producto_pedido');
        });

        // Índice compuesto para pago_pedidos (pedido + tipo)
        Schema::table('pago_pedidos', function (Blueprint $table) {
            $table->index(['id_pedido', 'tipo'], 'idx_pago_pedidos_pedido_tipo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropIndex('idx_pedidos_fecha_factura');
        });

        Schema::table('items_pedidos', function (Blueprint $table) {
            $table->dropIndex('idx_items_pedidos_producto_pedido');
        });

        Schema::table('pago_pedidos', function (Blueprint $table) {
            $table->dropIndex('idx_pago_pedidos_pedido_tipo');
        });
    }
};
