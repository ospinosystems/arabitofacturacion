<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddDevolucionFieldsToPedidosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->unsignedInteger('isdevolucionOriginalid')->nullable()->default(null)->after('fiscal');
            $table->foreign('isdevolucionOriginalid')->references('id')->on('pedidos')->onUpdate('cascade')->onDelete('set null');
            $table->timestamp('fecha_factura')->nullable()->default(null)->after('isdevolucionOriginalid');
            $table->index('fecha_factura');
            $table->index('id_vendedor');
        });

        Schema::table('pago_pedidos', function (Blueprint $table) {
            // Eliminar restricción unique para permitir múltiples pagos del mismo tipo con diferentes monedas
            $table->dropUnique(['tipo', 'id_pedido']);
            $table->decimal('monto_original', 20, 4)->nullable();
            
            $table->enum('moneda', ['dolar', 'bs', 'peso', 'euro'])->nullable()->after('monto');
            $table->string('referencia')->nullable()->after('moneda');
        });

        DB::table('pedidos')
            ->whereNull('fecha_factura')
            ->update(['fecha_factura' => DB::raw('created_at')]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropForeign(['isdevolucionOriginalid']);
            $table->dropIndex(['fecha_factura']);
            $table->dropIndex(['id_vendedor']);
            $table->dropColumn(['isdevolucionOriginalid', 'fecha_factura']);
        });

        Schema::table('pago_pedidos', function (Blueprint $table) {
            $table->dropColumn(['moneda', 'referencia']);
            // Recrear restricción unique
            $table->unique(['tipo', 'id_pedido']);
        });
    }
}
