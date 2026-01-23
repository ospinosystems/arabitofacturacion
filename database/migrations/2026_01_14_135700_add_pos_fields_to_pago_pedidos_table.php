<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPosFieldsToPagoPedidosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pago_pedidos', function (Blueprint $table) {
            $table->string('pos_message', 50)->nullable()->after('referencia');
            $table->integer('pos_lote')->nullable()->after('pos_message');
            $table->string('pos_responsecode', 10)->nullable()->after('pos_lote');
            // Cambiado a 12 dígitos en total para permitir hasta mil millones (9 enteros + 2 decimales = 11, pero para estar más seguros usamos 12)
            $table->decimal('pos_amount', 12, 2)->nullable()->after('pos_responsecode');
            $table->string('pos_terminal', 20)->nullable()->after('pos_amount');
            $table->text('pos_json_response')->nullable()->after('pos_terminal');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pago_pedidos', function (Blueprint $table) {
            $table->dropColumn([
                'pos_message',
                'pos_lote',
                'pos_responsecode',
                'pos_amount',
                'pos_terminal',
                'pos_json_response'
            ]);
        });
    }
}
