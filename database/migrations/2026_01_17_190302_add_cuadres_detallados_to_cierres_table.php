<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCuadresDetalladosToCierresTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cierres', function (Blueprint $table) {
            // Solo agregar JSON para guardar cuadre_detallado completo
            // Contiene toda la información de cuadre por método de pago de forma flexible
            $table->json("cuadre_detallado")->nullable()->after("descuadre");
            $table->decimal('tasa', 10, 4)->nullable()->change();
            
            // También actualizar tasacop si existe
            $table->decimal('tasacop', 10, 4)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cierres', function (Blueprint $table) {
            $table->dropColumn("cuadre_detallado");
        });
    }
}
