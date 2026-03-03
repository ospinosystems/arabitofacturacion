<?php

namespace App\Http\Controllers;

use App\Http\Controllers\sendCentral;
use App\Models\clientes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

/**
 * Proxy para solicitudes de descuento de pedidos front (UUID).
 * No persiste nada en facturación; solo reenvía a central.
 */
class SolicitudDescuentoFrontController extends Controller
{
    public function crear(Request $request)
    {
        $request->validate([
            'uuid_pedido_front' => 'required|string',
            'ids_productos' => 'required|array',
            'monto_bruto' => 'required|numeric',
            'monto_con_descuento' => 'required|numeric',
            'monto_descuento' => 'required|numeric',
            'porcentaje_descuento' => 'required|numeric',
            'tipo_descuento' => 'required|in:monto_porcentaje,metodo_pago',
        ]);

        $sendCentral = new sendCentral();
        $dataCliente = $request->input('data_cliente');
        // Si no vienen datos del cliente pero sí id_cliente, cargar desde BD de facturación para enviar a Central (identificación, nombre, etc.)
        if ((empty($dataCliente) || empty($dataCliente['identificacion'] ?? null)) && $request->filled('id_cliente')) {
            $cliente = clientes::find($request->input('id_cliente'));
            if ($cliente) {
                $dataCliente = [
                    'identificacion' => $cliente->identificacion ?? '',
                    'nombre' => $cliente->nombre ?? '',
                    'correo' => $cliente->correo ?? '',
                    'direccion' => $cliente->direccion ?? '',
                    'telefono' => $cliente->telefono ?? '',
                ];
            }
        }
        // Normalizar data_cliente si viene del front: solo campos que Central usa
        if (is_array($dataCliente) && !empty($dataCliente['identificacion'] ?? null)) {
            $dataCliente = [
                'identificacion' => $dataCliente['identificacion'] ?? '',
                'nombre' => $dataCliente['nombre'] ?? '',
                'correo' => $dataCliente['correo'] ?? '',
                'direccion' => $dataCliente['direccion'] ?? '',
                'telefono' => $dataCliente['telefono'] ?? '',
            ];
        }
        $resultado = $sendCentral->crearSolicitudDescuentoFront([
            'id_sucursal' => $sendCentral->getOrigen(),
            'uuid_pedido_front' => $request->uuid_pedido_front,
            'fecha' => $request->input('fecha', now()),
            'monto_bruto' => $request->monto_bruto,
            'monto_con_descuento' => $request->monto_con_descuento,
            'monto_descuento' => $request->monto_descuento,
            'porcentaje_descuento' => $request->porcentaje_descuento,
            'usuario_ensucursal' => session('usuario', 'sucursal'),
            'metodos_pago' => $request->input('metodos_pago', []),
            'ids_productos' => $request->ids_productos,
            'tipo_descuento' => $request->tipo_descuento,
            'observaciones' => $request->input('observaciones', ''),
            'id_cliente' => $request->input('id_cliente'),
            'data_cliente' => $dataCliente,
        ]);

        if (is_array($resultado) && isset($resultado['estado'])) {
            return Response::json($resultado);
        }
        if ($resultado instanceof \Illuminate\Http\JsonResponse) {
            return $resultado;
        }
        return Response::json([
            'estado' => false,
            'msj' => 'Error al enviar solicitud de descuento a central',
        ]);
    }

    public function verificar(Request $request)
    {
        $request->validate([
            'uuid_pedido_front' => 'required|string',
            'tipo_descuento' => 'required|in:monto_porcentaje,metodo_pago',
        ]);

        $sendCentral = new sendCentral();
        $payload = [
            'id_sucursal' => $sendCentral->getOrigen(),
            'uuid_pedido_front' => $request->uuid_pedido_front,
            'tipo_descuento' => $request->tipo_descuento,
        ];
        Log::channel('single')->info('[solicitudDescuentoFrontVerificar] Request', $payload);

        $resultado = $sendCentral->verificarSolicitudDescuentoFront($payload);

        Log::channel('single')->info('[solicitudDescuentoFrontVerificar] Response from central', [
            'is_array' => is_array($resultado),
            'resultado' => is_array($resultado) ? $resultado : (method_exists($resultado, 'getData') ? $resultado->getData(true) : 'not-array'),
            'data_metodos_pago' => is_array($resultado) && isset($resultado['data']['metodos_pago']) ? $resultado['data']['metodos_pago'] : null,
        ]);

        if (is_array($resultado)) {
            return Response::json($resultado);
        }
        if ($resultado instanceof \Illuminate\Http\JsonResponse) {
            return $resultado;
        }
        return Response::json([
            'estado' => false,
            'existe' => false,
            'msj' => 'Error al verificar solicitud',
        ]);
    }

    /**
     * Cancelar una solicitud pendiente de descuento por método de pago (o monto/porcentaje).
     * Así el cajero puede reenviar con los métodos de pago correctos.
     */
    public function cancelar(Request $request)
    {
        $request->validate([
            'uuid_pedido_front' => 'required|string',
            'tipo_descuento' => 'required|in:monto_porcentaje,metodo_pago',
        ]);

        $sendCentral = new sendCentral();
        $resultado = $sendCentral->cancelarSolicitudDescuentoFrontPorUuid(
            $request->uuid_pedido_front,
            $request->tipo_descuento
        );

        if (is_array($resultado) && isset($resultado['estado'])) {
            return Response::json($resultado);
        }
        return Response::json([
            'estado' => false,
            'msj' => 'Error al cancelar la solicitud',
        ]);
    }
}
