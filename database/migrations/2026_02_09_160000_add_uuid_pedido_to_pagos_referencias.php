<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUuidPedidoToPagosReferencias extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pagos_referencias', function (Blueprint $table) {
            $table->dropForeign(['id_pedido']);
        });

        Schema::table('pagos_referencias', function (Blueprint $table) {
            $table->integer('id_pedido')->unsigned()->nullable()->change();
            $table->string('uuid_pedido', 36)->nullable()->after('id_pedido');
        });

        Schema::table('pagos_referencias', function (Blueprint $table) {
            $table->foreign('id_pedido')->references('id')->on('pedidos')
                ->onDelete('cascade')
                ->onUpdate('cascade');
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
            $table->dropForeign(['id_pedido']);
        });

        Schema::table('pagos_referencias', function (Blueprint $table) {
            $table->integer('id_pedido')->unsigned()->nullable(false)->change();
            $table->dropColumn('uuid_pedido');
        });

        Schema::table('pagos_referencias', function (Blueprint $table) {
            $table->foreign('id_pedido')->references('id')->on('pedidos')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }
}
