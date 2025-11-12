<?php

namespace App\Http\Controllers;

use App\Models\TCRAsignacion;
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
                "qpedidocentrallimit" => $request->qpedidocentrallimit ?? '',
                "qpedidocentralestado" => $request->qpedidocentralestado ?? 4, // Solo revisados
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
            'pedido_id' => 'required|integer',
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
            ->whereIn('estado', ['pendiente', 'en_espera', 'en_proceso'])
            ->with(['warehouse'])
            ->orderBy('pedido_central_id')
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Agrupar por pedido
        $asignacionesPorPedido = $asignaciones->groupBy('pedido_central_id')->map(function($asigs, $pedidoId) {
            return [
                'pedido_id' => $pedidoId,
                'total_asignaciones' => $asigs->count(),
                'completadas' => $asigs->where('estado', 'en_espera')->count(),
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
            
            // Actualizar asignación - queda en "en_espera" hasta que el chequeador confirme
            $asignacion->cantidad_asignada += $request->cantidad;
            $asignacion->warehouse_codigo = $warehouse->codigo;
            $asignacion->warehouse_id = $warehouse->id;
            // Si está completo, queda en "en_espera" para revisión del chequeador
            // Si no está completo, queda en "en_proceso"
            $asignacion->estado = $asignacion->estaCompleto() ? 'en_espera' : 'en_proceso';
            $asignacion->save();
            
            // NO crear warehouse_inventory todavía - se creará cuando el chequeador confirme
            // NO registrar movimiento todavía - se registrará cuando el chequeador confirme
            
            DB::commit();
            
            return Response::json([
                'estado' => true,
                'msj' => 'Asignación procesada exitosamente. Esperando confirmación del chequeador.',
                'asignacion' => $asignacion->fresh(),
                'completo' => $asignacion->estaCompleto(),
                'en_espera' => $asignacion->estado === 'en_espera'
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
                'en_espera' => $asigs->where('estado', 'en_espera')->count(),
                'completadas' => $asigs->where('estado', 'completado')->count(),
                'pendientes' => $asigs->where('estado', 'pendiente')->count(),
                'en_proceso' => $asigs->where('estado', 'en_proceso')->count(),
                'asignaciones' => $asigs->values()
            ];
        })->values();
        
        // Verificar si todas las asignaciones están en espera o completadas
        $todasListas = $asignaciones->every(function($asig) {
            return in_array($asig->estado, ['en_espera', 'completado']);
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
                'en_espera' => $asignaciones->where('estado', 'en_espera')->count(),
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
        $chequeadorId = session('id_usuario');
        if (!$chequeadorId) {
            return Response::json([
                'estado' => false,
                'msj' => 'Usuario no autenticado'
            ], 401);
        }
        
        try {
            DB::beginTransaction();
            
            // Si recibe 'asignaciones', procesar las asignaciones TCR primero
            if ($request->has('asignaciones') && $request->asignaciones) {
                $pedidoId = $request->pedido_id ?? $request->pedido['id'] ?? null;
                
                if (!$pedidoId) {
                    throw new \Exception('ID de pedido requerido para procesar asignaciones');
                }
                
                // Verificar que todas las asignaciones del pedido estén listas (en_espera o completado)
                $totalAsignaciones = TCRAsignacion::where('pedido_central_id', $pedidoId)
                    ->where('chequeador_id', $chequeadorId)
                    ->count();
                
                if ($totalAsignaciones === 0) {
                    throw new \Exception('No hay asignaciones para este pedido');
                }
                
                $asignacionesListas = TCRAsignacion::where('pedido_central_id', $pedidoId)
                    ->where('chequeador_id', $chequeadorId)
                    ->whereIn('estado', ['en_espera', 'completado'])
                    ->count();
                
                if ($asignacionesListas < $totalAsignaciones) {
                    throw new \Exception("Aún hay asignaciones pendientes. Listas: {$asignacionesListas}/{$totalAsignaciones}");
                }
                
                // Obtener todas las asignaciones en espera del pedido
                $asignaciones = TCRAsignacion::where('pedido_central_id', $pedidoId)
                    ->where('chequeador_id', $chequeadorId)
                    ->where('estado', 'en_espera')
                    ->get();
                
                if ($asignaciones->isEmpty()) {
                    // Si no hay en espera, verificar si ya están todas completadas
                    $completadas = TCRAsignacion::where('pedido_central_id', $pedidoId)
                        ->where('chequeador_id', $chequeadorId)
                        ->where('estado', 'completado')
                        ->count();
                    
                    if ($completadas === $totalAsignaciones) {
                        // Ya están todas completadas, continuar con checkPedidosCentral
                    } else {
                        throw new \Exception('No hay asignaciones en espera para procesar');
                    }
                } else {
                    // Procesar cada asignación: crear warehouse_inventory y registrar movimientos
                    foreach ($asignaciones as $asignacion) {
                        // Buscar producto en inventario local
                        $producto = inventario::where('codigo_barras', $asignacion->codigo_barras)
                            ->orWhere('codigo_proveedor', $asignacion->codigo_proveedor)
                            ->first();
                        
                        if (!$producto) {
                            throw new \Exception("Producto no encontrado: {$asignacion->descripcion}");
                        }
                        
                        if (!$asignacion->warehouse_id) {
                            throw new \Exception("Asignación sin ubicación: {$asignacion->descripcion}");
                        }
                        
                        // Crear o actualizar warehouse_inventory
                        $warehouseInventory = WarehouseInventory::where('warehouse_id', $asignacion->warehouse_id)
                            ->where('inventario_id', $producto->id)
                            ->first();
                        
                        if ($warehouseInventory) {
                            $warehouseInventory->cantidad += $asignacion->cantidad_asignada;
                            $warehouseInventory->save();
                        } else {
                            $warehouseInventory = WarehouseInventory::create([
                                'warehouse_id' => $asignacion->warehouse_id,
                                'inventario_id' => $producto->id,
                                'cantidad' => $asignacion->cantidad_asignada,
                                'lote' => null,
                                'fecha_entrada' => now(),
                                'estado' => 'disponible',
                            ]);
                        }
                        
                        // Registrar movimiento
                        WarehouseMovement::create([
                            'tipo' => 'entrada',
                            'inventario_id' => $producto->id,
                            'warehouse_origen_id' => null,
                            'warehouse_destino_id' => $asignacion->warehouse_id,
                            'cantidad' => $asignacion->cantidad_asignada,
                            'lote' => null,
                            'fecha_vencimiento' => null,
                            'usuario_id' => $chequeadorId,
                            'documento_referencia' => "TCR-Pedido-{$asignacion->pedido_central_id}",
                            'observaciones' => "Asignación TCR confirmada - Pasillero: {$asignacion->pasillero_id}",
                            'fecha_movimiento' => now(),
                        ]);
                        
                        // Marcar asignación como completada
                        $asignacion->estado = 'completado';
                        $asignacion->save();
                    }
                }
            }
            
            // Llamar al método checkPedidosCentral del InventarioController
            $inventarioController = new \App\Http\Controllers\InventarioController();
            $resultado = $inventarioController->checkPedidosCentral($request);
            
            // Si el resultado es un array con 'estado', retornarlo directamente
            if (is_array($resultado) && isset($resultado['estado'])) {
                DB::commit();
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
                "qpedidocentralestado" => 4,
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
}
