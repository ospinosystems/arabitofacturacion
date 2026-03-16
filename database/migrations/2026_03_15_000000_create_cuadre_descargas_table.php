<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCuadreDescargasTable extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cuadre_descargas')) {
            Schema::create('cuadre_descargas', function (Blueprint $table) {
                $table->id();
                $table->string('token', 64)->unique();
                $table->json('fechas');
                $table->string('path')->nullable();
                $table->string('status', 20)->default('pending');
                $table->unsignedInteger('total_fechas')->default(0);
                $table->unsignedInteger('fechas_procesadas')->default(0);
                $table->unsignedInteger('total_pedidos')->default(0);
                $table->string('fecha_actual', 10)->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ready_at')->nullable();
            });
            return;
        }

        Schema::table('cuadre_descargas', function (Blueprint $table) {
            if (!Schema::hasColumn('cuadre_descargas', 'total_fechas')) {
                $table->unsignedInteger('total_fechas')->default(0)->after('status');
            }
            if (!Schema::hasColumn('cuadre_descargas', 'fechas_procesadas')) {
                $table->unsignedInteger('fechas_procesadas')->default(0)->after('total_fechas');
            }
            if (!Schema::hasColumn('cuadre_descargas', 'total_pedidos')) {
                $table->unsignedInteger('total_pedidos')->default(0)->after('fechas_procesadas');
            }
            if (!Schema::hasColumn('cuadre_descargas', 'fecha_actual')) {
                $table->string('fecha_actual', 10)->nullable()->after('total_pedidos');
            }
            if (!Schema::hasColumn('cuadre_descargas', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('ready_at');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuadre_descargas');
    }
}
