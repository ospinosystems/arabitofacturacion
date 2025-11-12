<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTransferenciaFieldsToTcdOrdenesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tcd_ordenes', function (Blueprint $table) {
            $table->unsignedBigInteger('sucursal_destino_id')->nullable()->after('fecha_despacho');
            $table->string('sucursal_destino_codigo')->nullable()->after('sucursal_destino_id');
            $table->timestamp('fecha_transferencia')->nullable()->after('sucursal_destino_codigo');
            $table->string('pedido_central_numero')->nullable()->after('fecha_transferencia');
            
            $table->index('sucursal_destino_id');
            $table->index('fecha_transferencia');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tcd_ordenes', function (Blueprint $table) {
            $table->dropIndex(['sucursal_destino_id']);
            $table->dropIndex(['fecha_transferencia']);
            $table->dropColumn([
                'sucursal_destino_id',
                'sucursal_destino_codigo',
                'fecha_transferencia',
                'pedido_central_numero'
            ]);
        });
    }
}
