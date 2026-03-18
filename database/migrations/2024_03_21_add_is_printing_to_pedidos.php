<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Anonymous class: avoids "Cannot declare class ... already in use" on Windows when Laravel
// re-requires this file because realpath() !== ReflectionClass::getFileName().
return new class extends Migration
{
    public function up()
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->boolean('is_printing')->default(false)->after('ticked');
        });
    }

    public function down()
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn('is_printing');
        });
    }
};
