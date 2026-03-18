<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registro por defecto (codigo sucursal) creado en create_sucursals;
 * pinpad se agrega después con default false — alinear con valor deseado.
 */
class SetPinpadOnDefaultSucursal extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('sucursals') || ! Schema::hasColumn('sucursals', 'pinpad')) {
            return;
        }

        DB::table('sucursals')->where('codigo', 'sucursal')->update(['pinpad' => true]);
    }

    public function down()
    {
        if (! Schema::hasTable('sucursals') || ! Schema::hasColumn('sucursals', 'pinpad')) {
            return;
        }

        DB::table('sucursals')->where('codigo', 'sucursal')->update(['pinpad' => false]);
    }
}
