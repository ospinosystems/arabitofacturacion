<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTcrAsignacionesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tcr_asignaciones', function (Blueprint $table) {
            $table->id();
            
            // Información del pedido de arabitocentral
            $table->integer('pedido_central_id'); // ID del pedido en arabitocentral
            $table->integer('item_pedido_id'); // ID del item dentro del pedido
            
            // Información del producto
            $table->string('codigo_barras')->nullable();
            $table->string('codigo_proveedor')->nullable();
            $table->string('descripcion');
            $table->decimal('cantidad', 10, 4); // Cantidad total del producto
            $table->decimal('cantidad_asignada', 10, 4)->default(0); // Cantidad ya asignada a ubicación
            
            // Usuarios
            $table->unsignedBigInteger('chequeador_id'); // Usuario que asignó (chequeador)
            $table->unsignedBigInteger('pasillero_id'); // Usuario pasillero asignado
            
            // Estado de la asignación
            $table->enum('estado', ['pendiente', 'en_proceso', 'completado', 'cancelado'])->default('pendiente');
            
            // Ubicación asignada (cuando el pasillero la asigna)
            $table->string('warehouse_codigo')->nullable(); // Código de la ubicación asignada
            $table->unsignedBigInteger('warehouse_id')->nullable(); // ID de la ubicación
            
            // Datos adicionales del pedido (para referencia)
            $table->decimal('precio_base', 10, 2)->nullable();
            $table->decimal('precio_venta', 10, 2)->nullable();
            $table->string('sucursal_origen')->nullable();
            
            // Observaciones
            $table->text('observaciones')->nullable();
            
            // Índices
            $table->index('pedido_central_id');
            $table->index('pasillero_id');
            $table->index('estado');
            $table->index('warehouse_id');
            
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
        Schema::dropIfExists('tcr_asignaciones');
    }
}
