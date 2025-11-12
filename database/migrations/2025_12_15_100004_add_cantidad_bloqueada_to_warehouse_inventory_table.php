<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCantidadBloqueadaToWarehouseInventoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('warehouse_inventory', function (Blueprint $table) {
            $table->decimal('cantidad_bloqueada', 10, 4)->default(0)->after('cantidad');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('warehouse_inventory', function (Blueprint $table) {
            $table->dropColumn('cantidad_bloqueada');
        });
    }
}

