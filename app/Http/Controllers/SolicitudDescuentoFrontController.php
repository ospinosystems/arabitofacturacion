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
     * VERIFICAR + APLICAR DESCUENTO PARA PEDIDOS BACKEND (con id_pedido en BD).
     *
     * Equivalente a `verificar()` pero para pedidos que ya existen en facturación.
     * Replica el comportamiento que tenía el botón "Verificar aprobación" en pedidos
     * front: consulta central, y si está aprobada, aplica los porcentajes a los items
     * del pedido (persistencia) para que el cajero los vea con el descuento cargado
     * inmediatamente y pueda facturar normal.
     *
     * Diseñado para tipo_descuento = 'monto_porcentaje' (descuento total/unitario).
     * Para 'metodo_pago' devuelve la respuesta de central tal cual; la aplicación
     * de ese descuento sigue ocurriendo en el flujo de setPagoPedido.
     */
    public function verificarBackend(Request $request)
    {
        $request->validate([
            'id_pedido' => 'required|integer',
            'tipo_descuento' => 'required|in:monto_porcentaje,metodo_pago',
            // check_only=true se usa al abrir el pedido para hidratar el tracker
            // del frontend sin disparar la aplicación de descuento (solo consulta).
            'check_only' => 'nullable|boolean',
        ]);

        $sendCentral = new sendCentral();
        $payload = [
            'id_sucursal' => $sendCentral->getOrigen(),
            'id_pedido' => (int) $request->id_pedido,
            'tipo_descuento' => $request->tipo_descuento,
        ];
        Log::channel('single')->info('[solicitudDescuentoBackendVerificar] Request', $payload);

        $resultado = $sendCentral->verificarSolicitudDescuento($payload);

        // Normalizar la respuesta a array para uniformar el manejo.
        if ($resultado instanceof \Illuminate\Http\JsonResponse) {
            $resultado = $resultado->getData(true);
        }
        if (!is_array($resultado)) {
            return Response::json([
                'estado' => false,
                'existe' => false,
                'msj' => 'Error al verificar solicitud en central',
            ]);
        }

        Log::channel('single')->info('[solicitudDescuentoBackendVerificar] Response from central', [
            'resultado' => $resultado,
        ]);

        $aprobado = isset($resultado['existe']) && $resultado['existe']
            && isset($resultado['data']['estado']) && $resultado['data']['estado'] === 'aprobado';

        // Para método de pago, no aplicamos acá — la aplicación sucede en setPagoPedido.
        // Para check_only solo devolvemos el estado (hidratación del tracker en el front).
        if ($request->tipo_descuento !== 'monto_porcentaje' || !$aprobado || $request->boolean('check_only')) {
            return Response::json($resultado);
        }

        // Aprobado + monto_porcentaje → aplicar a items_pedidos del pedido.
        $idsProductos = $resultado['data']['ids_productos'] ?? [];
        if (!is_array($idsProductos) || count($idsProductos) === 0) {
            // Aprobado pero sin desglose: devolver tal cual; el front decidirá refrescar.
            return Response::json(array_merge($resultado, [
                'aplicado' => false,
                'msj_aplicacion' => 'Aprobada pero central no envió ids_productos para aplicar',
            ]));
        }

        $aplicados = 0;
        try {
            \DB::transaction(function () use ($idsProductos, $request, &$aplicados) {
                foreach ($idsProductos as $prod) {
                    $idProducto = isset($prod['id_producto']) ? (int) $prod['id_producto'] : null;
                    $porcentaje = isset($prod['porcentaje_descuento']) ? (float) $prod['porcentaje_descuento'] : null;
                    if (!$idProducto || $porcentaje === null || $porcentaje < 0) {
                        continue;
                    }
                    $afected = \App\Models\items_pedidos::where('id_pedido', (int) $request->id_pedido)
                        ->where('id_producto', $idProducto)
                        ->update(['descuento' => $porcentaje]);
                    $aplicados += (int) $afected;
                }
            });
        } catch (\Throwable $e) {
            Log::channel('single')->error('[solicitudDescuentoBackendVerificar] Error aplicando descuento', [
                'id_pedido' => $request->id_pedido,
                'error' => $e->getMessage(),
            ]);
            return Response::json([
                'estado' => false,
                'existe' => true,
                'data' => $resultado['data'] ?? null,
                'msj' => 'Aprobada en central pero falló la aplicación local: ' . $e->getMessage(),
            ]);
        }

        return Response::json(array_merge($resultado, [
            'aplicado' => $aplicados > 0,
            'items_actualizados' => $aplicados,
        ]));
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
