<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Monto en Bolívares para conciliación.
     */
    public function up(): void
    {
        Schema::table('pago_pedidos', function (Blueprint $table) {
            $table->decimal('monto_bs', 18, 4)->nullable()->after('monto_original');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pago_pedidos', function (Blueprint $table) {
            $table->dropColumn('monto_bs');
        });
    }
};
