<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldsToPagosReferenciasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pagos_referencias', function (Blueprint $table) {
            $table->timestamp('fecha_pago')->nullable()->after('monto');
            $table->string('banco_origen')->nullable()->after('fecha_pago');
            $table->decimal('monto_real', 15, 2)->nullable()->after('banco_origen');
            $table->string('cuenta_origen')->nullable()->after('monto_real');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pagos_referencias', function (Blueprint $table) {
            $table->dropColumn(['fecha_pago', 'banco_origen', 'monto_real', 'cuenta_origen']);
        });
    }
}
