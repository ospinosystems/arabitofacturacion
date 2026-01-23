<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migración para optimizar rendimiento de endpoints de cierre (Complementaria)
 * 
 * NOTA: La migración 2025_12_07_170000_add_optimization_indexes_for_cierre.php
 * ya agregó la mayoría de los índices necesarios. Esta migración solo agrega
 * índices adicionales que no fueron incluidos en la anterior.
 * 
 * Esta migración agrega:
 * - idx_pedidos_fecha_export: Para filtros de pedidos export por fecha
 * - idx_pago_pedidos_created: Para ordenamientos por fecha de creación
 */
class AddPerformanceIndexesForCierre extends Migration
{
    /**
     * Verifica si un índice existe en una tabla
     */
    private function indexExists($table, $indexName)
    {
        $indexes = collect(Schema::getConnection()->getDoctrineSchemaManager()->listTableIndexes($table));
        return $indexes->has($indexName);
    }

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Solo agregar índices que NO existen y que NO fueron agregados por la migración anterior
        
        Schema::table('pedidos', function (Blueprint $table) {
            // Índice compuesto para pedidos export por fecha:
            // WHERE fecha_factura LIKE '2026-01-20%' AND export = 1
            // Este NO existe en la migración anterior
            if (!$this->indexExists('pedidos', 'idx_pedidos_fecha_export')) {
                $table->index(['fecha_factura', 'export'], 'idx_pedidos_fecha_export');
            }
        });

        Schema::table('pago_pedidos', function (Blueprint $table) {
            // Índice para ordenamiento por fecha de creación (usado en reportes y detalles de pagos)
            // Este NO existe en la migración anterior
            if (!$this->indexExists('pago_pedidos', 'idx_pago_pedidos_created')) {
                $table->index('created_at', 'idx_pago_pedidos_created');
            }
        });

        // NOTA: Los siguientes índices YA EXISTEN en la migración 2025_12_07:
        // - idx_pedidos_estado (estado)
        // - idx_pedidos_export (export)
        // - idx_pedidos_cierre_principal (fecha_factura, estado, id_vendedor)
        // - idx_pedidos_created_at (created_at)
        // - idx_pagos_pedido_tipo (id_pedido, tipo)
        // - idx_pagos_tipo_monto (tipo, monto)
        // - idx_cierres_fecha_usuario (fecha, id_usuario)
        // - idx_cierres_tipo (tipo_cierre)
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if ($this->indexExists('pedidos', 'idx_pedidos_fecha_export')) {
                $table->dropIndex('idx_pedidos_fecha_export');
            }
        });

        Schema::table('pago_pedidos', function (Blueprint $table) {
            if ($this->indexExists('pago_pedidos', 'idx_pago_pedidos_created')) {
                $table->dropIndex('idx_pago_pedidos_created');
            }
        });
    }
}
