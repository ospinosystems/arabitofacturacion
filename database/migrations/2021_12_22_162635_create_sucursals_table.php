<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateSucursalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sucursals', function (Blueprint $table) {
            $table->increments('id');

            $table->string('sucursal');
            $table->string('codigo')->unique();
            $table->string('direccion_registro');
            $table->string('direccion_sucursal');
            $table->string('telefono1');
            $table->string('telefono2');

            $table->string('correo');
            $table->string('nombre_registro');
            $table->string('rif');
            $table->boolean('iscentral')->default(0);

            $table->string('tickera')->nullable();
            $table->string('fiscal')->nullable();
            $table->string('app_version')->default('1');

            $table->timestamps();
        });

        $now = now();
        DB::table('sucursals')->insert([
            'sucursal' => 'SUCURSAL',
            'codigo' => 'sucursal',
            'direccion_registro' => '',
            'direccion_sucursal' => '',
            'telefono1' => '04269414946',
            'telefono2' => '04264712991',
            'correo' => 'arabitoferreteria@gmail.com',
            'nombre_registro' => '',
            'rif' => '',
            'iscentral' => true,
            'tickera' => 'smb://CAJA 1:12345678@caja1/XP-58',
            'fiscal' => '0',
            'app_version' => '1',
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
        Schema::dropIfExists('sucursals');
    }
}
