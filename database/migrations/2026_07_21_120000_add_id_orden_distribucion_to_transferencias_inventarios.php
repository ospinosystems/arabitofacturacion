<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan B (redistribución → orden editable): guarda en la orden de despacho local
 * el id de la orden de redistribución de central de la que nació, para poder
 * marcar esa orden "En Tránsito" al dar salida y no volver a mostrarla como premonta.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('transferencias_inventarios', 'id_orden_distribucion')) {
            Schema::table('transferencias_inventarios', function (Blueprint $table) {
                $table->unsignedInteger('id_orden_distribucion')->nullable()->after('id_transferencia_central');
                $table->index('id_orden_distribucion');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('transferencias_inventarios', 'id_orden_distribucion')) {
            Schema::table('transferencias_inventarios', function (Blueprint $table) {
                $table->dropIndex(['id_orden_distribucion']);
                $table->dropColumn('id_orden_distribucion');
            });
        }
    }
};
