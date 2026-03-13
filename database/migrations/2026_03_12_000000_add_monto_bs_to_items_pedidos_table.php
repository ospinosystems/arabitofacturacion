<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Añade monto_bs: monto en Bolívares (tasa del día × monto).
     */
    public function up(): void
    {
        Schema::table('items_pedidos', function (Blueprint $table) {
            $table->decimal('monto_bs', 18, 4)->nullable()->default(null)->after('monto');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items_pedidos', function (Blueprint $table) {
            $table->dropColumn('monto_bs');
        });
    }
};
