<?php

namespace App\Http\Controllers;

use App\Http\Controllers\sendCentral;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

/**
 * Proxy para solicitudes de crédito de pedidos front (UUID).
 * No persiste nada en facturación; solo reenvía a central.
 */
class SolicitudCreditoFrontController extends Controller
{
    public function crear(Request $request)
    {
        $request->validate([
            'uuid_pedido_front' => 'required|string',
            'saldo' => 'required|numeric|min:0',
            'fecha_inicio' => 'required|date',
            'fecha_vence' => 'required|date',
            'formato_pago' => 'required|integer|min:0',
            'cliente' => 'required|array',
            'cliente.identificacion' => 'required|string',
            'cliente.nombre' => 'required|string',
        ]);

        $data = [
            'uuid_pedido_front' => $request->uuid_pedido_front,
            'saldo' => floatval($request->saldo),
            'fecha_inicio' => $request->fecha_inicio,
            'fecha_vence' => $request->fecha_vence,
            'formato_pago' => (int) $request->formato_pago,
            'cliente' => $request->cliente,
            'deuda' => $request->input('deuda'),
            'fecha_ultimopago' => $request->input('fecha_ultimopago'),
        ];

        $sendCentral = new sendCentral();
        $resultado = $sendCentral->createCreditoAprobacion($data);

        if ($resultado === 'APROBADO') {
            return Response::json(['estado' => true, 'msj' => 'Crédito aprobado']);
        }
        if (is_string($resultado) && strpos($resultado, 'Solicitud enviada') !== false) {
            return Response::json(['estado' => true, 'msj' => $resultado]);
        }
        if ($resultado instanceof \Illuminate\Http\JsonResponse) {
            return $resultado;
        }
        if (is_object($resultado) && method_exists($resultado, 'json')) {
            $data = $resultado->json();
            return Response::json($data ?? ['estado' => false, 'msj' => 'Error desde central']);
        }
        return Response::json([
            'estado' => false,
            'msj' => is_string($resultado) ? $resultado : 'Error al enviar solicitud de crédito a central',
        ]);
    }
}
