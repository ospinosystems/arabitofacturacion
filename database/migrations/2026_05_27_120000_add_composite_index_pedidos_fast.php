<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * PERF 2026-05-27 — Índice compuesto para getPedidosFast.
 *
 * El endpoint corre:
 *   SELECT id, estado FROM pedidos
 *   WHERE fecha_factura BETWEEN ? AND ?
 *   [AND id_vendedor IN (...)]
 *   ORDER BY estado ASC, id DESC
 *   LIMIT 6
 *
 * Antes existía índice simple en `fecha_factura` y otro en `id_vendedor`. MySQL elegía
 * uno de los dos para el filtro y hacía filesort para el ORDER BY. Con el índice compuesto
 * `(id_vendedor, fecha_factura, estado, id)` la query puede:
 *   - resolver el filtro por seek directo en el índice
 *   - usar el orden natural del índice para ORDER BY (parcialmente — `id DESC` lo invierte)
 *   - leer solo el índice (covering index) porque también incluye `id` y `estado`
 *
 * En tablas chicas (cientos de filas) la diferencia es despreciable; en tablas con
 * millones (1 año de operación) baja de centenas a unidades de ms.
 */
class AddCompositeIndexPedidosFast extends Migration
{
    public function up()
    {
        // Comprobar si ya existe (para que la migración sea idempotente y no falle si
        // alguien la corrió a mano). MySQL: SHOW INDEX FROM pedidos.
        $indexName = 'pedidos_fast_idx';
        $existe = false;
        try {
            $rows = DB::select("SHOW INDEX FROM pedidos WHERE Key_name = ?", [$indexName]);
            $existe = count($rows) > 0;
        } catch (\Throwable $e) {
            // Si falla la introspección, intentamos crear y dejamos que MySQL diga si
            // ya existe — el catch de abajo lo absorbe.
        }
        if ($existe) {
            return;
        }
        try {
            Schema::table('pedidos', function (Blueprint $table) use ($indexName) {
                $table->index(['id_vendedor', 'fecha_factura', 'estado', 'id'], $indexName);
            });
        } catch (\Throwable $e) {
            // ej. "Duplicate key name" si la introspección falló. No bloqueamos.
            \Log::warning("AddCompositeIndexPedidosFast::up — índice no creado: " . $e->getMessage());
        }
    }

    public function down()
    {
        try {
            Schema::table('pedidos', function (Blueprint $table) {
                $table->dropIndex('pedidos_fast_idx');
            });
        } catch (\Throwable $e) {
            // ignorar si no existe
        }
    }
}
