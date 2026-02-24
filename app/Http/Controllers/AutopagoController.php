<?php

namespace App\Http\Controllers;

use App\Models\pedidos;
use App\Models\pagos_referencias;
use App\Models\items_pedidos;
use App\Models\tareaslocal;
use Illuminate\Http\Request;
use Response;

/**
 * Orden de autopago: reutiliza setCarrito, setpersonacarrito y setPagoPedido
 * sin alterar la lógica de esos métodos (solo se agrega uuid donde aplica).
 * setPagoPedido espera: debito/debitos[].monto en Bs, efectivo/transferencia/biopago/credito en USD.
 */
class AutopagoController extends Controller
{
    /**
     * Crea una tarea local tipo "agregarProducto" para validar clave DICI antes de abrir el panel.
     * Retorna { estado, id } con el id de la tarea para enviar a sendClavemodal.
     */
    public function crearTareaAgregarProducto(Request $req)
    {
        $tarea = tareaslocal::create([
            'tipo' => 'agregarProducto',
            'descripcion' => 'Solicitar agregar producto (autopago)',
            'id_usuario' => session('id_usuario'),
            'estado' => 0,
            'valoraprobado' => 0,
        ]);
        return Response::json(['estado' => true, 'id' => $tarea->id]);
    }

    /**
     * Completa una orden de autopago: crea pedido, items y pagos en una sola petición.
     * Body: uuid, id_cliente, items [{ id, quantity }], payments [{ type: 'card'|'transfer', amount, currency?: 'bs'|'usd' }]
     * Si currency no se envía, se asume 'bs' (quiosco envía montos en Bs).
     */
    public function completarOrden(Request $req)
    {
        $uuid = $req->uuid;
        $id_cliente = $req->id_cliente ?? 1;
        $items = $req->items ?? [];
        $payments = $req->payments ?? [];

        if (!$uuid || !is_array($items) || count($items) === 0) {
            return Response::json([
                'estado' => false,
                'msj' => 'Faltan uuid o items',
            ], 400);
        }

        // No reprocesar: si ya existe un pedido con este uuid y está procesado (estado 1)
        $yaProcesado = pedidos::where('uuid', $uuid)->where('estado', 1)->first();
        if ($yaProcesado) {
            return Response::json([
                'estado' => false,
                'msj' => 'La orden ya fue procesada',
                'id_pedido' => $yaProcesado->id,
            ], 409);
        }

        \DB::beginTransaction();
        try {
            $pedidosCtrl = new PedidosController();
            $id_pedido = $pedidosCtrl->addNewPedido();

            if ($id_pedido instanceof \Illuminate\Http\JsonResponse) {
                \DB::rollBack();
                return $id_pedido;
            }

            $pedido = pedidos::find($id_pedido);
            if (!$pedido) {
                \DB::rollBack();
                return Response::json(['estado' => false, 'msj' => 'Error al crear pedido'], 500);
            }

            $pedido->uuid = $uuid;
            $pedido->save();

            $personaReq = Request::create('/internal', 'POST', [
                'numero_factura' => $id_pedido,
                'id_cliente' => $id_cliente,
            ]);
            $personaReq->setLaravelSession($req->session());
            $prevRequest = app('request');
            app()->instance('request', $personaReq);
            try {
                $personaResp = $pedidosCtrl->setpersonacarrito($personaReq);
            } finally {
                app()->instance('request', $prevRequest);
            }
            if ($personaResp instanceof \Illuminate\Http\JsonResponse && isset($personaResp->getData()->estado) && !$personaResp->getData()->estado) {
                \DB::rollBack();
                return $personaResp;
            }

            $invCtrl = new InventarioController();
            foreach ($items as $item) {
                $id_producto = (int) ($item['id'] ?? 0);
                $cantidad = (float) ($item['quantity'] ?? 1);
                if ($id_producto <= 0) {
                    continue;
                }
                $carritoReq = Request::create('/internal', 'GET', [
                    'numero_factura' => $id_pedido,
                    'id' => $id_producto,
                    'cantidad' => $cantidad,
                    'type' => null,
                ]);
                $carritoReq->setLaravelSession($req->session());
                $prevRequest = app('request');
                app()->instance('request', $carritoReq);
                try {
                    $carritoResp = $invCtrl->setCarrito($carritoReq);
                } finally {
                    app()->instance('request', $prevRequest);
                }
                if ($carritoResp instanceof \Illuminate\Http\JsonResponse) {
                    $data = $carritoResp->getData();
                    if (isset($data->estado) && $data->estado === false) {
                        \DB::rollBack();
                        return $carritoResp;
                    }
                }
            }

            pagos_referencias::where('uuid_pedido', $uuid)->update(['id_pedido' => $id_pedido]);

            // Tasa del pedido (igual que setPagoPedido: primer ítem) para convertir montos correctamente
            $primerItem = items_pedidos::where('id_pedido', $id_pedido)->first();
            $tasaDolar = $primerItem ? (float) $primerItem->tasa : 1;
            if ($tasaDolar <= 0) {
                $tasaDolar = 1;
            }

            $debitosArray = [];
            $transferenciaUsd = 0.0;
            $efectivoUsd = 0.0;
            $referenciasTransfer = [];
            foreach ($payments as $p) {
                $monto = (float) ($p['amount'] ?? 0);
                if ($monto <= 0) {
                    continue;
                }
                $currency = isset($p['currency']) ? strtolower((string) $p['currency']) : 'bs';

                if (($p['type'] ?? '') === 'card') {
                    // setPagoPedido espera debitos[].monto en BOLÍVARES
                    if ($currency === 'usd') {
                        $montoBs = round($monto * $tasaDolar, 4);
                    } else {
                        $montoBs = $monto;
                    }
                    $referencia = isset($p['referencia']) ? (string) $p['referencia'] : '';
                    $referencia = preg_replace('/\D/', '', $referencia);
                    $referencia = strlen($referencia) >= 4 ? substr($referencia, -4) : str_pad($referencia, 4, '0', STR_PAD_LEFT);
                    $debitoItem = ['monto' => $montoBs, 'referencia' => $referencia];
                    if (!empty($p['posData']) && is_array($p['posData'])) {
                        $debitoItem['posData'] = $p['posData'];
                    }
                    $debitosArray[] = $debitoItem;
                } elseif (($p['type'] ?? '') === 'transfer') {
                    // setPagoPedido espera transferencia en DÓLARES
                    if ($currency === 'usd') {
                        $transferenciaUsd += $monto;
                    } else {
                        $transferenciaUsd += $tasaDolar > 0 ? $monto / $tasaDolar : 0;
                    }
                    $refDesc = isset($p['referencia']) ? (string) $p['referencia'] : '';
                    $refBanco = isset($p['banco']) ? (string) $p['banco'] : '0134';
                    if ($refDesc !== '') {
                        $montoRefBs = $currency === 'usd' ? round($monto * $tasaDolar, 4) : $monto;
                        $referenciasTransfer[] = [
                            'tipo' => 1,
                            'monto' => $montoRefBs,
                            'descripcion' => $refDesc,
                            'banco' => $refBanco,
                            'estatus' => 'aprobada',
                            'categoria' => 'central',
                        ];
                    }
                } elseif (($p['type'] ?? '') === 'manual') {
                    // Pago manual (efectivo/caja): setPagoPedido espera efectivo en DÓLARES
                    if ($currency === 'usd') {
                        $efectivoUsd += $monto;
                    } else {
                        $efectivoUsd += $tasaDolar > 0 ? $monto / $tasaDolar : 0;
                    }
                }
            }
            $transferenciaUsd = round($transferenciaUsd, 4);
            $efectivoUsd = round($efectivoUsd, 4);

            // Ajustar pagos al total real del pedido (el quiosco puede usar otra tasa y enviar más/menos)
            $pedidoReq = Request::create('/internal', 'GET', ['id' => $id_pedido]);
            $pedidoReq->setLaravelSession($req->session());
            $prevReq = app('request');
            app()->instance('request', $pedidoReq);
            try {
                $ped = $pedidosCtrl->getPedido($pedidoReq);
            } finally {
                app()->instance('request', $prevReq);
            }
            $totalRealUsd = (is_object($ped) && isset($ped->clean_total)) ? (float) $ped->clean_total : 0;

            $sumDebitoBs = 0;
            foreach ($debitosArray as $d) {
                $sumDebitoBs += (float) ($d['monto'] ?? 0);
            }
            $totalDebitoUsd = $tasaDolar > 0 ? $sumDebitoBs / $tasaDolar : 0;
            $totalInsUsd = $totalDebitoUsd + $transferenciaUsd + $efectivoUsd;

            $tolerancia = 0.02;
            if ($totalRealUsd > 0 && $totalInsUsd > $totalRealUsd + $tolerancia && $sumDebitoBs > 0) {
                // El quiosco envió más de lo que vale el pedido (ej. otra tasa): ajustar débitos al total real
                $debitoUsdNecesario = $totalRealUsd - $transferenciaUsd;
                if ($debitoUsdNecesario < 0) {
                    $debitoUsdNecesario = 0;
                }
                $sumDebitoBsObjetivo = round($debitoUsdNecesario * $tasaDolar, 4);
                $factor = $sumDebitoBsObjetivo / $sumDebitoBs;
                foreach ($debitosArray as $i => $d) {
                    $m = (float) ($d['monto'] ?? 0);
                    $debitosArray[$i]['monto'] = round($m * $factor, 4);
                }
            }

            $pagoParams = [
                'id' => $id_pedido,
                'efectivo' => $efectivoUsd,
                'transferencia' => $transferenciaUsd,
                'credito' => 0,
                'biopago' => 0,
            ];
            if (count($debitosArray) > 0) {
                $pagoParams['debitos'] = $debitosArray;
                $pagoParams['debito'] = 0;
            } else {
                $pagoParams['debito'] = 0;
            }
            if ($transferenciaUsd > 0 && count($referenciasTransfer) > 0) {
                $pagoParams['front_only'] = true;
                $pagoParams['referencias'] = $referenciasTransfer;
            }

            $pagoReq = Request::create('/internal', 'POST', $pagoParams);
            $pagoReq->setLaravelSession($req->session());

            $prevRequest = app('request');
            app()->instance('request', $pagoReq);
            try {
                $pagoPedidosCtrl = new PagoPedidosController();
                $pagoResp = $pagoPedidosCtrl->setPagoPedido($pagoReq);
            } finally {
                app()->instance('request', $prevRequest);
            }

            if ($pagoResp instanceof \Illuminate\Http\JsonResponse) {
                $pagoData = $pagoResp->getData();
                if (isset($pagoData->estado) && $pagoData->estado === false) {
                    \DB::rollBack();
                    return $pagoResp;
                }
            }

            \DB::commit();

            // Enviar recibo fiscal (orden ya creada y pagada)
            try {
                (new tickera)->sendReciboFiscalFun($id_pedido);
            } catch (\Exception $e) {
                \Log::error('Autopago: error al enviar recibo fiscal', ['id_pedido' => $id_pedido, 'error' => $e->getMessage()]);
            }

            return Response::json([
                'estado' => true,
                'msj' => 'Orden guardada',
                'id_pedido' => $id_pedido,
            ]);
        } catch (\Exception $e) {
            \DB::rollBack();
            return Response::json([
                'estado' => false,
                'msj' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }
}
