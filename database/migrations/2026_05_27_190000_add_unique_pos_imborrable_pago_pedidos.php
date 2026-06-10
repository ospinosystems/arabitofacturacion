<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * POS-IMBORRABLE-UNIQUE 2026-05-27
 *
 * Constraint contra duplicados de pago POS imborrable causados por race conditions.
 *
 * Caso real reportado (pedido #711641): 2 filas con mismos sequence/authid/referencia/
 * monto/timestamp por race condition de 2 POSTs simultáneos a /registrarPagoPosPinpad.
 * El GET_LOCK en persistirPosImborrable serializa el flujo, pero este índice único es
 * la defensa final: si el lock falla (ej DB cerrada, timeout), MySQL rechaza con
 * "Duplicate entry" y el insert no pasa.
 *
 * Como MySQL 5.7+ no soporta partial unique indexes directos, usamos una columna
 * generated stored que es la clave compuesta SOLO si imborrable=1+tipo=2, y NULL
 * en otros casos. Un índice único sobre NULLs no rechaza nada (MySQL permite NULLs
 * múltiples en UNIQUE), solo aplica cuando los 4 campos están presentes.
 */
return new class extends Migration {
    public function up(): void
    {
        // Verificar primero si ya hay duplicados existentes — si los hay, NO crear el
        // índice porque la creación fallaría. El admin debe limpiar primero.
        $duplicados = DB::select("
            SELECT id_pedido, referencia, COUNT(*) as cnt
            FROM pago_pedidos
            WHERE tipo = 2 AND imborrable = 1
            GROUP BY id_pedido, referencia
            HAVING cnt > 1
        ");

        if (!empty($duplicados)) {
            \Log::warning('[POS-UNIQUE] No se crea índice único — hay duplicados existentes', [
                'count' => count($duplicados),
                'samples' => array_slice($duplicados, 0, 5),
            ]);
            // No interrumpimos la migración — solo no creamos el índice.
            return;
        }

        // Columna generated: NULL si no es imborrable, sino "{id_pedido}|{referencia}"
        Schema::table('pago_pedidos', function ($table) {
            DB::statement("
                ALTER TABLE pago_pedidos
                ADD COLUMN pos_imborrable_key VARCHAR(80)
                GENERATED ALWAYS AS (
                    CASE
                        WHEN imborrable = 1 AND tipo = 2
                            THEN CONCAT(id_pedido, '|', referencia)
                        ELSE NULL
                    END
                ) STORED
            ");
            DB::statement("
                ALTER TABLE pago_pedidos
                ADD UNIQUE INDEX uniq_pos_imborrable_key (pos_imborrable_key)
            ");
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('pago_pedidos', 'pos_imborrable_key')) {
            DB::statement("ALTER TABLE pago_pedidos DROP INDEX uniq_pos_imborrable_key");
            DB::statement("ALTER TABLE pago_pedidos DROP COLUMN pos_imborrable_key");
        }
    }
};
