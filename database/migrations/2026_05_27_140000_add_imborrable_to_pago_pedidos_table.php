<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POS PINPAD 2026-05-27 — bandera `imborrable` para filas de pago_pedidos que
 * representan transacciones físicas ya cobradas a la tarjeta del cliente
 * (POS aprobado por Megasoft). Estas filas se persisten apenas el POS aprueba,
 * antes de que el cajero facture: si el cajero abandona/cierra el navegador,
 * el cobro queda registrado en backend (no se pierde como pasaba antes).
 *
 * Convención:
 *   imborrable = 0 → fila normal, se puede limpiar en `setPagoPedido` y `delpedido`.
 *   imborrable = 1 → fila protegida (POS PINPAD ya cobrado). Nadie la borra.
 *
 * El índice acelera el filtro en el delete de setPagoPedido:
 *   pago_pedidos::where('id_pedido', $id)->where('imborrable', 0)->delete();
 */
class AddImborrableToPagoPedidosTable extends Migration
{
    public function up()
    {
        Schema::table('pago_pedidos', function (Blueprint $table) {
            $table->tinyInteger('imborrable')->default(0)->after('pos_json_response');
            $table->index(['id_pedido', 'imborrable'], 'pago_pedidos_imborrable_idx');
        });
    }

    public function down()
    {
        Schema::table('pago_pedidos', function (Blueprint $table) {
            $table->dropIndex('pago_pedidos_imborrable_idx');
            $table->dropColumn('imborrable');
        });
    }
}
