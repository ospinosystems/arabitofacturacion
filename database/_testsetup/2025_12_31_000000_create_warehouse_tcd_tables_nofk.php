<?php

/**
 * SETUP DE PRUEBAS (no es parte del esquema canónico).
 *
 * La BD local `sinapsis` no fue construida con migraciones y sus tablas legacy
 * (inventarios/usuarios) no aceptan llaves foráneas (error 150). Estas 3 tablas
 * del módulo warehouse/TCD fallan al crearse por sus FK. Aquí las creamos con
 * las MISMAS columnas pero SIN las FK, para poder correr los tests de lógica.
 *
 * Correr con:  php artisan migrate --path=database/_testsetup
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('warehouse_inventory')) {
            Schema::create('warehouse_inventory', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('warehouse_id');
                $table->unsignedInteger('inventario_id');
                $table->decimal('cantidad', 10, 2)->default(0);
                $table->decimal('cantidad_bloqueada', 10, 4)->default(0);
                $table->string('lote')->nullable();
                $table->date('fecha_vencimiento')->nullable();
                $table->date('fecha_entrada')->nullable();
                $table->enum('estado', ['disponible', 'reservado', 'bloqueado', 'dañado'])->default('disponible');
                $table->text('observaciones')->nullable();
                $table->index(['warehouse_id', 'inventario_id']);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('warehouse_movements')) {
            Schema::create('warehouse_movements', function (Blueprint $table) {
                $table->id();
                $table->enum('tipo', ['entrada', 'salida', 'transferencia', 'ajuste', 'recepcion'])->default('transferencia');
                $table->unsignedInteger('inventario_id');
                $table->unsignedBigInteger('warehouse_origen_id')->nullable();
                $table->unsignedBigInteger('warehouse_destino_id')->nullable();
                $table->decimal('cantidad', 10, 2);
                $table->string('lote')->nullable();
                $table->date('fecha_vencimiento')->nullable();
                $table->unsignedInteger('usuario_id')->nullable();
                $table->string('documento_referencia')->nullable();
                $table->text('observaciones')->nullable();
                $table->datetime('fecha_movimiento')->useCurrent();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('tcd_orden_items')) {
            Schema::create('tcd_orden_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tcd_orden_id');
                $table->unsignedInteger('inventario_id');
                $table->string('codigo_barras')->nullable();
                $table->string('codigo_proveedor')->nullable();
                $table->string('descripcion');
                $table->decimal('precio', 10, 2)->nullable();
                $table->decimal('precio_base', 10, 2)->nullable();
                $table->string('ubicacion')->nullable();
                $table->decimal('cantidad', 10, 4);
                $table->decimal('cantidad_descontada', 10, 4)->default(0);
                $table->decimal('cantidad_bloqueada', 10, 4)->default(0);
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        // no-op: setup de pruebas
    }
};
