<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Amplía monto (y monto_original si existe) para soportar montos altos (ej. cuadre diario en Bs).
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE pago_pedidos MODIFY monto DECIMAL(18, 4) NOT NULL');

        if (Schema::hasColumn('pago_pedidos', 'monto_original')) {
            DB::statement('ALTER TABLE pago_pedidos MODIFY monto_original DECIMAL(18, 4) NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE pago_pedidos MODIFY monto DECIMAL(8, 2) NOT NULL');

        if (Schema::hasColumn('pago_pedidos', 'monto_original')) {
            DB::statement('ALTER TABLE pago_pedidos MODIFY monto_original DECIMAL(8, 2) NULL');
        }
    }
};
