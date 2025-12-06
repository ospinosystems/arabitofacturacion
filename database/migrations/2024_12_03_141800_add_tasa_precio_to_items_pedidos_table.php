<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddTasaPrecioToItemsPedidosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('items_pedidos', function (Blueprint $table) {
            $table->decimal('tasa', 15, 4)->nullable()->default(null)->after('monto');
            $table->decimal('tasa_cop', 15, 4)->nullable()->default(null)->after('tasa');
            $table->decimal('precio_unitario', 15, 4)->nullable()->default(null)->after('tasa_cop');
        });

        // Poblar los campos para items existentes
        $this->poblarDatosExistentes();
    }

    /**
     * Poblar precio_unitario y tasa para items_pedidos existentes
     */
    private function poblarDatosExistentes()
    {
        // Obtener la tasa actual de Bs de la tabla moneda
        $tasaActual = DB::table('monedas')
            ->where('tipo', 1)
            ->orderBy('id', 'desc')
            ->value('valor') ?? 1;
            
        // Obtener la tasa actual de COP de la tabla moneda
        $tasaCopActual = DB::table('monedas')
            ->where('tipo', 2)
            ->orderBy('id', 'desc')
            ->value('valor') ?? 1;

        // Actualizar precio_unitario con el precio del producto relacionado
        // Solo para items que no tienen precio_unitario asignado
        DB::statement("
            UPDATE items_pedidos ip
            INNER JOIN inventarios i ON ip.id_producto = i.id
            SET ip.precio_unitario = i.precio
            WHERE ip.precio_unitario IS NULL
        ");

        // Actualizar tasa con la tasa actual para todos los items que no la tienen
        DB::table('items_pedidos')
            ->whereNull('tasa')
            ->update(['tasa' => $tasaActual]);
            
        // Actualizar tasa_cop con la tasa actual para todos los items que no la tienen
        DB::table('items_pedidos')
            ->whereNull('tasa_cop')
            ->update(['tasa_cop' => $tasaCopActual]);

        // Log para referencia
        $itemsActualizados = DB::table('items_pedidos')
            ->whereNotNull('precio_unitario')
            ->whereNotNull('tasa')
            ->count();
        
        \Log::info("Migración: Se actualizaron {$itemsActualizados} items_pedidos con precio_unitario, tasa={$tasaActual} y tasa_cop={$tasaCopActual}");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('items_pedidos', function (Blueprint $table) {
            $table->dropColumn(['tasa', 'tasa_cop', 'precio_unitario']);
        });
    }
}
