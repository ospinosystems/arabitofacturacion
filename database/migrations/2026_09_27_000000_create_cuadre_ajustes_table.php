<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoría de los ajustes que hace cuadre:pedidos-diario sobre ítems y pagos reales.
 * Guarda los valores ORIGINALES para que cuadre:pedidos-reset (y --desde-cero) puedan
 * restaurarlos exactamente antes de volver a cuadrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cuadre_ajustes')) {
            return;
        }
        Schema::create('cuadre_ajustes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('id_pedido')->index();
            $table->date('fecha')->nullable();
            $table->string('maquina_fiscal', 50)->nullable();
            $table->decimal('ajuste_bs', 18, 4)->default(0);
            $table->decimal('ajuste_aplicado_bs', 18, 4)->default(0);

            $table->unsignedInteger('id_item')->nullable();
            $table->decimal('item_precio_unitario_orig', 18, 6)->nullable();
            $table->decimal('item_monto_orig', 18, 6)->nullable();
            $table->decimal('item_monto_bs_orig', 18, 6)->nullable();
            $table->decimal('item_precio_unitario_nuevo', 18, 6)->nullable();
            $table->decimal('item_monto_nuevo', 18, 6)->nullable();
            $table->decimal('item_monto_bs_nuevo', 18, 6)->nullable();

            $table->unsignedInteger('id_pago')->nullable();
            $table->boolean('pago_creado')->default(false);
            $table->decimal('pago_monto_orig', 18, 6)->nullable();
            $table->decimal('pago_monto_bs_orig', 18, 6)->nullable();
            $table->decimal('pago_monto_original_orig', 18, 6)->nullable();

            $table->timestamp('revertido_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuadre_ajustes');
    }
};
