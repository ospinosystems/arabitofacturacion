<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Índices optimizados para sincronización con Arabito Central
 * 
 * Estos índices aceleran las consultas de:
 * - Búsqueda de registros pendientes de sincronización (sincronizado = 0)
 * - Ordenamiento por ID durante el chunking
 * - Filtrado por fecha para sincronización inicial
 */
class AddSyncIndexesForOptimization extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // ============ INVENTARIOS ============
        // Índice para buscar productos pendientes de sincronización
        if (!$this->indexExists('inventarios', 'idx_inventarios_push')) {
            Schema::table('inventarios', function (Blueprint $table) {
                $table->index('push', 'idx_inventarios_push');
            });
        }
        
        // Índice compuesto para sincronización con filtro de fecha
        if (!$this->indexExists('inventarios', 'idx_inventarios_push_updated')) {
            Schema::table('inventarios', function (Blueprint $table) {
                $table->index(['push', 'updated_at'], 'idx_inventarios_push_updated');
            });
        }

        // ============ PEDIDOS ============
        if (!$this->indexExists('pedidos', 'idx_pedidos_sincronizado')) {
            Schema::table('pedidos', function (Blueprint $table) {
                $table->index('sincronizado', 'idx_pedidos_sincronizado');
            });
        }
        
        if (!$this->indexExists('pedidos', 'idx_pedidos_sync_created')) {
            Schema::table('pedidos', function (Blueprint $table) {
                $table->index(['sincronizado', 'created_at'], 'idx_pedidos_sync_created');
            });
        }

        // ============ PAGO_PEDIDOS ============
        if (!$this->indexExists('pago_pedidos', 'idx_pago_pedidos_sincronizado')) {
            Schema::table('pago_pedidos', function (Blueprint $table) {
                $table->index('sincronizado', 'idx_pago_pedidos_sincronizado');
            });
        }
        
        if (!$this->indexExists('pago_pedidos', 'idx_pago_pedidos_sync_created')) {
            Schema::table('pago_pedidos', function (Blueprint $table) {
                $table->index(['sincronizado', 'created_at'], 'idx_pago_pedidos_sync_created');
            });
        }

        // ============ ITEMS_PEDIDOS ============
        if (!$this->indexExists('items_pedidos', 'idx_items_pedidos_sincronizado')) {
            Schema::table('items_pedidos', function (Blueprint $table) {
                $table->index('sincronizado', 'idx_items_pedidos_sincronizado');
            });
        }
        
        if (!$this->indexExists('items_pedidos', 'idx_items_pedidos_sync_created')) {
            Schema::table('items_pedidos', function (Blueprint $table) {
                $table->index(['sincronizado', 'created_at'], 'idx_items_pedidos_sync_created');
            });
        }
        
        // Índice para id_pedido (usado en joins)
        if (!$this->indexExists('items_pedidos', 'idx_items_pedidos_id_pedido')) {
            Schema::table('items_pedidos', function (Blueprint $table) {
                $table->index('id_pedido', 'idx_items_pedidos_id_pedido');
            });
        }

        // ============ CIERRES ============
        if (!$this->indexExists('cierres', 'idx_cierres_sincronizado')) {
            Schema::table('cierres', function (Blueprint $table) {
                $table->index('sincronizado', 'idx_cierres_sincronizado');
            });
        }
        
        if (!$this->indexExists('cierres', 'idx_cierres_sync_fecha')) {
            Schema::table('cierres', function (Blueprint $table) {
                $table->index(['sincronizado', 'fecha'], 'idx_cierres_sync_fecha');
            });
        }

        // ============ CIERRES_PUNTOS ============
        if (!$this->indexExists('cierres_puntos', 'idx_cierres_puntos_sincronizado')) {
            Schema::table('cierres_puntos', function (Blueprint $table) {
                $table->index('sincronizado', 'idx_cierres_puntos_sincronizado');
            });
        }
        
        if (!$this->indexExists('cierres_puntos', 'idx_cierres_puntos_sync_fecha')) {
            Schema::table('cierres_puntos', function (Blueprint $table) {
                $table->index(['sincronizado', 'fecha'], 'idx_cierres_puntos_sync_fecha');
            });
        }

        // ============ PAGOS_REFERENCIAS ============
        if (!$this->indexExists('pagos_referencias', 'idx_pagos_referencias_sincronizado')) {
            Schema::table('pagos_referencias', function (Blueprint $table) {
                $table->index('sincronizado', 'idx_pagos_referencias_sincronizado');
            });
        }
        
        if (!$this->indexExists('pagos_referencias', 'idx_pagos_referencias_sync_created')) {
            Schema::table('pagos_referencias', function (Blueprint $table) {
                $table->index(['sincronizado', 'created_at'], 'idx_pagos_referencias_sync_created');
            });
        }

        // ============ CAJAS ============
        if (!$this->indexExists('cajas', 'idx_cajas_sincronizado')) {
            Schema::table('cajas', function (Blueprint $table) {
                $table->index('sincronizado', 'idx_cajas_sincronizado');
            });
        }
        
        if (!$this->indexExists('cajas', 'idx_cajas_sync_fecha')) {
            Schema::table('cajas', function (Blueprint $table) {
                $table->index(['sincronizado', 'fecha'], 'idx_cajas_sync_fecha');
            });
        }

        // ============ MOVIMIENTOS_INVENTARIOUNITARIOS ============
        if (!$this->indexExists('movimientos_inventariounitarios', 'idx_movinv_sincronizado')) {
            Schema::table('movimientos_inventariounitarios', function (Blueprint $table) {
                $table->index('sincronizado', 'idx_movinv_sincronizado');
            });
        }
        
        if (!$this->indexExists('movimientos_inventariounitarios', 'idx_movinv_sync_created')) {
            Schema::table('movimientos_inventariounitarios', function (Blueprint $table) {
                $table->index(['sincronizado', 'created_at'], 'idx_movinv_sync_created');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Inventarios
        Schema::table('inventarios', function (Blueprint $table) {
            $table->dropIndex('idx_inventarios_push');
            $table->dropIndex('idx_inventarios_push_updated');
        });

        // Pedidos
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropIndex('idx_pedidos_sincronizado');
            $table->dropIndex('idx_pedidos_sync_created');
        });

        // Pago Pedidos
        Schema::table('pago_pedidos', function (Blueprint $table) {
            $table->dropIndex('idx_pago_pedidos_sincronizado');
            $table->dropIndex('idx_pago_pedidos_sync_created');
        });

        // Items Pedidos
        Schema::table('items_pedidos', function (Blueprint $table) {
            $table->dropIndex('idx_items_pedidos_sincronizado');
            $table->dropIndex('idx_items_pedidos_sync_created');
            $table->dropIndex('idx_items_pedidos_id_pedido');
        });

        // Cierres
        Schema::table('cierres', function (Blueprint $table) {
            $table->dropIndex('idx_cierres_sincronizado');
            $table->dropIndex('idx_cierres_sync_fecha');
        });

        // Cierres Puntos
        Schema::table('cierres_puntos', function (Blueprint $table) {
            $table->dropIndex('idx_cierres_puntos_sincronizado');
            $table->dropIndex('idx_cierres_puntos_sync_fecha');
        });

        // Pagos Referencias
        Schema::table('pagos_referencias', function (Blueprint $table) {
            $table->dropIndex('idx_pagos_referencias_sincronizado');
            $table->dropIndex('idx_pagos_referencias_sync_created');
        });

        // Cajas
        Schema::table('cajas', function (Blueprint $table) {
            $table->dropIndex('idx_cajas_sincronizado');
            $table->dropIndex('idx_cajas_sync_fecha');
        });

        // Movimientos
        Schema::table('movimientos_inventariounitarios', function (Blueprint $table) {
            $table->dropIndex('idx_movinv_sincronizado');
            $table->dropIndex('idx_movinv_sync_created');
        });
    }
    
    /**
     * Verifica si un índice existe en una tabla
     */
    private function indexExists($table, $indexName)
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
        return count($indexes) > 0;
    }
}
