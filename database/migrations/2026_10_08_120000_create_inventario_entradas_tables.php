<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entradas de inventario de la sucursal copiadas desde central (pedidos de central con destino esta sucursal):
 * facturas fiscales de compra (CxP tipo FACTURA), notas (CxP tipo NOTA) y traslados sin CxP. Las carga
 * `inventario:importar-entradas`; el libro de inventario las cruza con las salidas (facturas cuadradas).
 */
class CreateInventarioEntradasTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('inventario_entradas')) {
            Schema::create('inventario_entradas', function (Blueprint $t) {
                $t->increments('id');
                $t->unsignedInteger('sucursal_central_id')->index();     // id de esta sucursal en central (Punto Fijo = 46)
                $t->string('tipo', 20)->index();                         // FACTURA | NOTA | TRANSFERENCIA | otro tipo_documento
                $t->unsignedInteger('central_pedido_id')->unique();      // pedidos.id en central
                $t->unsignedInteger('central_cxp_id')->nullable()->index();
                $t->unsignedInteger('origen_sucursal_id')->nullable();
                $t->string('origen_sucursal', 60)->nullable();
                $t->string('numero_documento', 60)->nullable();          // cuentasporpagars.numfact
                $t->string('numero_nota', 60)->nullable();               // cuentasporpagars.numnota
                $t->string('proveedor', 191)->nullable();
                $t->string('proveedor_rif', 40)->nullable();
                $t->date('fecha_emision')->nullable();
                $t->date('fecha_recepcion')->nullable()->index();        // fecha en que la mercancía entró (pedido de central)
                $t->decimal('tasa_bs', 15, 4)->nullable();
                $t->decimal('monto_usd', 14, 2)->nullable();
                $t->boolean('anulado')->default(false);
                $t->timestamp('importado_at')->nullable();
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('inventario_entrada_items')) {
            Schema::create('inventario_entrada_items', function (Blueprint $t) {
                $t->increments('id');
                $t->unsignedInteger('entrada_id')->index();
                $t->unsignedBigInteger('central_id_producto')->index();  // items_pedidos.id_producto en central (id del almacén origen)
                $t->unsignedBigInteger('id_producto')->nullable()->index(); // inventarios.id local
                $t->string('mapeo', 20)->nullable();                     // id | codigo_barras | codigo_proveedor | descripcion | creado | ninguno
                $t->decimal('cantidad', 12, 4);
                $t->decimal('cantidad_real', 12, 4)->nullable();
                $t->decimal('costo_unitario_usd', 15, 4);
                $t->decimal('precio_venta_usd', 15, 4)->nullable();
                $t->string('codigo_barras_origen', 191)->nullable();
                $t->string('codigo_proveedor_origen', 191)->nullable();
                $t->string('descripcion_origen', 191)->nullable();
                $t->timestamps();
                $t->unique(['entrada_id', 'central_id_producto']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('inventario_entrada_items');
        Schema::dropIfExists('inventario_entradas');
    }
}
