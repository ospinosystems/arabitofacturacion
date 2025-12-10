<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSucursalesCentralCacheTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sucursales_central_cache', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('nombre')->nullable();
            $table->string('direccion')->nullable();
            $table->string('rif')->nullable();
            $table->string('razon_social')->nullable();
            $table->string('direccion_fiscal')->nullable();
            $table->text('direccion_entrega')->nullable();
            $table->string('zona')->nullable();
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
        Schema::dropIfExists('sucursales_central_cache');
    }
}
