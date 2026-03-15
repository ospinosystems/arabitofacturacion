<?php

namespace App\Services;

use App\Models\pedidos;
use App\Models\items_pedidos;
use Barryvdh\DomPDF\Facade\Pdf;

class CuadrePdfService
{
    /**
     * Genera el PDF de un pedido (vista cuadre-diario-pedido) y devuelve el contenido binario.
     */
    public static function generarPdfPedido(int $id): ?string
    {
        $pedido = pedidos::with('cliente')->find($id);
        if (!$pedido) {
            return null;
        }
        $items = items_pedidos::where('id_pedido', $id)
            ->leftJoin('inventarios', 'inventarios.id', '=', 'items_pedidos.id_producto')
            ->select(
                'items_pedidos.*',
                'inventarios.codigo_barras',
                'inventarios.codigo_proveedor',
                'inventarios.descripcion as producto_descripcion'
            )
            ->orderBy('items_pedidos.id')
            ->get();

        $subtotalUsd = $items->sum(function ($i) { return (float) ($i->monto ?? 0); });
        $subtotalBs = $items->sum(function ($i) { return (float) ($i->monto_bs ?? 0); });
        $tasaPedido = null;
        $itemConTasa = $items->first(function ($i) { return isset($i->tasa) && (float) $i->tasa > 0; });
        if ($itemConTasa !== null) {
            $tasaPedido = (float) $itemConTasa->tasa;
        } elseif ($subtotalUsd > 0 && $subtotalBs > 0) {
            $tasaPedido = $subtotalBs / $subtotalUsd;
        }

        $html = view('reportes.cuadre-diario-pedido', [
            'pedido'        => $pedido,
            'items'         => $items,
            'subtotalUsd'   => $subtotalUsd,
            'subtotalBs'    => $subtotalBs,
            'montoTotalUsd' => $subtotalUsd,
            'montoTotalBs'  => $subtotalBs,
            'tasaPedido'    => $tasaPedido,
            'pdf'           => true,
        ])->render();

        $pdf = Pdf::loadHTML($html)
            ->setPaper('letter', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled'         => true,
                'margin_left'          => 10,
                'margin_right'         => 10,
                'margin_top'           => 10,
                'margin_bottom'        => 10,
            ]);

        return $pdf->output();
    }
}
