<?php

namespace App\Http\Controllers;

use App\Models\TCRAsignacion;
use App\Models\TCRNovedad;
use App\Models\TCRNovedadHistorial;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Models\WarehouseMovement;
use App\Models\inventario;
use App\Http\Controllers\sendCentral;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Response;

class TCRController extends Controller
{
    /**
     * Normalizar código de ubicación: reemplazar caracteres no alfanuméricos por guiones
     */
    private function normalizarCodigoUbicacion($codigo)
    {
        if (!$codigo) {
            return $codigo;
        }
        
        // Reemplazar todo lo que no sea letra o número por guiones
        $normalizado = preg_replace('/[^a-zA-Z0-9]/', '-', $codigo);
        // Reemplazar múltiples guiones consecutivos por uno solo
        $normalizado = preg_replace('/-+/', '-', $normalizado);
        // Eliminar guiones al inicio y final
        $normalizado = trim($normalizado, '-');
        
        return $normalizado;
    }

    /**
     * Vista del chequeador - Asignar productos a pasilleros
     */
    public function chequeador()
    {
        return view('warehouse-inventory.tcr.chequeador');
    }
    
    /**
     * Vista del pasillero - Interfaz sencilla de escaneo
     */
    public function pasillero()
    {
        return view('warehouse-inventory.tcr.pasillero');
    }
    
    /**
     * Obtener pedidos de arabitocentral (similar a reqpedidos)
     */
    public function getPedidosCentral(Request $request)
    {

        try {
            $sendCentral = new sendCentral();
            $codigo_origen = $sendCentral->getOrigen();
            
            $response = \Http::post($sendCentral->path() . '/respedidos', [
                "codigo_origen" => $codigo_origen,
                "qpedidoscentralq" => $request->qpedidoscentralq ?? '',
                "qpedidocentrallimit" => $request->qpedidocentrallimit ?? '20',
                "qpedidocentralestado" => $request->qpedidocentralestado, // Solo revisados
                "qpedidocentralemisor" => $request->qpedidocentralemisor ?? '',
            ]);
            
            if ($response->ok()) {
                $res = $response->json();
                if (isset($res["pedido"])) {
                    $pedidos = $res["pedido"];
                    
                    // Agregar información de asignaciones existentes
                    foreach ($pedidos as $pedidokey => $pedido) {
                        foreach ($pedido["items"] as $keyitem => $item) {
                            // Verificar si ya tiene asignaciones
                            $asignaciones = TCRAsignacion::where('pedido_central_id', $pedido['id'])
                                ->where('item_pedido_id', $item['id'])
                                ->get();
                            
                            $pedidos[$pedidokey]["items"][$keyitem]["asignaciones"] = $asignaciones;
                            // cantidad_asignada_total: suma de lo que el chequeador asignó (cantidad)
                            $pedidos[$pedidokey]["items"][$keyitem]["cantidad_asignada_total"] = $asignaciones->sum('cantidad');
                            // cantidad_pendiente: lo que aún se puede asignar (cantidad total - cantidad asignada por chequeador)
                            $pedidos[$pedidokey]["items"][$keyitem]["cantidad_pendiente"] = $item['cantidad'] - $asignaciones->sum('cantidad');
                        }
                    }
                    
                    return Response::json($pedidos);
                } else {
                    return Response::json(["estado" => false, "msj" => "No se encontraron pedidos"]);
                }
            } else {
                return Response::json(["estado" => false, "msj" => "Error: " . $response->body()]);
            }
        } catch (\Exception $e) {
            return Response::json(["estado" => false, "msj" => "Error: " . $e->getMessage()]);
        }
    }
    
    /**
     * Obtener usuarios pasilleros disponibles
     */
    public function getPasilleros()
    {
        // Obtener todos los usuarios (puedes filtrar por tipo_usuario si tienes un tipo específico para pasilleros)
        // Por ahora retornamos todos los usuarios excepto administradores (tipo_usuario = 1)
        $pasilleros = \App\Models\usuarios::where('tipo_usuario', '=', 8)
            ->select('id', 'nombre', 'usuario', 'tipo_usuario')
            ->orderBy('nombre')
            ->get();
        
        return Response::json([
            'estado' => true,
            'pasilleros' => $pasilleros
        ]);
    }
    
