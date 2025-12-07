<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para agregar campos de control de sincronización
 * Permite rastrear qué registros han sido sincronizados a Central
 */
class AddSincronizadoFieldsForSyncTracking extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Campo sincronizado en movimientos_inventariounitarios
        if (!Schema::hasColumn('movimientos_inventariounitarios', 'sincronizado')) {
            Schema::table('movimientos_inventariounitarios', function (Blueprint $table) {
                $table->boolean('sincronizado')->default(0)->after('origen');
                $table->timestamp('sincronizado_at')->nullable()->after('sincronizado');
                $table->index('sincronizado');
            });
        }

        // Campo sincronizado en pedidos
        if (!Schema::hasColumn('pedidos', 'sincronizado')) {
            Schema::table('pedidos', function (Blueprint $table) {
                $table->boolean('sincronizado')->default(0)->after('export');
                $table->timestamp('sincronizado_at')->nullable()->after('sincronizado');
                $table->index('sincronizado');
            });
        }

        // Campo sincronizado en pago_pedidos
        if (!Schema::hasColumn('pago_pedidos', 'sincronizado')) {
            Schema::table('pago_pedidos', function (Blueprint $table) {
                $table->boolean('sincronizado')->default(0)->after('id_pedido');
                $table->timestamp('sincronizado_at')->nullable()->after('sincronizado');
                $table->index('sincronizado');
            });
        }

        // Campo sincronizado en items_pedidos
        if (!Schema::hasColumn('items_pedidos', 'sincronizado')) {
            Schema::table('items_pedidos', function (Blueprint $table) {
                $table->boolean('sincronizado')->default(0)->after('condicion');
                $table->timestamp('sincronizado_at')->nullable()->after('sincronizado');
                $table->index('sincronizado');
            });
        }

        // Campo sincronizado en cierres
        if (!Schema::hasColumn('cierres', 'sincronizado')) {
            Schema::table('cierres', function (Blueprint $table) {
                $table->boolean('sincronizado')->default(0)->after('push');
                $table->timestamp('sincronizado_at')->nullable()->after('sincronizado');
                $table->index('sincronizado');
            });
        }

        // Campo sincronizado en cierres_puntos
        if (!Schema::hasColumn('cierres_puntos', 'sincronizado')) {
            Schema::table('cierres_puntos', function (Blueprint $table) {
                $table->boolean('sincronizado')->default(0)->after('id_usuario');
                $table->timestamp('sincronizado_at')->nullable()->after('sincronizado');
                $table->index('sincronizado');
            });
        }

        // Campo sincronizado en pagos_referencias
        if (!Schema::hasColumn('pagos_referencias', 'sincronizado')) {
            Schema::table('pagos_referencias', function (Blueprint $table) {
                $table->boolean('sincronizado')->default(0)->after('id_pedido');
                $table->timestamp('sincronizado_at')->nullable()->after('sincronizado');
                $table->index('sincronizado');
            });
        }

        // Campo sincronizado en cajas
        if (!Schema::hasColumn('cajas', 'sincronizado')) {
            Schema::table('cajas', function (Blueprint $table) {
                $table->boolean('sincronizado')->default(0)->after('tipo');
                $table->timestamp('sincronizado_at')->nullable()->after('sincronizado');
                $table->index('sincronizado');
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
        $tables = [
            'movimientos_inventariounitarios',
            'pedidos',
            'pago_pedidos',
            'items_pedidos',
            'cierres',
            'cierres_puntos',
            'pagos_referencias',
            'cajas'
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    if (Schema::hasColumn($tableName, 'sincronizado')) {
                        $table->dropIndex([$tableName . '_sincronizado_index']);
                        $table->dropColumn(['sincronizado', 'sincronizado_at']);
                    }
                });
            }
        }
    }
}
