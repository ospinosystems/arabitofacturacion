<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCierresMetodosPagoTable extends Migration
{
    /**
     * Run the migrations.
     * 
     * Esta tabla permite registrar métodos de pago variables de forma flexible
     * para poder sumar, agrupar y reportar dinámicamente sin modificar estructura
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cierres_metodos_pago', function (Blueprint $table) {
            $table->increments('id');
            
            // Clave foránea al cierre
            $table->integer('id_cierre')->unsigned();
            $table->foreign('id_cierre')->references('id')->on('cierres')->onDelete('cascade');
            
            // Tipo de método de pago (1=transferencia, 2=débito, 3=efectivo, 4=crédito, 5=biopago, etc.)
            $table->integer('tipo_pago')->unsigned();
            
            // Subtipo si aplica (ej: 'pinpad', 'otros_puntos', 'usd', 'bs', 'cop')
            $table->string('subtipo')->nullable();
            
            // Moneda del método de pago
            $table->string('moneda', 10)->default('USD'); // USD, BS, COP
            
            // Montos (soporta hasta mil millones)
            // 12 dígitos en total, 2 decimales: hasta 9,999,999,999.99
            $table->decimal('monto_real', 12, 2)->default(0);      
            $table->decimal('monto_inicial', 12, 2)->default(0);   
            $table->decimal('monto_digital', 12, 2)->default(0);   
            $table->decimal('monto_diferencia', 12, 2)->default(0); 
            
            // Estado del cuadre
            $table->string('estado_cuadre', 20)->nullable(); // 'cuadrado', 'sobra', 'falta'
            $table->string('mensaje_cuadre', 255)->nullable(); // Mensaje descriptivo
            
            // Metadatos adicionales en JSON (flexible para futuras necesidades)
            $table->json('metadatos')->nullable();
            
            $table->timestamps();
            
            // Índices para optimizar consultas de agrupación y sumas
            $table->index(['id_cierre', 'tipo_pago']);
            $table->index(['id_cierre', 'subtipo']);
            $table->index(['id_cierre', 'moneda']);
            $table->index('tipo_pago');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cierres_metodos_pago');
    }
}
