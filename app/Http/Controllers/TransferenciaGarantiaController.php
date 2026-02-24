<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use App\Http\Controllers\sendCentral;
use App\Http\Controllers\InventarioController;
use App\Models\inventario;
use App\Models\sucursal as SucursalModel;

/**
 * Módulo Transferencia de Garantía (Facturacion).
 * Proxy a Central + aplicación local cuando destino es DISPONIBLE_EN_TIENDA.
 */
class TransferenciaGarantiaController extends Controller
{
    /**
     * Listar órdenes (desde Central). Solo devuelve las que competen a esta sucursal (enviadas o recibidas).
     */
    public function index(Request $request)
    {
        $sendCentral = new sendCentral();
        $params = $request->only([
            'sucursal_origen_id', 'sucursal_destino_id', 'estado',
            'tipo_inventario_destino', 'tipo_inventario_origen',
            'proveedor_id',
            'fecha_desde', 'fecha_hasta', 'producto_search',
            'limit', 'page',
        ]);
        $params['sucursal_codigo'] = $sendCentral->getOrigen();
        $result = $sendCentral->ordenesTransferenciaGarantiaIndex($params);
        if (!$result['success']) {
            return Response::json(['success' => false, 'message' => $result['error'] ?? 'Error al listar'], 500);
        }
        $payload = $result['data'] ?? ['success' => true, 'data' => []];
        $payload['sucursal_codigo_actual'] = $sendCentral->getOrigen();
        return Response::json($payload);
    }

    /**
     * Crear orden (envío a Central).
     * Origen = esta sucursal por código (Central traduce a id); destino por id desde el listado de Central.
     */
    public function store(Request $request)
    {
        $sendCentral = new sendCentral();
        $data = $request->only([
            'sucursal_destino_id', 'proveedor_id', 'observaciones',
            'usuario_creacion_id', 'usuario_creacion_nombre', 'items'
        ]);
        $data['sucursal_origen_codigo'] = $sendCentral->getOrigen();
        $result = $sendCentral->ordenesTransferenciaGarantiaStore($data);
        if (!$result['success']) {
            $status = $result['status'] ?? 500;
            return Response::json(['success' => false, 'message' => $result['error'] ?? 'Error al crear orden'], $status);
        }
        return Response::json($result['data'] ?? ['success' => true]);
    }

    /**
     * Ver una orden (desde Central).
     */
    public function show($id)
    {
        $sendCentral = new sendCentral();
        $result = $sendCentral->ordenesTransferenciaGarantiaShow($id);
        if (!$result['success']) {
            return Response::json(['success' => false, 'message' => $result['error'] ?? 'Orden no encontrada'], 404);
        }
        return Response::json($result['data'] ?? ['success' => true, 'data' => null]);
    }

    /**
     * Aprobar orden (Central). Uso desde central; facturacion puede exponerlo si se llama desde central.
     */
    public function aprobar(Request $request, $id)
    {
        $sendCentral = new sendCentral();
        $data = $request->only(['usuario_aprobacion_id', 'usuario_aprobacion_nombre']);
        $result = $sendCentral->ordenesTransferenciaGarantiaAprobar($id, $data);
        if (!$result['success']) {
            return Response::json(['success' => false, 'message' => $result['error'] ?? 'Error al aprobar'], 500);
        }
        return Response::json($result['data'] ?? ['success' => true]);
    }

    /**
     * Rechazar orden (Central).
     */
    public function rechazar(Request $request, $id)
    {
        $sendCentral = new sendCentral();
        $data = $request->only(['motivo_rechazo']);
        $result = $sendCentral->ordenesTransferenciaGarantiaRechazar($id, $data);
        if (!$result['success']) {
            return Response::json(['success' => false, 'message' => $result['error'] ?? 'Error al rechazar'], 500);
        }
        return Response::json($result['data'] ?? ['success' => true]);
    }

