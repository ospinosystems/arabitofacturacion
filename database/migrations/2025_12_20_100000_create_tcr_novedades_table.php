<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTcrNovedadesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tcr_novedades', function (Blueprint $table) {
            $table->id();
            
            // Relación con pedido (opcional, puede ser novedad sin pedido)
            $table->integer('pedido_central_id')->nullable();
            $table->integer('item_pedido_id')->nullable();
            
            // Información del producto
            $table->string('codigo_barras')->nullable();
            $table->string('codigo_proveedor')->nullable();
            $table->string('descripcion');
            
            // Cantidades
            $table->decimal('cantidad_llego', 10, 4)->default(0); // Cantidad que llegó según pedido
            $table->decimal('cantidad_enviada', 10, 4)->default(0); // Cantidad que se envió/registró
            $table->decimal('diferencia', 10, 4)->default(0); // Diferencia entre llegó y enviada
            
            // Información del producto en inventario (si existe)
            $table->unsignedBigInteger('inventario_id')->nullable(); // ID del producto en inventarios si existe
            
            // Usuario que registró la novedad
            $table->unsignedBigInteger('pasillero_id'); // Usuario pasillero que registró
            $table->unsignedBigInteger('chequeador_id')->nullable(); // Usuario chequeador (si aplica)
            
            // Estado
            $table->enum('estado', ['pendiente', 'procesado', 'cancelado'])->default('pendiente');
            
            // Observaciones
            $table->text('observaciones')->nullable();
            
            // Índices
            $table->index('pedido_central_id');
            $table->index('pasillero_id');
            $table->index('inventario_id');
            $table->index('estado');
            $table->index('created_at');
            
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
        Schema::dropIfExists('tcr_novedades');
    }
}

