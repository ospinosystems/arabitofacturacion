<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class IncreaseOrdernumberPosOperacionesRechazadas extends Migration
{
    /**
     * Run the migrations.
     * Corrige "Data too long for column 'ordernumber'" cuando el ordernumber
     * incluye sucursal-caja-uuid-monto (ej: mantecal-caja2-19fc4f14-...-716465-0).
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pos_operaciones_rechazadas', function (Blueprint $table) {
            $table->string('ordernumber', 120)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pos_operaciones_rechazadas', function (Blueprint $table) {
            $table->string('ordernumber', 20)->nullable()->change();
        });
    }
}
