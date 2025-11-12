<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTcdOrdenItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tcd_orden_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tcd_orden_id');
            $table->unsignedBigInteger('inventario_id'); // Producto del inventario
            $table->string('codigo_barras')->nullable();
            $table->string('codigo_proveedor')->nullable();
            $table->string('descripcion');
            $table->decimal('precio', 10, 2)->nullable();
            $table->decimal('precio_base', 10, 2)->nullable();
            $table->string('ubicacion')->nullable(); // Ubicación del producto en el inventario
            $table->decimal('cantidad', 10, 4); // Cantidad solicitada
            $table->decimal('cantidad_descontada', 10, 4)->default(0); // Cantidad descontada del inventario
            $table->decimal('cantidad_bloqueada', 10, 4)->default(0); // Cantidad bloqueada esperando despacho
            $table->timestamps();
            
            $table->foreign('tcd_orden_id')->references('id')->on('tcd_ordenes')->onDelete('cascade');
            $table->foreign('inventario_id')->references('id')->on('inventarios')->onDelete('cascade');
            $table->index('tcd_orden_id');
            $table->index('inventario_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tcd_orden_items');
    }
}

