<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bulto (paquete físico) de una transferencia. Una transferencia puede tener varios bultos.
 * Al cerrarse se genera un código de barras único para la etiqueta. En el despacho se escanea
 * bulto por bulto y cada uno da salida a la mercancía que contiene.
 *
 * Nota de tipos: igual que en transferencia_asignaciones, el signo de la FK se detecta en
 * runtime porque transferencias_inventarios.id es SIGNED en producción y UNSIGNED en una DB
 * creada desde cero por las migraciones.
 */
return new class extends Migration
{
    public function up()
    {
        $refUnsigned = $this->referenciaEsUnsigned();

        Schema::create('transferencia_bultos', function (Blueprint $table) use ($refUnsigned) {
            $table->increments('id');

            $refUnsigned
                ? $table->unsignedInteger('id_transferencia')
                : $table->integer('id_transferencia');
            $table->foreign('id_transferencia')->references('id')->on('transferencias_inventarios')
                ->onDelete('cascade')->onUpdate('cascade');

            $table->integer('numero'); // correlativo por orden: 1..N
            $table->string('codigo_barras')->unique();

            $table->enum('estado', ['abierto', 'cerrado', 'despachado'])->default('abierto')->index();

            $table->integer('cerrado_por')->nullable();
            $table->timestamp('cerrado_at')->nullable();
            $table->integer('despachado_por')->nullable();
            $table->timestamp('despachado_at')->nullable();

            $table->timestamps();
            $table->index('id_transferencia');
        });
    }

    public function down()
    {
        Schema::dropIfExists('transferencia_bultos');
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
