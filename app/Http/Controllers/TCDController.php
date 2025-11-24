<?php

namespace App\Http\Controllers;

use App\Models\TCDOrden;
use App\Models\TCDOrdenItem;
use App\Models\TCDAsignacion;
use App\Models\TCDTicketDespacho;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Models\WarehouseMovement;
use App\Models\inventario;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\sendCentral;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Http;
use Response;

class TCDController extends Controller
{
    /**
     * Normalizar código de ubicación: reemplazar caracteres no alfanuméricos por guiones
     */
    private function normalizarCodigoUbicacion($codigo)
    {
        if (!$codigo) {
            return $codigo;
        }
        
        $normalizado = preg_replace('/[^a-zA-Z0-9]/', '-', $codigo);
        $normalizado = preg_replace('/-+/', '-', $normalizado);
        $normalizado = trim($normalizado, '-');
        
        return $normalizado;
    }

    /**
     * Vista del chequeador - Crear orden TCD y asignar productos a pasilleros
     */
    public function chequeador()
    {
        return view('warehouse-inventory.tcd.chequeador');
    }
    
    /**
     * Vista del pasillero - Interfaz de escaneo
     */
    public function pasillero()
    {
        return view('warehouse-inventory.tcd.pasillero');
    }
    
    /**
     * Buscar productos por código de barras, código proveedor o descripción
     */
    public function buscarProductos(Request $request)
    {
        $buscar = $request->buscar;
        
        if (!$buscar || strlen($buscar) < 2) {
            return Response::json([
                'estado' => false,
                'productos' => [],
                'msj' => 'Ingrese al menos 2 caracteres'
            ]);
        }
        
        try {
            $productos = inventario::with(['proveedor', 'categoria'])
                ->where(function($query) use ($buscar) {
                    $query->where('codigo_barras', 'like', "%{$buscar}%")
                          ->orWhere('codigo_proveedor', 'like', "%{$buscar}%")
                          ->orWhere('descripcion', 'like', "%{$buscar}%");
                })
                ->limit(20)
                ->get();
            
            $productosFormateados = $productos->map(function($producto) {
                try {
                    // Obtener stock disponible desde WarehouseInventory (cantidad - cantidad_bloqueada)
                    $stockDisponibleWarehouse = WarehouseInventory::where('inventario_id', $producto->id)
                        ->selectRaw('SUM(cantidad - COALESCE(cantidad_bloqueada, 0)) as disponible')
                        ->value('disponible');
                    
                    $stockDisponibleWarehouse = $stockDisponibleWarehouse !== null ? (float)$stockDisponibleWarehouse : 0;
                    
                    // Obtener stock total desde WarehouseInventory
                    $stockTotalWarehouse = WarehouseInventory::where('inventario_id', $producto->id)
                        ->sum('cantidad') ?? 0;
                    
                    // Si no hay stock en WarehouseInventory, usar el stock general del inventario
                    // Esto puede pasar cuando el producto tiene stock pero no está asignado a ubicaciones específicas
                    if ($stockTotalWarehouse == 0 && isset($producto->cantidad) && $producto->cantidad > 0) {
                        $stockDisponible = (float)$producto->cantidad;
                        $stockTotal = (float)$producto->cantidad;
                    } else {
                        $stockDisponible = $stockDisponibleWarehouse;
                        $stockTotal = (float)$stockTotalWarehouse;
                    }
                    
                    // Obtener ubicación del producto (primera ubicación con stock disponible)
                    $warehouseInventory = WarehouseInventory::where('inventario_id', $producto->id)
                        ->whereRaw('(cantidad - COALESCE(cantidad_bloqueada, 0)) > 0')
                        ->with('warehouse')
                        ->orderBy('cantidad', 'desc')
                        ->first();
                    
                    $ubicacion = 'N/A';
                    if ($warehouseInventory && $warehouseInventory->warehouse) {
                        $ubicacion = $warehouseInventory->warehouse->codigo ?? 'N/A';
                    }
                    
                    return [
                        'id' => $producto->id,
                        'codigo_barras' => $producto->codigo_barras ?? '',
                        'codigo_proveedor' => $producto->codigo_proveedor ?? '',
                        'descripcion' => $producto->descripcion ?? '',
                        'precio' => $producto->precio ?? 0,
                        'precio_base' => $producto->precio_base ?? 0,
                        'ubicacion' => $ubicacion,
                        'stock_total' => $stockTotal,
                        'stock_disponible' => $stockDisponible,
                    ];
                } catch (\Exception $e) {
                    // Si hay error procesando un producto, intentar usar stock general como fallback
                    $stockGeneral = isset($producto->cantidad) ? (float)$producto->cantidad : 0;
                    
                    return [
                        'id' => $producto->id,
                        'codigo_barras' => $producto->codigo_barras ?? '',
                        'codigo_proveedor' => $producto->codigo_proveedor ?? '',
                        'descripcion' => $producto->descripcion ?? '',
                        'precio' => $producto->precio ?? 0,
                        'precio_base' => $producto->precio_base ?? 0,
                        'ubicacion' => 'N/A',
                        'stock_total' => $stockGeneral,
                        'stock_disponible' => $stockGeneral,
                    ];
                }
            })->values(); // values() para asegurar que sea un array indexado
            
            return Response::json([
                'estado' => true,
                'productos' => $productosFormateados->toArray()
            ], 200, [], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            \Log::error('Error en buscarProductos TCD: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return Response::json([
                'estado' => false,
                'productos' => [],
                'msj' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Crear orden TCD desde el carrito
     */
    public function crearOrden(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.inventario_id' => 'required|exists:inventarios,id',
            'items.*.cantidad' => 'required|numeric|min:0.01',
            'observaciones' => 'nullable|string',
        ]);
        
        try {
            DB::beginTransaction();
            
            $chequeadorId = session('id_usuario');
            if (!$chequeadorId) {
                throw new \Exception('Usuario no autenticado');
            }
            
            // Generar número de orden
            $ultimaOrden = TCDOrden::orderBy('id', 'desc')->first();
            $numeroOrden = 'TCD #' . str_pad(($ultimaOrden ? $ultimaOrden->id : 0) + 1, 6, '0', STR_PAD_LEFT);
            
            // Crear orden
            $orden = TCDOrden::create([
                'numero_orden' => $numeroOrden,
                'chequeador_id' => $chequeadorId,
                'estado' => 'borrador',
                'observaciones' => $request->observaciones,
            ]);
            
            // Crear items de la orden con validación de stock
            foreach ($request->items as $item) {
                $producto = inventario::findOrFail($item['inventario_id']);
                
                // Validar stock disponible desde WarehouseInventory (cantidad - cantidad_bloqueada)
                $stockDisponibleWarehouse = WarehouseInventory::where('inventario_id', $producto->id)
                    ->selectRaw('SUM(cantidad - COALESCE(cantidad_bloqueada, 0)) as disponible')
                    ->value('disponible') ?? 0;
                
                // Si no hay stock en WarehouseInventory, usar el stock general del inventario
                $stockTotalWarehouse = WarehouseInventory::where('inventario_id', $producto->id)
                    ->sum('cantidad') ?? 0;
                
                if ($stockTotalWarehouse == 0 && isset($producto->cantidad) && $producto->cantidad > 0) {
                    $stockDisponible = (float)$producto->cantidad;
                } else {
                    $stockDisponible = (float)$stockDisponibleWarehouse;
                }
                
                if ($stockDisponible < $item['cantidad']) {
                    throw new \Exception("Stock insuficiente para el producto '{$producto->descripcion}'. Disponible: {$stockDisponible}, Solicitado: {$item['cantidad']}");
                }
                
                // Obtener ubicación del producto
                $warehouseInventory = WarehouseInventory::where('inventario_id', $producto->id)
                    ->whereRaw('(cantidad - COALESCE(cantidad_bloqueada, 0)) > 0')
                    ->with('warehouse')
                    ->orderBy('cantidad', 'desc')
                    ->first();
                
                $ubicacion = $warehouseInventory ? $warehouseInventory->warehouse->codigo ?? null : null;
                
                TCDOrdenItem::create([
                    'tcd_orden_id' => $orden->id,
                    'inventario_id' => $producto->id,
                    'codigo_barras' => $producto->codigo_barras,
                    'codigo_proveedor' => $producto->codigo_proveedor,
                    'descripcion' => $producto->descripcion,
                    'precio' => $producto->precio,
                    'precio_base' => $producto->precio_base,
                    'ubicacion' => $ubicacion,
                    'cantidad' => $item['cantidad'],
                    'cantidad_descontada' => 0,
                    'cantidad_bloqueada' => 0,
                ]);
            }
            
            DB::commit();
            
            return Response::json([
                'estado' => true,
                'msj' => 'Orden creada exitosamente',
                'orden' => $orden->load('items')
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
     * Obtener usuarios pasilleros disponibles
     */
    public function getPasilleros()
    {
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
     * Asignar productos a pasilleros (manual o automático)
     */
    public function asignarProductos(Request $request)
    {
        $request->validate([
            'orden_id' => 'required|exists:tcd_ordenes,id',
            'asignaciones' => 'required|array',
            'asignaciones.*.item_id' => 'required|exists:tcd_orden_items,id',
            'asignaciones.*.pasillero_id' => 'required|exists:usuarios,id',
            'asignaciones.*.cantidad' => 'required|numeric|min:0.01',
            'modo' => 'required|in:manual,automatico',
        ]);
        
        try {
            DB::beginTransaction();
            
            $orden = TCDOrden::findOrFail($request->orden_id);
            
            if ($orden->estado !== 'borrador') {
                throw new \Exception('La orden ya fue asignada');
            }
            
            // Validar cantidades
            foreach ($request->asignaciones as $asig) {
                $item = TCDOrdenItem::findOrFail($asig['item_id']);
                
                if ($asig['cantidad'] > $item->cantidad) {
                    throw new \Exception("Cantidad excede lo disponible para el producto '{$item->descripcion}'");
                }
            }
            
            // Crear asignaciones
            foreach ($request->asignaciones as $asig) {
                $item = TCDOrdenItem::findOrFail($asig['item_id']);
                
                TCDAsignacion::create([
                    'tcd_orden_id' => $orden->id,
                    'tcd_orden_item_id' => $asig['item_id'],
                    'pasillero_id' => $asig['pasillero_id'],
                    'cantidad' => $asig['cantidad'],
                    'cantidad_procesada' => 0,
                    'estado' => 'pendiente',
                ]);
            }
            
            // Actualizar estado de la orden
            $orden->estado = 'asignada';
            $orden->save();
            
            DB::commit();
            
            return Response::json([
                'estado' => true,
                'msj' => 'Productos asignados exitosamente'
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
     * Obtener asignaciones del pasillero actual agrupadas por orden
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
        
        // Obtener asignaciones del pasillero
        $asignaciones = TCDAsignacion::porPasillero($pasilleroId)
            ->whereIn('estado', ['pendiente', 'en_proceso'])
            ->with(['orden', 'ordenItem', 'warehouse'])
            ->get()
            ->map(function($asig) {
                $inventarioId = $asig->ordenItem->inventario_id;
                $cantidadPendiente = $asig->cantidad - $asig->cantidad_procesada;
                
                // Obtener stock disponible total desde WarehouseInventory
                $stockDisponibleWarehouse = WarehouseInventory::where('inventario_id', $inventarioId)
                    ->selectRaw('SUM(cantidad - COALESCE(cantidad_bloqueada, 0)) as disponible')
                    ->value('disponible') ?? 0;
                
                // Si no hay stock en WarehouseInventory, usar el stock general del inventario
                $stockTotalWarehouse = WarehouseInventory::where('inventario_id', $inventarioId)
                    ->sum('cantidad') ?? 0;
                
                $producto = inventario::find($inventarioId);
                if ($stockTotalWarehouse == 0 && $producto && isset($producto->cantidad) && $producto->cantidad > 0) {
                    $stockDisponible = (float)$producto->cantidad;
                } else {
                    $stockDisponible = (float)$stockDisponibleWarehouse;
                }
                
                // Obtener TODAS las ubicaciones con stock disponible para este producto
                $todasUbicaciones = WarehouseInventory::where('inventario_id', $inventarioId)
                    ->whereRaw('(cantidad - COALESCE(cantidad_bloqueada, 0)) > 0')
                    ->with('warehouse')
                    ->get()
                    ->map(function($wi) {
                        $disponible = $wi->cantidad - ($wi->cantidad_bloqueada ?? 0);
                        return [
                            'warehouse_id' => $wi->warehouse_id,
                            'codigo' => $wi->warehouse->codigo ?? 'N/A',
                            'stock_disponible' => (float)$disponible
                        ];
                    })
                    ->values();
                
                // Separar ubicaciones: las que resuelven completamente y las que tienen stock pero no resuelven
                $ubicacionesSuficientes = $todasUbicaciones->filter(function($ubicacion) use ($cantidadPendiente) {
                    return $ubicacion['stock_disponible'] >= $cantidadPendiente;
                })->values();
                
                $ubicacionesInsuficientes = $todasUbicaciones->filter(function($ubicacion) use ($cantidadPendiente) {
                    return $ubicacion['stock_disponible'] < $cantidadPendiente;
                })->values();
                
                // Verificar si hay al menos una ubicación que resuelva la cantidad pendiente
                $tieneUbicacionSuficiente = $ubicacionesSuficientes->count() > 0;
                
                $asig->stock_disponible = $stockDisponible;
                $asig->ubicaciones_suficientes = $ubicacionesSuficientes;
                $asig->ubicaciones_insuficientes = $ubicacionesInsuficientes;
                $asig->ubicaciones_disponibles = $todasUbicaciones; // Todas las ubicaciones para mostrar
                $asig->tiene_ubicacion_suficiente = $tieneUbicacionSuficiente;
                
                return $asig;
            });
        
        // Agrupar por orden
        $ordenesAgrupadas = $asignaciones->groupBy('tcd_orden_id')->map(function($asigs, $ordenId) {
            $orden = $asigs->first()->orden;
            return [
                'orden_id' => $orden->id,
                'numero_orden' => $orden->numero_orden,
                'estado' => $orden->estado,
                'fecha_creacion' => $orden->created_at->toDateTimeString(),
                'observaciones' => $orden->observaciones,
                'asignaciones' => $asigs->map(function($asig) {
                    return [
                        'id' => $asig->id,
                        'tcd_orden_id' => $asig->tcd_orden_id,
                        'tcd_orden_item_id' => $asig->tcd_orden_item_id,
                        'pasillero_id' => $asig->pasillero_id,
                        'cantidad' => (float)$asig->cantidad,
                        'cantidad_procesada' => (float)$asig->cantidad_procesada,
                        'estado' => $asig->estado,
                        'warehouse_id' => $asig->warehouse_id,
                        'stock_disponible' => (float)($asig->stock_disponible ?? 0),
                        'ubicaciones_suficientes' => $asig->ubicaciones_suficientes ?? [],
                        'ubicaciones_insuficientes' => $asig->ubicaciones_insuficientes ?? [],
                        'ubicaciones_disponibles' => $asig->ubicaciones_disponibles ?? [],
                        'tiene_ubicacion_suficiente' => $asig->tiene_ubicacion_suficiente ?? false,
                        'orden' => [
                            'id' => $asig->orden->id,
                            'numero_orden' => $asig->orden->numero_orden,
                            'estado' => $asig->orden->estado
                        ],
                        'orden_item' => [
                            'id' => $asig->ordenItem->id,
                            'inventario_id' => $asig->ordenItem->inventario_id,
                            'codigo_barras' => $asig->ordenItem->codigo_barras,
                            'codigo_proveedor' => $asig->ordenItem->codigo_proveedor,
                            'descripcion' => $asig->ordenItem->descripcion,
                            'cantidad_solicitada' => (float)$asig->ordenItem->cantidad_solicitada
                        ],
                        'warehouse' => $asig->warehouse ? [
                            'id' => $asig->warehouse->id,
                            'codigo' => $asig->warehouse->codigo
                        ] : null
                    ];
                })->values()
            ];
        })->sortByDesc(function($orden) {
            return $orden['fecha_creacion'];
        })->values(); // Ordenar por fecha de creación descendente
        
        return Response::json([
            'estado' => true,
            'ordenes' => $ordenesAgrupadas->toArray(),
            'asignaciones' => $asignaciones->map(function($asig) {
                return [
                    'id' => $asig->id,
                    'tcd_orden_id' => $asig->tcd_orden_id,
                    'tcd_orden_item_id' => $asig->tcd_orden_item_id,
                    'pasillero_id' => $asig->pasillero_id,
                    'cantidad' => (float)$asig->cantidad,
                    'cantidad_procesada' => (float)$asig->cantidad_procesada,
                    'estado' => $asig->estado,
                    'warehouse_id' => $asig->warehouse_id,
                    'stock_disponible' => (float)($asig->stock_disponible ?? 0),
                    'ubicaciones_suficientes' => $asig->ubicaciones_suficientes ?? [],
                    'ubicaciones_insuficientes' => $asig->ubicaciones_insuficientes ?? [],
                    'ubicaciones_disponibles' => $asig->ubicaciones_disponibles ?? [],
                    'tiene_ubicacion_suficiente' => $asig->tiene_ubicacion_suficiente ?? false,
                    'orden' => [
                        'id' => $asig->orden->id,
                        'numero_orden' => $asig->orden->numero_orden,
                        'estado' => $asig->orden->estado
                    ],
                    'orden_item' => [
                        'id' => $asig->ordenItem->id,
                        'inventario_id' => $asig->ordenItem->inventario_id,
                        'codigo_barras' => $asig->ordenItem->codigo_barras,
                        'codigo_proveedor' => $asig->ordenItem->codigo_proveedor,
                        'descripcion' => $asig->ordenItem->descripcion,
                        'cantidad_solicitada' => (float)$asig->ordenItem->cantidad_solicitada
                    ],
                    'warehouse' => $asig->warehouse ? [
                        'id' => $asig->warehouse->id,
                        'codigo' => $asig->warehouse->codigo
                    ] : null
                ];
            })->toArray() // Mantener para compatibilidad
        ]);
    }
    
    /**
     * Procesar asignación - Pasillero escanea ubicación, producto y cantidad
     */
    public function procesarAsignacion(Request $request)
    {
        $request->validate([
            'asignacion_id' => 'required|exists:tcd_asignaciones,id',
            'codigo_ubicacion' => 'required|string',
            'codigo_producto' => 'required|string',
            'cantidad' => 'required|numeric|min:0.01',
        ]);
        
        try {
            DB::beginTransaction();
            
            $asignacion = TCDAsignacion::findOrFail($request->asignacion_id);
            $ordenItem = $asignacion->ordenItem;
            
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
            $codigoBarras = strtoupper(trim($ordenItem->codigo_barras ?? ''));
            $codigoProveedor = strtoupper(trim($ordenItem->codigo_proveedor ?? ''));
            
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
            
            // Actualizar asignación
            $asignacion->cantidad_procesada += $request->cantidad;
            $asignacion->warehouse_codigo = $warehouse->codigo;
            $asignacion->warehouse_id = $warehouse->id;
            $asignacion->estado = $asignacion->estaCompleta() ? 'completada' : 'en_proceso';
            $asignacion->save();
            
            // Actualizar estado de la orden si todas las asignaciones están completas
            $orden = $asignacion->orden;
            $todasCompletas = $orden->asignaciones()->where('estado', '!=', 'completada')->count() === 0;
            if ($todasCompletas) {
                $orden->estado = 'completada';
                $orden->save();
            } else {
                $orden->estado = 'en_proceso';
                $orden->save();
            }
            
            DB::commit();
            
            return Response::json([
                'estado' => true,
                'msj' => 'Asignación procesada exitosamente',
                'asignacion' => $asignacion->fresh(),
                'completa' => $asignacion->estaCompleta()
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
     * Reversar una asignación procesada
     */
    public function reversarAsignacion(Request $request)
    {
        $chequeadorId = session('id_usuario');
        if (!$chequeadorId) {
            return Response::json([
                'estado' => false,
                'msj' => 'Usuario no autenticado'
            ], 401);
        }
        
        $request->validate([
            'asignacion_id' => 'required|integer|exists:tcd_asignaciones,id',
            'cantidad' => 'required|numeric|min:0.0001'
        ]);
        
        try {
            DB::beginTransaction();
            
            $asignacion = TCDAsignacion::with(['orden', 'ordenItem'])->findOrFail($request->asignacion_id);
            
            // Verificar que el chequeador sea el dueño de la orden
            if ($asignacion->orden->chequeador_id != $chequeadorId) {
                throw new \Exception('No tiene permiso para reversar esta asignación');
            }
            
            // Verificar que la orden no tenga ticket de despacho generado
            if ($asignacion->orden->ticketDespacho) {
                throw new \Exception('No se puede reversar una asignación de una orden que ya tiene ticket de despacho');
            }
            
            // Verificar que la cantidad a reversar no exceda la cantidad procesada
            if ($request->cantidad > $asignacion->cantidad_procesada) {
                throw new \Exception("La cantidad a reversar ({$request->cantidad}) excede la cantidad procesada ({$asignacion->cantidad_procesada})");
            }
            
            // Reversar la cantidad
            $asignacion->cantidad_procesada -= $request->cantidad;
            
            // Si la cantidad procesada llega a 0, cambiar estado a pendiente
            if ($asignacion->cantidad_procesada <= 0) {
                $asignacion->cantidad_procesada = 0;
                $asignacion->estado = 'pendiente';
            } else {
                // Si aún hay cantidad procesada, cambiar a en_proceso
                $asignacion->estado = 'en_proceso';
            }
            
            $asignacion->save();
            
            // Actualizar estado de la orden
            $orden = $asignacion->orden;
            $todasCompletas = $orden->asignaciones()->where('estado', '!=', 'completada')->count() === 0;
            if ($todasCompletas) {
                $orden->estado = 'completada';
            } else {
                $orden->estado = 'en_proceso';
            }
            $orden->save();
            
            DB::commit();
            
            return Response::json([
                'estado' => true,
                'msj' => 'Asignación reversada exitosamente',
                'asignacion' => $asignacion->fresh()
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
     * Obtener asignaciones por orden para el chequeador
     */
    public function getAsignacionesPorOrden(Request $request)
    {
        $chequeadorId = session('id_usuario');
        if (!$chequeadorId) {
            return Response::json([
                'estado' => false,
                'msj' => 'Usuario no autenticado'
            ], 401);
        }
        
        $ordenId = $request->orden_id;
        if (!$ordenId) {
            return Response::json([
                'estado' => false,
                'msj' => 'ID de orden requerido'
            ]);
        }
        
        $orden = TCDOrden::where('id', $ordenId)
            ->where('chequeador_id', $chequeadorId)
            ->with(['items', 'asignaciones.pasillero', 'asignaciones.ordenItem', 'asignaciones.warehouse', 'ticketDespacho'])
            ->firstOrFail();
        
        // Verificar si ya tiene ticket de despacho (ya fue confirmada y descontada)
        $tieneTicketDespacho = $orden->ticketDespacho !== null;
        
        // Agrupar por pasillero
        $asignacionesPorPasillero = $orden->asignaciones->groupBy('pasillero_id')->map(function($asigs, $pasilleroId) {
            $pasillero = $asigs->first()->pasillero;
            return [
                'pasillero_id' => $pasilleroId,
                'pasillero_nombre' => $pasillero->nombre ?? 'N/A',
                'total_asignaciones' => $asigs->count(),
                'completadas' => $asigs->where('estado', 'completada')->count(),
                'pendientes' => $asigs->where('estado', 'pendiente')->count(),
                'en_proceso' => $asigs->where('estado', 'en_proceso')->count(),
                'asignaciones' => $asigs->map(function($asig) {
                    return [
                        'id' => $asig->id,
                        'cantidad' => (float)$asig->cantidad,
                        'cantidad_procesada' => (float)$asig->cantidad_procesada,
                        'estado' => $asig->estado,
                        'warehouse_id' => $asig->warehouse_id,
                        'warehouse_codigo' => $asig->warehouse_codigo,
                        'orden_item' => [
                            'id' => $asig->ordenItem->id,
                            'inventario_id' => $asig->ordenItem->inventario_id,
                            'codigo_barras' => $asig->ordenItem->codigo_barras,
                            'codigo_proveedor' => $asig->ordenItem->codigo_proveedor,
                            'descripcion' => $asig->ordenItem->descripcion,
                            'cantidad_solicitada' => (float)$asig->ordenItem->cantidad_solicitada,
                            'precio_base' => (float)$asig->ordenItem->precio_base,
                            'precio_venta' => (float)$asig->ordenItem->precio_venta,
                        ],
                        'warehouse' => $asig->warehouse ? [
                            'id' => $asig->warehouse->id,
                            'codigo' => $asig->warehouse->codigo,
                            'descripcion' => $asig->warehouse->descripcion ?? null,
                        ] : null
                    ];
                })->values()
            ];
        })->values();
        
        $todasListas = $orden->asignaciones->every(function($asig) {
            return $asig->estado === 'completada';
        });
        
        return Response::json([
            'estado' => true,
            'orden' => $orden,
            'asignaciones_por_pasillero' => $asignacionesPorPasillero,
            'todas_listas' => $todasListas,
            'tiene_ticket_despacho' => $tieneTicketDespacho,
            'ticket_despacho' => $orden->ticketDespacho ? [
                'codigo_barras' => $orden->ticketDespacho->codigo_barras,
                'estado' => $orden->ticketDespacho->estado,
                'fecha_generacion' => $orden->ticketDespacho->created_at,
            ] : null,
            'transferencia' => $orden->fecha_transferencia ? [
                'sucursal_destino_id' => $orden->sucursal_destino_id,
                'sucursal_destino_codigo' => $orden->sucursal_destino_codigo,
                'fecha_transferencia' => $orden->fecha_transferencia,
                'pedido_central_numero' => $orden->pedido_central_numero,
            ] : null,
        ]);
    }
    
    /**
     * Obtener todas las órdenes del chequeador
     */
    public function getOrdenes(Request $request)
    {
        $chequeadorId = session('id_usuario');
        if (!$chequeadorId) {
            return Response::json([
                'estado' => false,
                'msj' => 'Usuario no autenticado'
            ], 401);
        }
        
        $estado = $request->estado ?? null;
        
        $query = TCDOrden::where('chequeador_id', $chequeadorId)
            ->with(['items', 'asignaciones', 'ticketDespacho'])
            ->orderBy('created_at', 'desc');
        
        if ($estado) {
            $query->where('estado', $estado);
        }
        
        $ordenes = $query->get()->map(function($orden) {
            $ordenArray = $orden->toArray();
            $ordenArray['tiene_ticket_despacho'] = $orden->ticketDespacho !== null;
            $ordenArray['fue_transferida'] = $orden->fecha_transferencia !== null;
            return $ordenArray;
        });
        
        return Response::json([
            'estado' => true,
            'ordenes' => $ordenes
        ]);
    }
    
    /**
     * Confirmar orden y descontar del inventario (chequeador)
     */
    public function confirmarOrden(Request $request)
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
            
            $orden = TCDOrden::where('id', $request->orden_id)
                ->where('chequeador_id', $chequeadorId)
                ->with(['items', 'asignaciones'])
                ->firstOrFail();
            
            if ($orden->estado !== 'completada') {
                throw new \Exception('La orden no está completa. Todas las asignaciones deben estar completadas.');
            }
            
            // Acumular cantidades por producto para descontar del inventario general
            $cantidadesPorProducto = [];
            
            // Procesar cada item de la orden
            foreach ($orden->items as $item) {
                $producto = inventario::findOrFail($item->inventario_id);
                
                // Obtener todas las asignaciones de este item
                $asignaciones = $orden->asignaciones()->where('tcd_orden_item_id', $item->id)->get();
                
                foreach ($asignaciones as $asignacion) {
                    if (!$asignacion->warehouse_id) {
                        throw new \Exception("Asignación sin ubicación para el producto: {$item->descripcion}");
                    }
                    
                    // Buscar o crear warehouse_inventory
                    $warehouseInventory = WarehouseInventory::where('warehouse_id', $asignacion->warehouse_id)
                        ->where('inventario_id', $producto->id)
                        ->first();
                    
                    if (!$warehouseInventory) {
                        throw new \Exception("Producto no encontrado en la ubicación para: {$item->descripcion}");
                    }
                    
                    // Calcular stock disponible (cantidad - cantidad_bloqueada)
                    $stockDisponible = $warehouseInventory->cantidad - ($warehouseInventory->cantidad_bloqueada ?? 0);
                    
                    if ($stockDisponible < $asignacion->cantidad_procesada) {
                        throw new \Exception("Stock insuficiente en ubicación para el producto: {$item->descripcion}. Disponible: {$stockDisponible}, Solicitado: {$asignacion->cantidad_procesada}");
                    }
                    
                    // Descontar cantidad y bloquear
                    $warehouseInventory->cantidad -= $asignacion->cantidad_procesada;
                    $warehouseInventory->cantidad_bloqueada = ($warehouseInventory->cantidad_bloqueada ?? 0) + $asignacion->cantidad_procesada;
                    $warehouseInventory->save();
                    
                    // Acumular cantidad para descontar del inventario general
                    if (!isset($cantidadesPorProducto[$producto->id])) {
                        $cantidadesPorProducto[$producto->id] = [
                            'producto' => $producto,
                            'cantidad_total' => 0,
                            'cantidad_anterior' => $producto->cantidad
                        ];
                    }
                    $cantidadesPorProducto[$producto->id]['cantidad_total'] += $asignacion->cantidad_procesada;
                    
                    // Actualizar item
                    $item->cantidad_descontada += $asignacion->cantidad_procesada;
                    $item->cantidad_bloqueada += $asignacion->cantidad_procesada;
                    $item->save();
                    
                    // Registrar movimiento
                    WarehouseMovement::create([
                        'tipo' => 'salida',
                        'inventario_id' => $producto->id,
                        'warehouse_origen_id' => $asignacion->warehouse_id,
                        'warehouse_destino_id' => null,
                        'cantidad' => $asignacion->cantidad_procesada,
                        'lote' => null,
                        'fecha_vencimiento' => null,
                        'usuario_id' => $chequeadorId,
                        'documento_referencia' => $orden->numero_orden,
                        'observaciones' => "TCD - Producto bloqueado esperando despacho",
                        'fecha_movimiento' => now(),
                    ]);
                }
            }
            
            // Descontar del inventario general usando descontarInventario
            $inventarioController = new InventarioController();
            foreach ($cantidadesPorProducto as $productoId => $datos) {
                $producto = $datos['producto'];
                $cantidadAnterior = $datos['cantidad_anterior'];
                $cantidadADescontar = $datos['cantidad_total'];
                $cantidadNueva = $cantidadAnterior - $cantidadADescontar;
                
                if ($cantidadNueva < 0) {
                    throw new \Exception("No hay suficiente stock en inventario general para el producto: {$producto->descripcion}. Disponible: {$cantidadAnterior}, Solicitado: {$cantidadADescontar}");
                }
                
                // Descontar del inventario general
                // Pasamos null como id_pedido (es un campo integer nullable) y el numero_orden completo como origen
                $inventarioController->descontarInventario(
                    $productoId,
                    $cantidadNueva, // cantidad nueva (después del descuento)
                    $cantidadAnterior, // cantidad anterior (antes del descuento)
                    null, // id_pedido (null porque es un campo integer y queremos el concepto en origen)
                    $orden->numero_orden // origen (el número de orden completo, ej: "TCD #000005")
                );
            }
            
            // Generar ticket de despacho
            $codigoBarras = 'TCD-' . str_pad($orden->id, 8, '0', STR_PAD_LEFT) . '-' . time();
            
            TCDTicketDespacho::create([
                'tcd_orden_id' => $orden->id,
                'codigo_barras' => $codigoBarras,
                'estado' => 'generado',
            ]);
            
            // La orden queda en estado "completada" pero con productos bloqueados
            // Se desbloqueará cuando se escanee el ticket de despacho
            
            DB::commit();
            
            return Response::json([
                'estado' => true,
                'msj' => 'Orden confirmada y productos descontados. Productos bloqueados esperando despacho.',
                'orden' => $orden->fresh(),
                'ticket_codigo_barras' => $codigoBarras
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
        $inventario_id = $request->inventario_id; // ID del producto para validar stock
        
        if (!$codigo) {
            return Response::json([
                'estado' => false,
                'msj' => 'Código requerido'
            ]);
        }
        
        $codigo = $this->normalizarCodigoUbicacion($codigo);
        
        $warehouse = Warehouse::where('codigo', $codigo)
            ->where('estado', 'activa')
            ->first();
        
        if (!$warehouse) {
            return Response::json([
                'estado' => false,
                'msj' => 'Ubicación no encontrada o no está activa'
            ]);
        }
        
        $response = [
            'estado' => true,
            'ubicacion' => $warehouse
        ];
        
        // Si se proporciona el inventario_id, calcular stock disponible en este warehouse
        if ($inventario_id) {
            $stockDisponible = WarehouseInventory::where('inventario_id', $inventario_id)
                ->where('warehouse_id', $warehouse->id)
                ->selectRaw('SUM(cantidad - COALESCE(cantidad_bloqueada, 0)) as disponible')
                ->value('disponible') ?? 0;
            
            $response['stock_disponible'] = (float)$stockDisponible;
        }
        
        return Response::json($response);
    }
    
    /**
     * Escanear ticket de despacho y desbloquear productos
     */
    public function escanearTicketDespacho(Request $request)
    {
        $request->validate([
            'codigo_barras' => 'required|string',
        ]);
        
        try {
            DB::beginTransaction();
            
            $usuarioId = session('id_usuario');
            if (!$usuarioId) {
                throw new \Exception('Usuario no autenticado');
            }
            
            $ticket = TCDTicketDespacho::where('codigo_barras', $request->codigo_barras)
                ->where('estado', 'generado')
                ->with('orden.items')
                ->first();
            
            if (!$ticket) {
                throw new \Exception('Ticket de despacho no encontrado o ya fue escaneado');
            }
            
            $orden = $ticket->orden;
            
            // Desbloquear todos los productos de la orden
            foreach ($orden->items as $item) {
                $asignaciones = $orden->asignaciones()->where('tcd_orden_item_id', $item->id)->get();
                
                foreach ($asignaciones as $asignacion) {
                    if ($asignacion->warehouse_id) {
                        $warehouseInventory = WarehouseInventory::where('warehouse_id', $asignacion->warehouse_id)
                            ->where('inventario_id', $item->inventario_id)
                            ->first();
                        
                        if ($warehouseInventory) {
                            // Desbloquear cantidad
                            $cantidadDesbloquear = min($asignacion->cantidad_procesada, $warehouseInventory->cantidad_bloqueada ?? 0);
                            $warehouseInventory->cantidad_bloqueada = ($warehouseInventory->cantidad_bloqueada ?? 0) - $cantidadDesbloquear;
                            $warehouseInventory->save();
                            
                            // Actualizar item
                            $item->cantidad_bloqueada = max(0, $item->cantidad_bloqueada - $cantidadDesbloquear);
                            $item->save();
                        }
                    }
                }
            }
            
            // Marcar ticket como escaneado
            $ticket->estado = 'escaneado';
            $ticket->usuario_despacho_id = $usuarioId;
            $ticket->fecha_escaneo = now();
            $ticket->save();
            
            // Actualizar orden
            $orden->estado = 'despachada';
            $orden->fecha_despacho = now();
            $orden->save();
            
            DB::commit();
            
            return Response::json([
                'estado' => true,
                'msj' => 'Ticket escaneado exitosamente. Productos desbloqueados.',
                'orden' => $orden->fresh()
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
     * Convertir orden TCD al formato de pedido para transferencia
     */
    private function convertirOrdenTCDAPedido($orden)
    {
        $orden->load(['items.inventario.proveedor', 'items.inventario.categoria']);
        
        // Generar un ID numérico único para TCD usando un offset grande
        // Usamos 9000000 como base para evitar conflictos con pedidos normales
        // El ID será: 9000000 + orden_id (ej: 9000004 para orden 4)
        $idinsucursalNumerico = 9000000 + $orden->id;
        
        // Crear estructura similar a pedidosExportadosFun
        $pedidoFormato = [
            'id' => $idinsucursalNumerico, // ID numérico único para TCD (9000000 + orden_id)
            'numero_orden' => $orden->numero_orden,
            'tipo' => 'TCD',
            'fecha' => $orden->created_at->toDateString(),
            'observaciones' => ($orden->observaciones ?? 'Orden TCD: ' . $orden->numero_orden) . ' [TCD-' . $orden->id . ']',
            'cliente' => null, // TCD no tiene cliente
            'items' => $orden->items->map(function($item) {
                $producto = $item->inventario;
                return [
                    'id' => $item->id,
                    'id_producto' => $item->inventario_id,
                    'cantidad' => $item->cantidad_descontada > 0 ? $item->cantidad_descontada : $item->cantidad,
                    'monto' => ($item->precio ?? 0) * ($item->cantidad_descontada > 0 ? $item->cantidad_descontada : $item->cantidad),
                    'descuento' => 0,
                    'entregado' => 0,
                    'condicion' => null,
                    'producto' => $producto ? [
                        'id' => $producto->id,
                        'codigo_barras' => $producto->codigo_barras,
                        'codigo_proveedor' => $producto->codigo_proveedor,
                        'descripcion' => $producto->descripcion,
                        'precio' => $producto->precio,
                        'precio_base' => $producto->precio_base,
                        'cantidad' => $producto->cantidad,
                        'proveedor' => $producto->proveedor,
                        'categoria' => $producto->categoria,
                    ] : null,
                ];
            }),
            'base' => $orden->items->sum(function($item) {
                return ($item->precio_base ?? 0) * ($item->cantidad_descontada > 0 ? $item->cantidad_descontada : $item->cantidad);
            }),
            'venta' => $orden->items->sum(function($item) {
                return ($item->precio ?? 0) * ($item->cantidad_descontada > 0 ? $item->cantidad_descontada : $item->cantidad);
            }),
        ];
        
        return $pedidoFormato;
    }
    
    /**
     * Obtener sucursales disponibles para transferencia
     */
    public function getSucursalesDisponibles()
    {
        try {
            $sendCentral = new sendCentral();
            $response = $sendCentral->getSucursales();
            
            // El método getSucursales retorna un Response::json, necesitamos extraer los datos
            if (is_object($response) && method_exists($response, 'getData')) {
                $data = $response->getData(true);
                if (isset($data['estado']) && $data['estado'] && isset($data['msj'])) {
                    return Response::json([
                        'estado' => true,
                        'sucursales' => $data['msj']
                    ]);
                }
            }
            
            return Response::json([
                'estado' => false,
                'msj' => 'No se pudieron obtener las sucursales'
            ], 500);
            
        } catch (\Exception $e) {
            return Response::json([
                'estado' => false,
                'msj' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Transferir orden TCD a una sucursal
     */
    public function transferirOrdenASucursal(Request $request)
    {
        $chequeadorId = session('id_usuario');
        if (!$chequeadorId) {
            return Response::json([
                'estado' => false,
                'msj' => 'Usuario no autenticado'
            ], 401);
        }
        
        $request->validate([
            'orden_id' => 'required|exists:tcd_ordenes,id',
            'id_sucursal' => 'required|integer',
        ]);
        
        try {
            DB::beginTransaction();
            
            $orden = TCDOrden::where('id', $request->orden_id)
                ->where('chequeador_id', $chequeadorId)
                ->with(['items.inventario.proveedor', 'items.inventario.categoria'])
                ->firstOrFail();
            
            // Verificar que la orden esté completada y tenga ticket de despacho
            if ($orden->estado !== 'completada') {
                throw new \Exception('La orden debe estar completada para poder transferirla');
            }
            
            if (!$orden->ticketDespacho) {
                throw new \Exception('La orden debe tener un ticket de despacho generado para poder transferirla');
            }
            
            // Verificar si la orden ya fue transferida
            if ($orden->fecha_transferencia) {
                throw new \Exception('Esta orden ya fue transferida el ' . $orden->fecha_transferencia->format('Y-m-d H:i:s') . ' a la sucursal ' . ($orden->sucursal_destino_codigo ?? 'ID: ' . $orden->sucursal_destino_id));
            }
            
            // Obtener información de la sucursal destino
            $sendCentral = new sendCentral();
            $sucursalesResponse = $sendCentral->getSucursales();
            $sucursalDestino = null;
            $sucursalDestinoCodigo = null;
            
            // Extraer datos de la respuesta de getSucursales
            if (is_object($sucursalesResponse) && method_exists($sucursalesResponse, 'getData')) {
                $sucursalesData = $sucursalesResponse->getData(true);
                if (isset($sucursalesData['estado']) && $sucursalesData['estado'] && isset($sucursalesData['msj'])) {
                    $sucursales = $sucursalesData['msj'];
                    
                    // Buscar la sucursal por ID
                    if (is_array($sucursales)) {
                        foreach ($sucursales as $suc) {
                            if (isset($suc['id']) && $suc['id'] == $request->id_sucursal) {
                                $sucursalDestino = $suc;
                                $sucursalDestinoCodigo = $suc['codigo'] ?? null;
                                break;
                            } elseif (isset($suc['id_sucursal']) && $suc['id_sucursal'] == $request->id_sucursal) {
                                $sucursalDestino = $suc;
                                $sucursalDestinoCodigo = $suc['codigo'] ?? null;
                                break;
                            }
                        }
                    }
                }
            }
            
            // Convertir orden TCD al formato de pedido
            $pedidoFormato = $this->convertirOrdenTCDAPedido($orden);
            
                // Preparar los datos en el formato que espera setPedidoInCentralFromMaster
            $codigo_origen = $sendCentral->getOrigen();
            
            $response = Http::post(
                $sendCentral->path() . "/setPedidoInCentralFromMasters", [
                    "codigo_origen" => $codigo_origen,
                    "id_sucursal" => $request->id_sucursal,
                    "type" => "add",
                    "pedidos" => [$pedidoFormato], // Array con un solo pedido
                    "tipo_pedido" => "TCD", // Indicar que es una orden TCD
                    "tcd_orden_id" => $orden->id, // ID de la orden TCD original
                ]
            );
            
            if ($response->ok()) {
                $resretur = $response->json();
                
                if ($resretur["estado"]) {
                    // Obtener número de pedido de la respuesta
                    $pedidoCentralNumero = null;
                    if (isset($resretur['pedidos']) && is_array($resretur['pedidos']) && count($resretur['pedidos']) > 0) {
                        // Si viene un array de pedidos, tomar el primero
                        $pedidoCentralNumero = $resretur['pedidos'][0]['numero'] ?? 
                                               $resretur['pedidos'][0]['id'] ?? 
                                               $resretur['pedidos'][0]['numero_pedido'] ?? null;
                    } elseif (isset($resretur['pedido'])) {
                        // Si viene un solo pedido
                        $pedidoCentralNumero = $resretur['pedido']['numero'] ?? 
                                               $resretur['pedido']['id'] ?? 
                                               $resretur['pedido']['numero_pedido'] ?? null;
                    } elseif (isset($resretur['numero_pedido'])) {
                        $pedidoCentralNumero = $resretur['numero_pedido'];
                    } elseif (isset($resretur['id'])) {
                        $pedidoCentralNumero = $resretur['id'];
                    }
                    
                    // Guardar información de transferencia en la orden
                    $orden->sucursal_destino_id = $request->id_sucursal;
                    $orden->sucursal_destino_codigo = $sucursalDestinoCodigo;
                    $orden->fecha_transferencia = now();
                    $orden->pedido_central_numero = $pedidoCentralNumero ? (string)$pedidoCentralNumero : null;
                    $orden->save();
                    
                    DB::commit();
                    
                    return Response::json([
                        'estado' => true,
                        'msj' => 'Orden TCD transferida exitosamente a la sucursal',
                        'data' => $resretur,
                        'transferencia' => [
                            'sucursal_destino_id' => $orden->sucursal_destino_id,
                            'sucursal_destino_codigo' => $orden->sucursal_destino_codigo,
                            'fecha_transferencia' => $orden->fecha_transferencia,
                            'pedido_central_numero' => $orden->pedido_central_numero,
                        ]
                    ]);
                } else {
                    throw new \Exception($resretur['msj'] ?? 'Error al transferir la orden');
                }
            } else {
                throw new \Exception('Error de comunicación con central: ' . $response->status());
            }
            
        } catch (\Exception $e) {
            DB::rollBack();
            return Response::json([
                'estado' => false,
                'msj' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}