    /**
     * Aceptar orden (sucursal receptora). Llama a Central para ejecutar movimientos y, si hay items
     * con tipo DISPONIBLE_EN_TIENDA, recarga inventario local con descontarInventario (origen TRANSF.GARANTIA.DISPONIBLE_TIENDA).
     */
    public function aceptar(Request $request, $id)
    {
        $request->validate([
            'sucursal_destino_id' => 'nullable|integer',
            'sucursal_destino_codigo' => 'nullable|string|max:50',
            'usuario_aceptacion_id' => 'nullable|integer',
            'usuario_aceptacion_nombre' => 'nullable|string|max:100',
        ]);

        $sendCentral = new sendCentral();
        // En Facturación identificamos la sucursal receptora por código
        $acceptData = [
            'sucursal_destino_codigo' => $request->filled('sucursal_destino_codigo') ? $request->sucursal_destino_codigo : $sendCentral->getOrigen(),
            'usuario_aceptacion_id' => $request->usuario_aceptacion_id,
            'usuario_aceptacion_nombre' => $request->usuario_aceptacion_nombre,
        ];
        if ($request->filled('sucursal_destino_id')) {
            $acceptData['sucursal_destino_id'] = (int) $request->sucursal_destino_id;
        }

        $ordenResult = $sendCentral->ordenesTransferenciaGarantiaShow($id);
        if (!$ordenResult['success'] || empty($ordenResult['data']['data'])) {
            return Response::json(['success' => false, 'message' => 'Orden no encontrada'], 404);
        }
        $orden = $ordenResult['data']['data'];
        // Central validará que sucursal_destino (por id o código) coincida con la orden
        $acceptResult = $sendCentral->ordenesTransferenciaGarantiaAceptar($id, $acceptData);
        if (!$acceptResult['success']) {
            return Response::json([
                'success' => false,
                'message' => $acceptResult['error'] ?? 'Error al aceptar la orden en central',
            ], $acceptResult['status'] ?? 500);
        }

        $responseData = $acceptResult['data']['data'] ?? $acceptResult['data'];
        $itemsConDisponible = $acceptResult['data']['items_con_disponible_en_tienda'] ?? [];
        if (empty($itemsConDisponible) && !empty($responseData['items'])) {
            $itemsConDisponible = array_filter($responseData['items'], function ($it) {
                return ($it['tipo_inventario_destino'] ?? '') === 'DISPONIBLE_EN_TIENDA';
            });
        }

        $invController = new InventarioController();
        $erroresLocal = [];
        foreach ($itemsConDisponible as $item) {
            $cantidadSumar = (int) ($item['cantidad'] ?? 0);
            if ($cantidadSumar <= 0) {
                continue;
            }
            $producto = $item['producto'] ?? null;
            $codigoBarras = $producto['codigo_barras'] ?? null;
            $idCentral = $producto['id'] ?? $item['producto_id'] ?? null;
            $invLocal = null;
            if ($codigoBarras) {
                $invLocal = inventario::where('codigo_barras', $codigoBarras)->first();
            }
            if (!$invLocal && $idCentral) {
                $invLocal = inventario::find($idCentral);
            }
            if (!$invLocal) {
                $erroresLocal[] = 'Producto no encontrado en inventario local (cb: ' . ($codigoBarras ?? '') . ', id_central: ' . $idCentral . ')';
                continue;
            }
            $cantidadActual = (float) $invLocal->cantidad;
            $nuevaCantidad = $cantidadActual + $cantidadSumar;
            try {
                // descontarInventario(id, cantidad_nueva, cantidad_antes, id_pedido, origen)
                // setNewCtMov registra movimiento = cantidadafter - ct1; debe ser +cantidadSumar
                $invController->descontarInventario(
                    $invLocal->id,
                    $nuevaCantidad,
                    $cantidadActual,
                    null,
                    'TRANSF.GARANTIA.DISPONIBLE_TIENDA'
                );
            } catch (\Exception $e) {
                $erroresLocal[] = 'Producto ' . ($invLocal->descripcion ?? $invLocal->id) . ': ' . $e->getMessage();
            }
        }

        $payload = [
            'success' => true,
            'message' => 'Orden aceptada y movimientos registrados en central.',
            'data' => $responseData,
        ];
        if (!empty($erroresLocal)) {
            $payload['warnings'] = $erroresLocal;
            $payload['message'] .= ' Algunos ítems no pudieron cargarse al inventario local: ' . implode('; ', $erroresLocal);
        }
        return Response::json($payload);
    }

