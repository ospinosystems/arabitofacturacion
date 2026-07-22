<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vincula una orden TCD (warehouse) con la orden de redistribución de central de la que nació,
 * para que al transferirla a la sucursal se pueda marcar esa OD "En Tránsito" (premonta → despacho).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('tcd_ordenes', 'id_orden_distribucion')) {
            Schema::table('tcd_ordenes', function (Blueprint $table) {
                $table->unsignedInteger('id_orden_distribucion')->nullable()->after('estado');
                $table->index('id_orden_distribucion');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tcd_ordenes', 'id_orden_distribucion')) {
            Schema::table('tcd_ordenes', function (Blueprint $table) {
                $table->dropIndex(['id_orden_distribucion']);
                $table->dropColumn('id_orden_distribucion');
            });
        }
    }
};