    /**
     * Asignar productos a pasilleros (chequeador)
     */
    public function asignarProductos(Request $request)
    {

        
        $request->validate([
            'pedido_id' => 'required',
            'items' => 'required|array',
            'items.*.item_id' => 'required|integer',
            'items.*.pasillero_id' => 'required|integer',
            'items.*.cantidad' => 'required|numeric|min:0.01',
        ]);
        
        try {
            DB::beginTransaction();
            
            $chequeadorId = session('id_usuario');
            if (!$chequeadorId) {
                throw new \Exception('Usuario no autenticado');
            }
            $asignacionesCreadas = [];
            
            // Obtener información del pedido una sola vez
            $pedidos = $this->getPedidosCentralData($request->pedido_id);
            if (!$pedidos || !isset($pedidos['items'])) {
                throw new \Exception('No se pudo obtener información del pedido');
            }
            
            // Primero, validar todas las cantidades antes de crear ninguna asignación
            // Agrupar items por item_id para sumar las cantidades que se van a asignar
            $itemsPorProducto = [];
            foreach ($request->items as $item) {
                $itemPedido = collect($pedidos['items'])->firstWhere('id', $item['item_id']);
                
                if (!$itemPedido) {
                    continue; // Saltar si no se encuentra el item
                }
                
                // Agrupar por item_id para sumar todas las cantidades que se van a asignar
                if (!isset($itemsPorProducto[$item['item_id']])) {
                    $itemsPorProducto[$item['item_id']] = [
                        'item_pedido' => $itemPedido,
                        'cantidad_total_a_asignar' => 0,
                        'items' => []
                    ];
                }
                
                $itemsPorProducto[$item['item_id']]['cantidad_total_a_asignar'] += $item['cantidad'];
                $itemsPorProducto[$item['item_id']]['items'][] = $item;
            }
            
            // Validar todas las cantidades antes de crear asignaciones
            foreach ($itemsPorProducto as $itemId => $data) {
                $itemPedido = $data['item_pedido'];
                $cantidadTotalAAsignar = $data['cantidad_total_a_asignar'];
                
                // Obtener asignaciones existentes para este item
                $asignacionesExistentes = TCRAsignacion::where('pedido_central_id', $request->pedido_id)
                    ->where('item_pedido_id', $itemId)
                    ->get();
                
                $cantidadAsignadaTotal = $asignacionesExistentes->sum('cantidad'); // Suma de lo asignado por el chequeador
                $cantidadDisponible = $itemPedido['cantidad'] - $cantidadAsignadaTotal;
                
                if ($cantidadTotalAAsignar > $cantidadDisponible) {
                    throw new \Exception("Cantidad excede lo disponible para el producto '{$itemPedido['producto']['descripcion']}'. Disponible: {$cantidadDisponible}, Intentando asignar: {$cantidadTotalAAsignar}");
                }
                
                if ($cantidadDisponible <= 0) {
                    throw new \Exception("No hay cantidad disponible para el producto '{$itemPedido['producto']['descripcion']}'. Ya se asignaron todas las unidades.");
                }
            }
            
            // Si todas las validaciones pasaron, crear las asignaciones
            foreach ($request->items as $item) {
                $itemPedido = collect($pedidos['items'])->firstWhere('id', $item['item_id']);
                
                if (!$itemPedido) {
                    continue; // Saltar si no se encuentra el item
                }
                
                // Crear asignación
                $asignacion = TCRAsignacion::create([
                    'pedido_central_id' => $request->pedido_id,
                    'item_pedido_id' => $item['item_id'],
                    'codigo_barras' => $itemPedido['producto']['codigo_barras'] ?? null,
                    'codigo_proveedor' => $itemPedido['producto']['codigo_proveedor'] ?? null,
                    'descripcion' => $itemPedido['producto']['descripcion'] ?? 'Sin descripción',
                    'cantidad' => $item['cantidad'],
                    'cantidad_asignada' => 0,
                    'chequeador_id' => $chequeadorId,
                    'pasillero_id' => $item['pasillero_id'],
                    'estado' => 'pendiente',
                    'precio_base' => $itemPedido['precio_base'] ?? null,
                    'precio_venta' => $itemPedido['precio'] ?? null,
                    'sucursal_origen' => $pedidos['origen']['codigo'] ?? null,
                ]);
                
                $asignacionesCreadas[] = $asignacion;
            }
            
            DB::commit();
            
            return Response::json([
                'estado' => true,
                'msj' => 'Productos asignados exitosamente',
                'asignaciones' => $asignacionesCreadas
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return Response::json([
                'estado' => false,
                'msj' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener asignaciones del pasillero actual agrupadas por pedido
     */
    public function getMisAsignaciones(Request $request)
    {
        $pasilleroId = session('id_usuario');
        if (!$pasilleroId) {
            return Response::json([
                'estado' => false,
                'msj' => 'Usuario no autenticado'
            ], 401);
        }
        $estado = $request->estado ?? 'pendiente';
        
        // Obtener asignaciones agrupadas por pedido
        $asignaciones = TCRAsignacion::porPasillero($pasilleroId)
            ->whereIn('estado', ['pendiente', 'completado', 'en_proceso'])
            ->with(['warehouse'])
            ->orderBy('pedido_central_id')
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Agrupar por pedido
        $asignacionesPorPedido = $asignaciones->groupBy('pedido_central_id')->map(function($asigs, $pedidoId) {
            return [
                'pedido_id' => $pedidoId,
                'total_asignaciones' => $asigs->count(),
                'completadas' => $asigs->where('estado', 'completado')->count(),
                'pendientes' => $asigs->where('estado', 'pendiente')->count(),
                'en_proceso' => $asigs->where('estado', 'en_proceso')->count(),
                'asignaciones' => $asigs->values()
            ];
        })->values();
        
        return Response::json([
            'estado' => true,
            'asignaciones_por_pedido' => $asignacionesPorPedido,
            'asignaciones' => $asignaciones // Mantener compatibilidad
        ]);
    }
    
    /**
     * Procesar asignación - Pasillero escanea ubicación, producto y cantidad
     */
    public function procesarAsignacion(Request $request)
    {
        $request->validate([
            'asignacion_id' => 'required|exists:tcr_asignaciones,id',
            'codigo_ubicacion' => 'required|string',
            'codigo_producto' => 'required|string',
            'cantidad' => 'required|numeric|min:0.01',
        ]);
        
        try {
            DB::beginTransaction();
            
            $asignacion = TCRAsignacion::findOrFail($request->asignacion_id);
            
            // Verificar que el pasillero sea el asignado
            $pasilleroId = session('id_usuario');
            if (!$pasilleroId) {
                throw new \Exception('Usuario no autenticado');
            }
            if ($asignacion->pasillero_id != $pasilleroId) {
                throw new \Exception('No tienes permiso para procesar esta asignación');
            }
            
            // Verificar código de producto
            $codigoProducto = strtoupper(trim($request->codigo_producto));
            $codigoBarras = strtoupper(trim($asignacion->codigo_barras ?? ''));
            $codigoProveedor = strtoupper(trim($asignacion->codigo_proveedor ?? ''));
            
            if ($codigoProducto !== $codigoBarras && $codigoProducto !== $codigoProveedor) {
                throw new \Exception('El código del producto no coincide');
            }
            
            // Buscar ubicación
            $codigoUbicacion = $this->normalizarCodigoUbicacion($request->codigo_ubicacion);
            $warehouse = Warehouse::where('codigo', $codigoUbicacion)
                ->where('estado', 'activa')
                ->first();
            
            if (!$warehouse) {
                throw new \Exception('Ubicación no encontrada o no está activa');
            }
            
            // Verificar cantidad
            $cantidadPendiente = $asignacion->cantidadPendiente();
            if ($request->cantidad > $cantidadPendiente) {
                throw new \Exception("Cantidad excede lo pendiente. Pendiente: {$cantidadPendiente}");
            }
            
            // NOTA: No buscamos el producto en el inventario local aquí porque
            // el producto aún no existe en el inventario local (solo existe en arabitocentral).
            // El producto se agregará al inventario local cuando el chequeador confirme el pedido.
            
            // Actualizar asignación - queda en "completado" cuando está completa, o "en_proceso" si está parcial
            $asignacion->cantidad_asignada += $request->cantidad;
            $asignacion->warehouse_codigo = $warehouse->codigo;
            $asignacion->warehouse_id = $warehouse->id;
            // Si está completo, queda en "completado" para revisión del chequeador
            // Si no está completo, queda en "en_proceso"
            $asignacion->estado = $asignacion->estaCompleto() ? 'completado' : 'en_proceso';
            $asignacion->save();
            
            // NO crear warehouse_inventory todavía - se creará cuando el chequeador confirme
            // NO registrar movimiento todavía - se registrará cuando el chequeador confirme
            
            DB::commit();
            
            return Response::json([
                'estado' => true,
                'msj' => 'Asignación procesada exitosamente. Esperando confirmación del chequeador.',
                'asignacion' => $asignacion->fresh(),
                'completo' => $asignacion->estaCompleto(),
                'completado' => $asignacion->estado === 'completado'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return Response::json([
                'estado' => false,
                'msj' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener asignaciones por pedido para el chequeador
     */
    public function getAsignacionesPorPedido(Request $request)
    {
        $chequeadorId = session('id_usuario');
        if (!$chequeadorId) {
            return Response::json([
                'estado' => false,
                'msj' => 'Usuario no autenticado'
            ], 401);
        }
        
        $pedidoId = $request->pedido_id;
        if (!$pedidoId) {
            return Response::json([
                'estado' => false,
                'msj' => 'ID de pedido requerido'
            ]);
        }
        
        // Obtener todas las asignaciones del pedido (de todos los pasilleros asignados por este chequeador)
        $asignaciones = TCRAsignacion::where('pedido_central_id', $pedidoId)
            ->where('chequeador_id', $chequeadorId)
            ->with(['pasillero', 'warehouse'])
            ->orderBy('pasillero_id')
            ->orderBy('created_at')
            ->get();
        
        // Agrupar por pasillero
        $asignacionesPorPasillero = $asignaciones->groupBy('pasillero_id')->map(function($asigs, $pasilleroId) {
            $pasillero = $asigs->first()->pasillero;
            return [
                'pasillero_id' => $pasilleroId,
                'pasillero_nombre' => $pasillero->nombre ?? 'N/A',
                'total_asignaciones' => $asigs->count(),
                'completadas' => $asigs->where('estado', 'completado')->count(),
                'pendientes' => $asigs->where('estado', 'pendiente')->count(),
                'en_proceso' => $asigs->where('estado', 'en_proceso')->count(),
                'asignaciones' => $asigs->values()
            ];
        })->values();
        
        // Verificar si todas las asignaciones están completadas
        $todasListas = $asignaciones->every(function($asig) {
            return $asig->estado === 'completado';
        });
        
        return Response::json([
            'estado' => true,
            'pedido_id' => $pedidoId,
            'asignaciones_por_pedido' => $asignacionesPorPasillero,
            'total_asignaciones' => $asignaciones->count(),
            'todas_listas' => $todasListas,
            'resumen' => [
                'pendientes' => $asignaciones->where('estado', 'pendiente')->count(),
                'en_proceso' => $asignaciones->where('estado', 'en_proceso')->count(),
                'completadas' => $asignaciones->where('estado', 'completado')->count(),
            ]
        ]);
    }
    
    /**
     * Confirmar y guardar pedido completo (chequeador)
     * Si recibe 'asignaciones' en el request, procesa las asignaciones TCR antes de llamar a checkPedidosCentral
     */
    public function confirmarPedido(Request $request)
    {
        $tipo_usuario = session('tipo_usuario');
        if ($tipo_usuario != 7) {
            return Response::json(['msj' => '¡No tienes permisos para verificar pedidos central, solo DICI!', 'estado' => false]);
        }
        
        $pedidoId = $request->pedido_id ?? $request->pedido['id'] ?? null;
        
        if (!$pedidoId) {
            return Response::json([
                'estado' => false,
                'msj' => 'ID de pedido requerido'
            ]);
        }
        
        try {
            DB::beginTransaction();
            
            // Obtener el pedido completo desde central
            $sendCentral = new sendCentral();
            $getPedido = $sendCentral->getPedidoCentralImport($pedidoId);
            
            if (!isset($getPedido['estado']) || $getPedido['estado'] !== true || !isset($getPedido['pedido'])) {
                throw new \Exception('Error al obtener pedido desde central: ' . ($getPedido['msj'] ?? 'Error desconocido'));
            }
            
            $pedidoCentral = $getPedido['pedido'];
            
            // Validar que el pedido tenga items
            if (!isset($pedidoCentral['items']) || !is_array($pedidoCentral['items']) || empty($pedidoCentral['items'])) {
                throw new \Exception('El pedido no tiene items para procesar');
            }
            
            // Obtener todas las asignaciones TCR del pedido para mapear warehouse_codigo
            $asignacionesTCR = TCRAsignacion::where('pedido_central_id', $pedidoId)
                ->where('chequeador_id', session('id_usuario'))
                ->where('estado', 'completado')
                ->with('warehouse')
                ->get();
            
            // Crear un mapa de item_id -> warehouse_codigo
            $warehouseMap = [];
            foreach ($asignacionesTCR as $asig) {
                $itemId = $asig->item_pedido_id;
                if ($asig->warehouse && $asig->warehouse->codigo) {
                    if (!isset($warehouseMap[$itemId])) {
                        $warehouseMap[$itemId] = [];
                    }
                    // Si hay múltiples asignaciones para el mismo item, usar la primera
                    if (empty($warehouseMap[$itemId])) {
                        $warehouseMap[$itemId] = $asig->warehouse->codigo;
                    }
                }
            }
            
            // Preparar los items del pedido con la información necesaria para checkPedidosCentral
            $itemsPreparados = [];
            foreach ($pedidoCentral['items'] as $item) {
                $itemId = $item['id'];
                
                // Buscar producto en inventario local por código de barras o proveedor
                $productoLocal = inventario::where('codigo_barras', $item['producto']['codigo_barras'] ?? '')
                    ->orWhere('codigo_proveedor', $item['producto']['codigo_proveedor'] ?? '')
                    ->first();
                
                $vinculoReal = $productoLocal ? $productoLocal->id : null;
                $idinsucursalVinculo = $vinculoReal;
                
                // Preparar item con formato que espera checkPedidosCentral
                $itemPreparado = [
                    'id' => $itemId,
                    'producto' => $item['producto'],
                    'cantidad' => $item['cantidad'],
                    'base' => $item['base'] ?? $item['producto']['precio_base'] ?? 0,
                    'venta' => $item['venta'] ?? $item['producto']['precio'] ?? 0,
                    'aprobado' => true, // Todos aprobados porque ya pasaron por el proceso TCR
                    'vinculo_real' => $vinculoReal,
                    'idinsucursal_vinculo' => $idinsucursalVinculo,
                    'barras_real' => $item['producto']['codigo_barras'] ?? null,
                    'warehouse_codigo' => $warehouseMap[$itemId] ?? null, // Agregar warehouse_codigo si existe
                ];
                
                $itemsPreparados[] = $itemPreparado;
            }
            
            // Validar que haya items preparados
            if (empty($itemsPreparados)) {
                throw new \Exception('No se pudieron preparar los items del pedido. Items encontrados: ' . count($pedidoCentral['items']));
            }
            
            // Preparar el pedido en el formato que espera checkPedidosCentral
            $pedidoPreparado = [
                'id' => $pedidoCentral['id'],
                'id_origen' => $pedidoCentral['origen']['id'] ?? null,
                'items' => $itemsPreparados
            ];
            
            // Verificar que el pedido preparado tenga la estructura correcta
            if (!isset($pedidoPreparado['items']) || empty($pedidoPreparado['items'])) {
                throw new \Exception('El pedido preparado no tiene items. Items preparados: ' . count($itemsPreparados));
            }
            
            // Crear un nuevo Request con los datos preparados usando merge para que se acceda correctamente
            $requestPreparado = new Request();
            $requestPreparado->merge([
                'pedido' => $pedidoPreparado,
                'pathcentral' => $sendCentral->path()
            ]);
            
            // Verificar que el request tenga los datos correctos
            $pedidoEnRequest = $requestPreparado->input('pedido');
            if (!$pedidoEnRequest || !isset($pedidoEnRequest['items']) || empty($pedidoEnRequest['items'])) {
                throw new \Exception('Error al preparar el request. Items en request: ' . (isset($pedidoEnRequest['items']) ? count($pedidoEnRequest['items']) : 0));
            }
            
            // Llamar al método checkPedidosCentral del InventarioController
            $inventarioController = new \App\Http\Controllers\InventarioController();
            $resultado = $inventarioController->checkPedidosCentral($requestPreparado);
            
            // Si el resultado es un array con 'estado', retornarlo directamente
            if (is_array($resultado) && isset($resultado['estado'])) {
                if ($resultado['estado'] === false) {
                    DB::rollBack();
                } else {
                    DB::commit();
                }
                return Response::json($resultado);
            }
            
            // Si es un string (mensaje de error), retornarlo como error
            if (is_string($resultado)) {
                DB::rollBack();
                return Response::json([
                    'estado' => false,
                    'msj' => $resultado
                ]);
            }
            
            // Si todo salió bien, hacer commit
            DB::commit();
            
            return Response::json([
                'estado' => true,
                'msj' => 'Pedido procesado exitosamente'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return Response::json([
                'estado' => false,
                'msj' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Buscar ubicación por código
     */
    public function buscarUbicacion(Request $request)
    {
        $codigo = $request->codigo;
        
        if (!$codigo) {
            return Response::json([
                'estado' => false,
                'msj' => 'Código requerido'
            ]);
        }
        
        // Normalizar código de ubicación
        $codigo = $this->normalizarCodigoUbicacion($codigo);
        
        $warehouse = Warehouse::where('codigo', $codigo)
            ->where('estado', 'activa')
            ->first();
        
        if ($warehouse) {
            return Response::json([
                'estado' => true,
                'ubicacion' => $warehouse
            ]);
        } else {
            return Response::json([
                'estado' => false,
                'msj' => 'Ubicación no encontrada o no está activa'
            ]);
        }
    }
    
    /**
     * Helper para obtener datos del pedido
     */
    private function getPedidosCentralData($pedidoId)
    {
        try {
            $sendCentral = new sendCentral();
            $codigo_origen = $sendCentral->getOrigen();
            
            $response = \Http::post($sendCentral->path() . '/respedidos', [
                "codigo_origen" => $codigo_origen,
                "qpedidoscentralq" => $pedidoId,
                "qpedidocentrallimit" => 1,
                "qpedidocentralestado" => "",
                "qpedidocentralemisor" => '',
            ]);
            
            if ($response->ok()) {
                $res = $response->json();
                if (isset($res["pedido"]) && count($res["pedido"]) > 0) {
                    return $res["pedido"][0];
                }
            }
            
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Buscar o registrar novedad de producto
     */
    public function buscarORegistrarNovedad(Request $request)
    {
        $request->validate([
            'codigo' => 'required|string',
            'cantidad_llego' => 'nullable|numeric|min:0',
            'pedido_id' => 'nullable|integer',
            'item_pedido_id' => 'nullable|integer',
        ]);
        
        try {
            DB::beginTransaction();
            
            $pasilleroId = session('id_usuario');
            if (!$pasilleroId) {
                throw new \Exception('Usuario no autenticado');
            }
            
            $codigo = strtoupper(trim($request->codigo));
            
            // Buscar producto en inventario por código de barras o proveedor
            $productoInventario = inventario::where(function($query) use ($codigo) {
                $query->where('codigo_barras', $codigo)
                      ->orWhere('codigo_proveedor', $codigo);
            })->first();
            
            // Buscar si ya existe una novedad para este producto
            // Si el producto existe en inventario, buscar por inventario_id (más preciso)
            // Si no existe en inventario, buscar por código escaneado
            $novedadExistente = null;
            
            if ($productoInventario) {
                // Buscar por inventario_id primero (más preciso)
                $novedadExistente = TCRNovedad::where('inventario_id', $productoInventario->id)
                    ->where('pasillero_id', $pasilleroId)
                    ->where('estado', 'pendiente')
                    ->first();
                
                // Si no se encuentra por inventario_id, buscar por códigos del inventario
                if (!$novedadExistente) {
                    $novedadExistente = TCRNovedad::where(function($query) use ($productoInventario) {
                        $query->where(function($q) use ($productoInventario) {
                            if ($productoInventario->codigo_barras) {
                                $q->where('codigo_barras', $productoInventario->codigo_barras);
                            }
                            if ($productoInventario->codigo_proveedor) {
                                $q->orWhere('codigo_proveedor', $productoInventario->codigo_proveedor);
                            }
                        });
                    })
                    ->where('pasillero_id', $pasilleroId)
                    ->where('estado', 'pendiente')
                    ->first();
                }
            } else {
                // Si no existe en inventario, buscar por código escaneado
                $novedadExistente = TCRNovedad::where(function($query) use ($codigo) {
                    $query->where('codigo_barras', $codigo)
                          ->orWhere('codigo_proveedor', $codigo);
                })
                ->where('pasillero_id', $pasilleroId)
                ->where('estado', 'pendiente')
                ->first();
            }
            
            if ($novedadExistente) {
                // Si existe, actualizar la cantidad que llegó sumando la nueva cantidad
                $cantidadAnterior = $novedadExistente->cantidad_llego;
                $cantidadNueva = $request->cantidad_llego ?? 0;
                
                // Si es negativo, validar que no exceda la cantidad existente
                if ($cantidadNueva < 0) {
                    $valorAbsoluto = abs($cantidadNueva);
                    if ($valorAbsoluto > $cantidadAnterior) {
                        throw new \Exception("No puede restar más de {$cantidadAnterior} (cantidad actual)");
                    }
                }
                
                $novedadExistente->cantidad_llego += $cantidadNueva;
                
                // Asegurar que no sea negativa
                if ($novedadExistente->cantidad_llego < 0) {
                    $novedadExistente->cantidad_llego = 0;
                }
                
                // Recalcular diferencia
                $novedadExistente->diferencia = $novedadExistente->cantidad_llego - $novedadExistente->cantidad_enviada;
                
                // Actualizar observaciones si hay nuevas
                if ($request->observaciones) {
                    $observacionesAnteriores = $novedadExistente->observaciones ? $novedadExistente->observaciones . ' | ' : '';
                    $novedadExistente->observaciones = $observacionesAnteriores . $request->observaciones;
                }
                
                $novedadExistente->save();
                
                // Registrar movimiento en historial
                DB::table('tcr_novedades_historial')->insert([
                    'novedad_id' => $novedadExistente->id,
                    'cantidad_agregada' => $cantidadNueva,
                    'cantidad_total' => $novedadExistente->cantidad_llego,
                    'pasillero_id' => $pasilleroId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                // Cargar historial actualizado
                $novedadExistente->load(['historial' => function($query) {
                    $query->orderBy('created_at', 'desc');
                }]);
                
                DB::commit();
                
                return Response::json([
                    'estado' => true,
                    'novedad' => $novedadExistente,
                    'existe' => true,
                    'msj' => "Cantidad actualizada: +{$cantidadNueva} (Total: {$novedadExistente->cantidad_llego})"
                ]);
            }
            
            // Si no existe, crear nueva novedad
            $novedad = new TCRNovedad();
            
            // Si el producto existe en inventario, usar sus datos
            if ($productoInventario) {
                $novedad->codigo_barras = $productoInventario->codigo_barras;
                $novedad->codigo_proveedor = $productoInventario->codigo_proveedor;
                $novedad->descripcion = $productoInventario->descripcion;
                $novedad->inventario_id = $productoInventario->id;
            } else {
                // Si no existe, usar el código escaneado
                // Intentar determinar si es código de barras o proveedor (por ahora, guardar en ambos)
                $novedad->codigo_barras = $codigo;
                $novedad->codigo_proveedor = $codigo;
                $novedad->descripcion = 'Producto no encontrado - Código: ' . $codigo;
                $novedad->inventario_id = null;
            }
            
            $cantidadLlego = $request->cantidad_llego ?? 0;
            
            // Si es negativo y no hay novedad existente, no permitir
            if ($cantidadLlego < 0) {
                throw new \Exception("No puede registrar una cantidad negativa para un producto nuevo. Primero debe registrar una cantidad positiva.");
            }
            
            $novedad->cantidad_llego = $cantidadLlego;
            
            // Si hay observaciones con diferencia, extraer cantidad esperada (cantidad enviada)
            $cantidadEsperada = null;
            if ($request->observaciones && preg_match('/Cantidad esperada: ([\d.]+)/', $request->observaciones, $matches)) {
                $cantidadEsperada = floatval($matches[1]);
                $novedad->cantidad_enviada = $cantidadEsperada;
                // La diferencia real es la diferencia con lo esperado
                $novedad->diferencia = $cantidadLlego - $cantidadEsperada;
            } else {
                // Si no hay cantidad esperada, significa que el producto no estaba en el pedido
                $novedad->cantidad_enviada = 0;
                // La diferencia es igual a cantidad_llego (aún no se ha enviado nada)
                $novedad->diferencia = $cantidadLlego;
            }
            
            $novedad->pedido_central_id = $request->pedido_id;
            $novedad->item_pedido_id = $request->item_pedido_id;
            $novedad->pasillero_id = $pasilleroId;
            $novedad->estado = 'pendiente';
            $novedad->observaciones = $request->observaciones ?? null;
            $novedad->save();
            
            DB::commit();
            
            return Response::json([
                'estado' => true,
                'novedad' => $novedad,
                'existe' => false,
                'msj' => 'Novedad registrada exitosamente'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return Response::json([
                'estado' => false,
                'msj' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Agregar cantidad a una novedad existente
     */
    public function agregarCantidadNovedad(Request $request)
    {
        $request->validate([
            'novedad_id' => 'required|exists:tcr_novedades,id',
            'cantidad' => 'required|numeric|min:0.0001',
            'observaciones' => 'nullable|string',
        ]);
        
        try {
            DB::beginTransaction();
            
            $pasilleroId = session('id_usuario');
            if (!$pasilleroId) {
                throw new \Exception('Usuario no autenticado');
            }
            
            $novedad = TCRNovedad::findOrFail($request->novedad_id);
            
            if ($novedad->pasillero_id != $pasilleroId) {
                throw new \Exception('No tienes permiso para modificar esta novedad');
            }
            
            $cantidad = $request->cantidad;
            
            // Validar si es negativo, que no exceda la cantidad total cargada
            if ($cantidad < 0) {
                $cantidadTotalCargada = $novedad->cantidad_llego;
                $valorAbsoluto = abs($cantidad);
                
                if ($valorAbsoluto > $cantidadTotalCargada) {
                    throw new \Exception("No puede restar más de {$cantidadTotalCargada} (cantidad total cargada)");
                }
            }
            
            // Actualizar cantidad que llegó (no cantidad_enviada)
            $cantidadAnterior = $novedad->cantidad_llego;
            $novedad->cantidad_llego += $cantidad;
            
            // Asegurar que no sea negativa
            if ($novedad->cantidad_llego < 0) {
                $novedad->cantidad_llego = 0;
            }
            
            // Recalcular diferencia
            $novedad->diferencia = $novedad->cantidad_llego - $novedad->cantidad_enviada;
            $novedad->save();
            
            // Registrar en historial
            DB::table('tcr_novedades_historial')->insert([
                'novedad_id' => $novedad->id,
                'cantidad_agregada' => $cantidad,
                'cantidad_total' => $novedad->cantidad_llego,
                'pasillero_id' => $pasilleroId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            DB::commit();
            
            // Cargar historial actualizado
            $novedad->load(['historial' => function($query) {
                $query->orderBy('created_at', 'desc');
            }]);
            
            return Response::json([
                'estado' => true,
                'novedad' => $novedad,
                'msj' => 'Cantidad agregada exitosamente'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return Response::json([
                'estado' => false,
                'msj' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener todas las novedades del pasillero actual
     */
    public function getNovedades(Request $request)
    {
        $pasilleroId = session('id_usuario');
        if (!$pasilleroId) {
            return Response::json([
                'estado' => false,
                'msj' => 'Usuario no autenticado'
            ], 401);
        }
        
        $novedades = TCRNovedad::porPasillero($pasilleroId)
            ->where('estado', 'pendiente')
            ->with(['historial' => function($query) {
                $query->orderBy('created_at', 'desc');
            }, 'inventario'])
            ->orderBy('created_at', 'desc')
            ->get();
        
        return Response::json([
            'estado' => true,
            'novedades' => $novedades
        ]);
    }
    
    /**
     * Actualizar cantidad que llegó de una novedad
     */
    public function actualizarCantidadLlegoNovedad(Request $request)
    {
        $request->validate([
            'novedad_id' => 'required|exists:tcr_novedades,id',
            'cantidad_llego' => 'required|numeric|min:0',
        ]);
        
        try {
            $pasilleroId = session('id_usuario');
            if (!$pasilleroId) {
                throw new \Exception('Usuario no autenticado');
            }
            
            $novedad = TCRNovedad::findOrFail($request->novedad_id);
            
            if ($novedad->pasillero_id != $pasilleroId) {
                throw new \Exception('No tienes permiso para modificar esta novedad');
            }
            
            $novedad->cantidad_llego = $request->cantidad_llego;
            $novedad->calcularDiferencia();
            $novedad->save();
            
            return Response::json([
                'estado' => true,
                'novedad' => $novedad->fresh(),
                'msj' => 'Cantidad que llegó actualizada exitosamente'
            ]);
            
        } catch (\Exception $e) {
            return Response::json([
                'estado' => false,
                'msj' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}