    /**
     * Confirmar recepción de un ítem (o cantidad parcial). Proxy a Central y, si el ítem es DISPONIBLE_EN_TIENDA, actualiza inventario local.
     */
    public function recibirItem(Request $request, $id)
    {
        $sendCentral = new sendCentral();
        $data = $request->only(['item_id', 'cantidad']);
        $data['sucursal_destino_codigo'] = $sendCentral->getOrigen();
        $data['usuario_aceptacion_nombre'] = $request->input('usuario_aceptacion_nombre', 'Usuario');

        $result = $sendCentral->ordenesTransferenciaGarantiaRecibirItem($id, $data);
        if (!$result['success']) {
            $status = $result['status'] ?? 500;
            $err = $result['error'] ?? '';
            if (is_string($err) && trim($err) !== '' && (trim($err)[0] === '{' || trim($err)[0] === '[')) {
                $dec = json_decode($err, true);
                $message = $dec['message'] ?? $dec['error'] ?? $err;
            } else {
                $message = is_array($err) ? ($err['message'] ?? json_encode($err)) : $err;
            }
            return Response::json(['success' => false, 'message' => $message], $status >= 400 ? $status : 500);
        }

        $responseData = $result['data']['data'] ?? $result['data'];
        $itemRecibido = $result['data']['item_recibido'] ?? null;
        $warnings = [];
        if ($itemRecibido && strtoupper($itemRecibido['tipo_inventario_destino'] ?? '') === 'DISPONIBLE_EN_TIENDA') {
            $cantidadSumar = (int) ($itemRecibido['cantidad_recibida_esta_vez'] ?? 0);
            if ($cantidadSumar > 0) {
                $producto = $itemRecibido['producto'] ?? null;
                $codigoBarras = $producto['codigo_barras'] ?? null;
                $idCentral = $producto['id'] ?? $itemRecibido['producto_id'] ?? null;
                $invLocal = null;
                if ($codigoBarras) {
                    $invLocal = inventario::where('codigo_barras', $codigoBarras)->first();
                }
                if (!$invLocal && $idCentral) {
                    $invLocal = inventario::find($idCentral);
                }
                if ($invLocal) {
                    try {
                        $invController = new InventarioController();
                        $cantidadActual = (float) $invLocal->cantidad;
                        $nuevaCantidad = $cantidadActual + $cantidadSumar;
                        $invController->descontarInventario(
                            $invLocal->id,
                            $nuevaCantidad,
                            $cantidadActual,
                            null,
                            'TRANSF.GARANTIA.DISPONIBLE_TIENDA'
                        );
                    } catch (\Exception $e) {
                        $warnings[] = 'Inventario local: ' . $e->getMessage();
                    }
                }
            }
        }

        $payload = [
            'success' => true,
            'message' => $result['data']['message'] ?? 'Recepción registrada.',
            'data' => $responseData,
            'orden_completa' => $result['data']['orden_completa'] ?? false,
        ];
        if (!empty($warnings)) {
            $payload['warnings'] = $warnings;
        }
        return Response::json($payload);
    }

