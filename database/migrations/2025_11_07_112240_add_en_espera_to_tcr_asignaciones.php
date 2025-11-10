<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddEnEsperaToTcrAsignaciones extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Modificar el enum para agregar 'en_espera'
        DB::statement("ALTER TABLE tcr_asignaciones MODIFY COLUMN estado ENUM('pendiente', 'en_proceso', 'en_espera', 'completado', 'cancelado') DEFAULT 'pendiente'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Revertir el enum a su estado original
        DB::statement("ALTER TABLE tcr_asignaciones MODIFY COLUMN estado ENUM('pendiente', 'en_proceso', 'completado', 'cancelado') DEFAULT 'pendiente'");
    }
}
