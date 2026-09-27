<?php

namespace App\Services\Cuadre;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deshace el cuadre diario en un rango de fechas:
 *  1. Restaura los ítems/pagos ajustados usando la auditoría `cuadre_ajustes` (valores originales).
 *  2. Compatibilidad: elimina los "ítems de ajuste" del mecanismo antiguo (id_producto NULL, cantidad 1, monto 0)
 *     y recalcula el pago de esos pedidos.
 *  3. Limpia numero_factura, maquina_fiscal y valido.
 *
 * Es la única implementación del reset; la usan cuadre:pedidos-reset y cuadre:pedidos-diario --desde-cero.
 */
class CuadreResetService
{
    public const DATE_EXPR = 'DATE(COALESCE(pedidos.fecha_factura, pedidos.created_at))';

    /** Tamaño de lote para no abrir transacciones enormes en rangos de años. */
    protected int $lote = 500;

    /**
     * IDs de pedidos válidos en el rango (ambos extremos inclusive; null = sin límite).
     * @return int[]
     */
    public function idsEnRango(?string $fechaDesde, ?string $fechaHasta): array
    {
        $q = DB::table('pedidos')->where('valido', true);
        if ($fechaDesde !== null && $fechaDesde !== '') {
            $q->whereRaw(self::DATE_EXPR . ' >= ?', [$fechaDesde]);
        }
        if ($fechaHasta !== null && $fechaHasta !== '') {
            $q->whereRaw(self::DATE_EXPR . ' <= ?', [$fechaHasta]);
        }
        return array_map('intval', $q->orderBy('pedidos.id')->pluck('pedidos.id')->all());
    }

    /**
     * Ejecuta el reset para los ids dados. Devuelve contadores.
     * @param int[] $ids
     * @param callable|null $progreso function(int $procesados, int $total)
     */
    public function resetear(array $ids, ?callable $progreso = null): array
    {
        $stats = [
            'pedidos'              => 0,
            'ajustes_revertidos'   => 0,
            'pagos_eliminados'     => 0,
            'items_legacy_borrados'=> 0,
        ];
        $total = count($ids);
        if ($total === 0) {
            return $stats;
        }

        $tieneAuditoria = Schema::hasTable('cuadre_ajustes');
        $tieneMaquina = Schema::hasColumn('pedidos', 'maquina_fiscal');
        $pagoTieneMontoBs = Schema::hasColumn('pago_pedidos', 'monto_bs');

        foreach (array_chunk($ids, $this->lote) as $chunk) {
            DB::transaction(function () use ($chunk, $tieneAuditoria, $tieneMaquina, $pagoTieneMontoBs, &$stats) {
                if ($tieneAuditoria) {
                    // Del más reciente al más antiguo: si un ítem se ajustó varias veces, al final queda el
                    // valor original más antiguo.
                    $ajustes = DB::table('cuadre_ajustes')
                        ->whereIn('id_pedido', $chunk)
                        ->whereNull('revertido_at')
                        ->orderByDesc('id')
                        ->get();
                    foreach ($ajustes as $a) {
                        if ($a->id_item !== null) {
                            DB::table('items_pedidos')->where('id', $a->id_item)->update([
                                'precio_unitario' => $a->item_precio_unitario_orig,
                                'monto'           => $a->item_monto_orig,
                                'monto_bs'        => $a->item_monto_bs_orig,
                            ]);
                        }
                        if ($a->pago_creado && $a->id_pago !== null) {
                            $stats['pagos_eliminados'] += DB::table('pago_pedidos')->where('id', $a->id_pago)->delete();
                        } elseif ($a->id_pago !== null) {
                            $upd = ['monto' => $a->pago_monto_orig];
                            if ($pagoTieneMontoBs) {
                                $upd['monto_bs'] = $a->pago_monto_bs_orig;
                            }
                            if ($a->pago_monto_original_orig !== null) {
                                $upd['monto_original'] = $a->pago_monto_original_orig;
                            }
                            DB::table('pago_pedidos')->where('id', $a->id_pago)->update($upd);
                        }
                        DB::table('cuadre_ajustes')->where('id', $a->id)->update(['revertido_at' => now(), 'updated_at' => now()]);
                        $stats['ajustes_revertidos']++;
                    }
                }

                // Mecanismo antiguo: ítems de ajuste sin producto.
                $itemsAjuste = DB::table('items_pedidos')
                    ->whereIn('id_pedido', $chunk)
                    ->whereNull('id_producto')->where('cantidad', 1)->where('monto', 0)
                    ->get(['id', 'id_pedido']);
                if ($itemsAjuste->isNotEmpty()) {
                    $stats['items_legacy_borrados'] += DB::table('items_pedidos')->whereIn('id', $itemsAjuste->pluck('id')->all())->delete();
                    foreach ($itemsAjuste->pluck('id_pedido')->unique() as $idPedido) {
                        // Convención: pago.monto = USD, monto_bs = Bs.
                        $sumaUsd = (float) DB::table('items_pedidos')->where('id_pedido', $idPedido)->sum(DB::raw('COALESCE(monto, 0)'));
                        $sumaBs = (float) DB::table('items_pedidos')->where('id_pedido', $idPedido)->sum(DB::raw('COALESCE(monto_bs, 0)'));
                        $upd = ['monto' => round($sumaUsd, 4)];
                        if ($pagoTieneMontoBs) {
                            $upd['monto_bs'] = round($sumaBs, 4);
                        }
                        DB::table('pago_pedidos')->where('id_pedido', $idPedido)->orderBy('id')->limit(1)->update($upd);
                    }
                }

                // valido vuelve a NULL (estado original de un pedido nunca cuadrado).
                $data = ['numero_factura' => null, 'valido' => null];
                if ($tieneMaquina) {
                    $data['maquina_fiscal'] = null;
                }
                $stats['pedidos'] += DB::table('pedidos')->whereIn('id', $chunk)->update($data);
            });

            if ($progreso) {
                $progreso($stats['pedidos'], $total);
            }
        }

        return $stats;
    }

    /**
     * Atajo: resetea todo el rango de fechas.
     */
    public function resetearRango(?string $fechaDesde, ?string $fechaHasta, ?callable $progreso = null): array
    {
        return $this->resetear($this->idsEnRango($fechaDesde, $fechaHasta), $progreso);
    }
}