    /**
     * Confirmar devolución por proveedor (orden ya aceptada con destino TRANSFERIDO_AL_PROVEEDOR).
     * Redirige ítems a DISPONIBLE_EN_TIENDA, RECUPERADO, DAÑADO, etc.
     */
    public function confirmarDevolucionProveedor(Request $request, $id)
    {
        $sendCentral = new sendCentral();
        $data = $request->only(['items', 'usuario_nombre']);
        $data['usuario_nombre'] = $data['usuario_nombre'] ?? 'Usuario';
        $result = $sendCentral->ordenesTransferenciaGarantiaConfirmarDevolucionProveedor($id, $data);
        if (!$result['success']) {
            $status = $result['status'] ?? 500;
            $err = $result['error'] ?? '';
            if (is_string($err) && trim($err) !== '' && (trim($err)[0] === '{' || trim($err)[0] === '[')) {
                $dec = json_decode($err, true);
                $message = $dec['message'] ?? $dec['error'] ?? $err;
            } else {
                $message = is_array($err) ? ($err['message'] ?? json_encode($err)) : $err;
            }
            return Response::json(['success' => false, 'message' => $message], $status >= 400 ? $status : 500);
        }
        return Response::json($result['data'] ?? ['success' => true]);
    }

    /**
     * Inventario disponible para armar orden (desde Central).
     * La sucursal se identifica por código; Central traduce a id (igual que el resto del módulo de garantía).
     */
    public function inventarioDisponible(Request $request)
    {
        $sendCentral = new sendCentral();
        $params = array_merge(
            ['sucursal_codigo' => $sendCentral->getOrigen()],
            $request->only(['tipo_inventario', 'producto_search'])
        );
        $result = $sendCentral->ordenesTransferenciaGarantiaInventarioDisponible($params);
        if (!$result['success']) {
            return Response::json(['success' => false, 'message' => $result['error'] ?? 'Error'], 500);
        }
        return Response::json($result['data'] ?? ['success' => true, 'data' => []]);
    }

    /**
     * Tipos de inventario (desde Central).
     */
    public function tipos()
    {
        $sendCentral = new sendCentral();
        $result = $sendCentral->ordenesTransferenciaGarantiaTipos();
        if (!$result['success']) {
            return Response::json(['success' => false, 'message' => $result['error'] ?? 'Error'], 500);
        }
        return Response::json($result['data'] ?? ['success' => true, 'data' => []]);
    }

    /**
     * Reporte en Blade tamaño carta: lo enviado en una orden de transferencia de garantía.
     * GET (web) reportes/transferencia-garantia/{id}
     */
    public function reporte($id)
    {
        $sendCentral = new sendCentral();
        $result = $sendCentral->ordenesTransferenciaGarantiaShow($id);
        if (!$result['success']) {
            abort(404, 'Orden de transferencia no encontrada');
        }
        // Central devuelve { success, data: orden }; $response->json() = ['success' => true, 'data' => orden]
        $payload = $result['data'];
        $orden = isset($payload['data']) ? $payload['data'] : $payload;
        if (empty($orden) || !isset($orden['id'])) {
            abort(404, 'Orden de transferencia no encontrada');
        }
        // Normalizar a array y unificar camelCase/snake_case para la vista
        $toArray = function ($v) {
            if (is_object($v)) {
                return json_decode(json_encode($v), true);
            }
            return $v;
        };
        $orden['sucursal_origen'] = $orden['sucursal_origen'] ?? $orden['sucursalOrigen'] ?? null;
        $orden['sucursal_destino'] = $orden['sucursal_destino'] ?? $orden['sucursalDestino'] ?? null;
        $orden['items'] = $orden['items'] ?? [];
        $orden['proveedor'] = $orden['proveedor'] ?? null;
        $orden['sucursal_origen'] = $toArray($orden['sucursal_origen']);
        $orden['sucursal_destino'] = $toArray($orden['sucursal_destino']);
        $orden['proveedor'] = $toArray($orden['proveedor']);
        $items = $toArray($orden['items']);
        $orden['items'] = is_array($items) ? array_map(function ($it) use ($toArray) {
            $it = is_array($it) ? $it : (array) $it;
            if (!empty($it['producto'])) {
                $it['producto'] = $toArray($it['producto']);
            }
            return $it;
        }, $items) : [];
        $sucursal = SucursalModel::all()->first();
        return view('reportes.transferencia-garantia-reporte', [
            'orden' => $orden,
            'sucursal' => $sucursal,
        ]);
    }
}
