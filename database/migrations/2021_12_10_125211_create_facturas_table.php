<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFacturasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('facturas', function (Blueprint $table) {
            $table->increments("id");

            $table->string("numfact");
            $table->string("descripcion");
            $table->integer("id_usuario");
            $table->integer("estatus");
            $table->integer("id_proveedor")->nullable(true);
            $table->integer("id_pedido_central")->nullable(true);
            $table->decimal("subtotal",10,4)->nullable(true);
            $table->decimal("descuento",10,4)->nullable(true);
            $table->decimal("monto_exento",10,4)->nullable(true);
            $table->decimal("monto_gravable",10,4)->nullable(true);
            $table->decimal("iva",10,4)->nullable(true);
            $table->decimal("monto",10,4)->nullable(true);
            $table->date("fechaemision")->nullable(true);
            $table->date("fechavencimiento")->nullable(true);
            $table->date("fecharecepcion")->nullable(true);
            $table->text("nota")->nullable(true);
            $table->string("numnota")->nullable(true);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('facturas');
    }
}
