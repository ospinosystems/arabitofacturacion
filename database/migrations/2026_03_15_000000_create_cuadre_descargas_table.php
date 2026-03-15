<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCuadreDescargasTable extends Migration
{
    public function up(): void
    {
        Schema::create('cuadre_descargas', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->json('fechas'); // array de fechas Y-m-d
            $table->string('path')->nullable(); // path relativo en storage cuando está listo
            $table->string('status', 20)->default('pending'); // pending, processing, ready, failed
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('ready_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuadre_descargas');
    }
}
