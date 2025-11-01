<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWarehouseInventoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('warehouse_inventory', function (Blueprint $table) {
            $table->id();
            
            // Relaciones
            $table->unsignedBigInteger('warehouse_id'); // Ubicación del almacén
            $table->unsignedInteger('inventario_id'); // Producto del inventario
            
            // Cantidad y detalles
            $table->decimal('cantidad', 10, 2)->default(0); // Cantidad en esta ubicación
            $table->string('lote')->nullable(); // Lote del producto
            $table->date('fecha_vencimiento')->nullable(); // Fecha de vencimiento
            $table->date('fecha_entrada'); // Fecha de entrada a esta ubicación
            
            // Estado del inventario en esta ubicación
            $table->enum('estado', ['disponible', 'reservado', 'bloqueado', 'dañado'])->default('disponible');
            $table->text('observaciones')->nullable();
            
            // Llaves foráneas
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('restrict');
            $table->foreign('inventario_id')->references('id')->on('inventarios')->onDelete('restrict');
            
            // Índices
            $table->index(['warehouse_id', 'inventario_id']);
            $table->index('inventario_id');
            $table->index('estado');
            $table->index('fecha_vencimiento');
            
            // Un producto puede estar en múltiples ubicaciones, pero con lotes diferentes
            // No ponemos unique porque un mismo producto puede tener varios lotes en la misma ubicación
            
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
        Schema::dropIfExists('warehouse_inventory');
    }
}
