<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWarehouseMovementsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('warehouse_movements', function (Blueprint $table) {
            $table->id();
            
            // Tipo de movimiento
            $table->enum('tipo', ['entrada', 'salida', 'transferencia', 'ajuste', 'recepcion'])->default('transferencia');
            
            // Producto
            $table->unsignedInteger('inventario_id');
            $table->foreign('inventario_id')->references('id')->on('inventarios')->onDelete('restrict');
            
            // Ubicaciones (origen y destino)
            $table->unsignedBigInteger('warehouse_origen_id')->nullable(); // Null si es entrada nueva
            $table->unsignedBigInteger('warehouse_destino_id')->nullable(); // Null si es salida
            
            $table->foreign('warehouse_origen_id')->references('id')->on('warehouses')->onDelete('restrict');
            $table->foreign('warehouse_destino_id')->references('id')->on('warehouses')->onDelete('restrict');
            
            // Cantidad y detalles
            $table->decimal('cantidad', 10, 2);
            $table->string('lote')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            
            // Información adicional
            $table->unsignedInteger('usuario_id')->nullable(); // Usuario que realizó el movimiento
            $table->foreign('usuario_id')->references('id')->on('users')->onDelete('set null');
            
            $table->string('documento_referencia')->nullable(); // Número de orden, factura, etc.
            $table->text('observaciones')->nullable();
            $table->datetime('fecha_movimiento')->useCurrent();
            
            // Índices
            $table->index('tipo');
            $table->index('inventario_id');
            $table->index('warehouse_origen_id');
            $table->index('warehouse_destino_id');
            $table->index('fecha_movimiento');
            
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
        Schema::dropIfExists('warehouse_movements');
    }
}
