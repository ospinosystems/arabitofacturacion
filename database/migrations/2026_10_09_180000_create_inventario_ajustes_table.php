<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajustes de inventario del libro de entradas y salidas (ejecutar con --path):
 *  - tipo AJUSTE: movimientos del equipo de inventario traídos del registro de movimientos de central
 *    (ediciones del DICI, ajustes de TitanioPOS, garantías y fusiones de fichas), con cantidad con signo.
 *  - tipo SALDO_INICIAL: existencia de apertura (fechada en marzo) para los productos que de otro modo
 *    quedarían con existencia negativa; la calcula inventario:saldos-iniciales.
 */
class CreateInventarioAjustesTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inventario_ajustes')) {
            return;
        }
        Schema::create('inventario_ajustes', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedInteger('sucursal_central_id')->nullable();
            $t->string('tipo', 20);                // AJUSTE | SALDO_INICIAL
            $t->string('subtipo', 40);             // EDICION DICI | AJUSTE TPOS | GARANTIA | FUSION | SALDO INICIAL
            $t->string('clave', 40)->unique();     // TP<id mov central> | TP<id>-origen | SI<id_producto>
            $t->date('fecha')->index();
            $t->unsignedInteger('id_producto')->index();
            $t->decimal('cantidad', 14, 4);        // con signo: + entra, - sale
            $t->string('usuario', 60)->nullable();
            $t->string('referencia', 255)->nullable();
            $t->timestamp('importado_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_ajustes');
    }
}
