<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWarehousesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            
            // Coordenadas de ubicación: [LETRA:PASILLO][NUMERO:CARADELPASILLO]-[NUMERO:RACK]-[NUMERO:NIVEL]
            $table->string('pasillo', 10); // A, B, C, etc.
            $table->integer('cara'); // 1, 2 (cara del pasillo)
            $table->integer('rack'); // Número de rack
            $table->integer('nivel'); // Nivel del rack
            
            // Código único de ubicación generado: A1-15-3
            $table->string('codigo')->unique(); // Generado automáticamente
            
            // Información adicional
            $table->string('nombre')->nullable(); // Nombre descriptivo opcional
            $table->text('descripcion')->nullable();
            $table->enum('tipo', ['almacenamiento', 'picking', 'recepcion', 'despacho'])->default('almacenamiento');
            $table->enum('estado', ['activa', 'inactiva', 'mantenimiento', 'bloqueada'])->default('activa');
            $table->string('zona')->nullable(); // Zona del almacén (A, B, C, etc.)
            
            // Capacidad
            $table->decimal('capacidad_peso', 10, 2)->nullable(); // kg
            $table->decimal('capacidad_volumen', 10, 2)->nullable(); // m³
            $table->integer('capacidad_unidades')->nullable(); // cantidad máxima de productos
            
            // Índices para búsqueda rápida
            $table->index(['pasillo', 'cara', 'rack', 'nivel']);
            $table->index('estado');
            $table->index('tipo');
            
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
        Schema::dropIfExists('warehouses');
    }
}
