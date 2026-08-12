<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Asignación de una línea de transferencia a un pasillero (usuario tipo 8).
 * Es la "orden de recolección": qué producto/cantidad debe ir a buscar cada pasillero,
 * y cuánto realmente recolectó (cantidad_recolectada).
 *
 * Nota de tipos: en producción transferencias_inventarios.id es int(11) SIGNED, pero una DB
 * creada desde cero por las migraciones lo deja int UNSIGNED (viene de increments()). MySQL 8
 * exige que la FK use exactamente el mismo signo, así que el tipo se detecta en runtime.
 */
return new class extends Migration
{
    public function up()
    {
        $refUnsigned = $this->referenciaEsUnsigned();

        Schema::create('transferencia_asignaciones', function (Blueprint $table) use ($refUnsigned) {
            $table->increments('id');

            $refUnsigned
                ? $table->unsignedInteger('id_transferencia')
                : $table->integer('id_transferencia');
            $table->foreign('id_transferencia')->references('id')->on('transferencias_inventarios')
                ->onDelete('cascade')->onUpdate('cascade');

            $table->integer('id_transferencia_item')->index();
            $table->integer('id_producto')->index();
            $table->integer('pasillero_id')->index();

            $table->decimal('cantidad', 13, 3);
            $table->decimal('cantidad_recolectada', 13, 3)->default(0);

            $table->enum('estado', ['pendiente', 'en_proceso', 'recolectada'])->default('pendiente')->index();
            $table->string('warehouse_codigo')->nullable();
            $table->text('observaciones')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('transferencia_asignaciones');
    }

    /**
     * ¿transferencias_inventarios.id es UNSIGNED? (true en DB nueva, false en la de producción)
     */
    private function referenciaEsUnsigned(): bool
    {
        $columna = DB::selectOne(
            'select column_type as tipo from information_schema.columns
             where table_schema = database() and table_name = ? and column_name = ?',
            ['transferencias_inventarios', 'id']
        );

        return $columna ? str_contains(strtolower($columna->tipo), 'unsigned') : false;
    }
};
