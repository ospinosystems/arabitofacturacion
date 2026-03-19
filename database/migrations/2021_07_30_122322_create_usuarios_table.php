<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class CreateUsuariosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre');
            $table->string('usuario')->unique();
            $table->string('clave');
            $table->integer('tipo_usuario');
            // 1 Administrador
            // 2 Caja
            // 3 Vendedor
            // 4 Cajero Vendedor
            $table->timestamps();
        });

        $now = now();
        $cajaHash = Hash::make('1234');
        $adminHash = Hash::make('1234');

        DB::table('usuarios')->insert([
            [
                'nombre' => 'Caja 1',
                'usuario' => 'caja1',
                'clave' => $cajaHash,
                'tipo_usuario' => 4,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'nombre' => 'Caja 2',
                'usuario' => 'caja2',
                'clave' => $cajaHash,
                'tipo_usuario' => 4,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'nombre' => 'Caja 3',
                'usuario' => 'caja3',
                'clave' => $cajaHash,
                'tipo_usuario' => 4,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'nombre' => 'Caja 4',
                'usuario' => 'caja4',
                'clave' => $cajaHash,
                'tipo_usuario' => 4,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'nombre' => 'Administrador',
                'usuario' => 'admin',
                'clave' => $adminHash,
                'tipo_usuario' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('usuarios');
    }
}
