<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Optimización de índices para cierre de caja
     */
    public function up()
    {
        // 1. Índices para PEDIDOS
        Schema::table('pedidos', function (Blueprint $table) {
            // Optimiza: where("fecha_factura", "LIKE", $fecha . "%")->where("estado",1)->whereIn("id_vendedor", ...)
            // Usamos fecha_factura(10) para el LIKE 'YYYY-MM-DD%'
            $table->index(['fecha_factura', 'estado', 'id_vendedor'], 'idx_pedidos_cierre_principal');
            
            // Optimiza: where("estado", 0)
            if (!collect(Schema::getConnection()->getDoctrineSchemaManager()->listTableIndexes('pedidos'))->has('idx_pedidos_estado')) {
                $table->index('estado', 'idx_pedidos_estado');
            }
            
            // Optimiza: where("created_at", "LIKE", $fecha . "%")
            $table->index('created_at', 'idx_pedidos_created_at');
            
            // Optimiza: where("export", 1)
            $table->index('export', 'idx_pedidos_export');
        });

        // 2. Índices para PAGO_PEDIDOS
        Schema::table('pago_pedidos', function (Blueprint $table) {
            // Optimiza: where("tipo", 6)->where("monto", "<>", 0)
            $table->index(['tipo', 'monto'], 'idx_pagos_tipo_monto');
            
            // Optimiza: whereIn("id_pedido", ...) y búsquedas por relación
            $table->index(['id_pedido', 'tipo'], 'idx_pagos_pedido_tipo');
            
            // Optimiza: where("cuenta", 0) -> abonos
            $table->index(['cuenta', 'monto'], 'idx_pagos_cuenta_monto');
            
            // Optimiza ordenamiento por id desc
            // (Ya suele tener índice primario, pero si se busca por pedido y ordena...)
        });

        // 3. Índices para ITEMS_PEDIDOS
        Schema::table('items_pedidos', function (Blueprint $table) {
            // Fundamental para: items_pedidos::whereIn("id_pedido", ...)
            if (!collect(Schema::getConnection()->getDoctrineSchemaManager()->listTableIndexes('items_pedidos'))->has('idx_items_id_pedido')) {
                $table->index('id_pedido', 'idx_items_id_pedido');
            }
        });

        // 4. Índices para CIERRES
        Schema::table('cierres', function (Blueprint $table) {
            // Optimiza: where("fecha", $fecha)->where("id_usuario", ...)
            $table->index(['fecha', 'id_usuario'], 'idx_cierres_fecha_usuario');
            
            // Optimiza: whereIn("id_usuario", ...)->orderBy("fecha", "desc")
            $table->index(['id_usuario', 'fecha'], 'idx_cierres_usuario_fecha');
            
            // Optimiza: filtros por tipo_cierre
            $table->index('tipo_cierre', 'idx_cierres_tipo');
        });

        // 5. Índices para CIERRES_PUNTOS
        Schema::table('cierres_puntos', function (Blueprint $table) {
            // Optimiza: whereIn("id_usuario", ...)->where("fecha", ...)
            $table->index(['fecha', 'id_usuario'], 'idx_cierrespuntos_fecha_usuario');
        });
        
        // 6. Índices para CAJAS
        Schema::table('cajas', function (Blueprint $table) {
            // Optimiza: where("estatus",0)
            $table->index('estatus', 'idx_cajas_estatus');
            
            // Optimiza: where("concepto", ...)->where("fecha", ...)
            $table->index(['fecha', 'concepto'], 'idx_cajas_fecha_concepto');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropIndex('idx_pedidos_cierre_principal');
            $table->dropIndex('idx_pedidos_estado');
            $table->dropIndex('idx_pedidos_created_at');
            $table->dropIndex('idx_pedidos_export');
        });

        Schema::table('pago_pedidos', function (Blueprint $table) {
            $table->dropIndex('idx_pagos_tipo_monto');
            $table->dropIndex('idx_pagos_pedido_tipo');
            $table->dropIndex('idx_pagos_cuenta_monto');
        });

        Schema::table('items_pedidos', function (Blueprint $table) {
            $table->dropIndex('idx_items_id_pedido');
        });

        Schema::table('cierres', function (Blueprint $table) {
            $table->dropIndex('idx_cierres_fecha_usuario');
            $table->dropIndex('idx_cierres_usuario_fecha');
            $table->dropIndex('idx_cierres_tipo');
        });

        Schema::table('cierres_puntos', function (Blueprint $table) {
            $table->dropIndex('idx_cierrespuntos_fecha_usuario');
        });
        
        Schema::table('cajas', function (Blueprint $table) {
            $table->dropIndex('idx_cajas_estatus');
            $table->dropIndex('idx_cajas_fecha_concepto');
        });
    }
};
