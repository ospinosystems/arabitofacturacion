<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateClientesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('identificacion', 30)->unique();

            $table->string('nombre');
            $table->string('correo')->nullable();
            $table->text('direccion')->nullable();
            $table->string('telefono')->nullable();

            $table->string('estado')->nullable();
            $table->string('ciudad')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('clientes')->insert([
            'identificacion' => 'CF',
            'nombre' => 'CF',
            'correo' => 'CF',
            'direccion' => 'CF',
            'telefono' => 'CF',
            'estado' => 'CF',
            'ciudad' => 'CF',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('clientes');
    }
}
