<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tablas que necesitan el campo push para sincronización
     */
    protected $tablas = [
        'pedidos',
        'pago_pedidos',
        'items_pedidos',
        'cierres',
        'cierres_puntos',
        'pagos_referencias',
        'cajas',
        'movimientos',
        'movimientos_inventariounitarios',
        
    ];
    
    /**
     * Run the migrations.
     * Agregar campo push para control de sincronización
     */
    public function up()
    {
        foreach ($this->tablas as $tabla) {
            if (Schema::hasTable($tabla) && !Schema::hasColumn($tabla, 'push')) {
                Schema::table($tabla, function (Blueprint $table) {
                    $table->tinyInteger('push')->default(0)->after('id')
                          ->comment('0=pendiente sync, 1=sincronizado');
                    $table->index('push', 'idx_push');
                });
                DB::table($tabla)->update(['push' => 1]);
            }
        }
        
        // Inventario ya tiene el campo push, solo agregar índice si no existe
        if (Schema::hasTable('inventario') && Schema::hasColumn('inventario', 'push')) {
            Schema::table('inventario', function (Blueprint $table) {
                // Verificar si el índice ya existe antes de crearlo
                $sm = Schema::getConnection()->getDoctrineSchemaManager();
                $indexes = $sm->listTableIndexes('inventario');
                if (!isset($indexes['idx_push'])) {
                    $table->index('push', 'idx_push');
                }
            });
            DB::table('inventario')->update(['push' => 1]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        foreach ($this->tablas as $tabla) {
            if (Schema::hasTable($tabla) && Schema::hasColumn($tabla, 'push')) {
                Schema::table($tabla, function (Blueprint $table) use ($tabla) {
                    $table->dropIndex('idx_push');
                    $table->dropColumn('push');
                });
            }
        }
    }
};
