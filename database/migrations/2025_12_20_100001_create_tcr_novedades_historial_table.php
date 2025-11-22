<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTcrNovedadesHistorialTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tcr_novedades_historial', function (Blueprint $table) {
            $table->id();
            
            // Relación con la novedad
            $table->unsignedBigInteger('novedad_id');
            
            // Cantidad agregada en esta operación
            $table->decimal('cantidad_agregada', 10, 4);
            
            // Cantidad total acumulada después de esta operación
            $table->decimal('cantidad_total', 10, 4);
            
            // Usuario que agregó
            $table->unsignedBigInteger('pasillero_id');
            
            // Observaciones de esta agregación
            $table->text('observaciones')->nullable();
            
            // Índices
            $table->index('novedad_id');
            $table->index('pasillero_id');
            $table->index('created_at');
            
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('novedad_id')->references('id')->on('tcr_novedades')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tcr_novedades_historial');
    }
}

