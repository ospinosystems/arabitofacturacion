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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                ->get()
                ->map(function($producto) {
                    // Obtener ubicación del producto (primera ubicación con stock)
                    $warehouseInventory = WarehouseInventory::where('inventario_id', $producto->id)
                        ->where('cantidad', '>', 0)
                        ->with('warehouse')
                        ->first();
                    
                    $ubicacion = $warehouseInventory ? $warehouseInventory->warehouse->codigo ?? 'N/A' : 'N/A';
                    
                    return [
                        'id' => $producto->id,
                        'codigo_barras' => $producto->codigo_barras,
                        'codigo_proveedor' => $producto->codigo_proveedor,
                        'descripcion' => $producto->descripcion,
                        'precio' => $producto->precio,
                        'precio_base' => $producto->precio_base,
                        'ubicacion' => $ubicacion,
                        'stock_total' => WarehouseInventory::where('inventario_id', $producto->id)
                            ->where('cantidad', '>', 0)
                            ->sum('cantidad'),
                    ];
                });
            
            return Response::json([
                'estado' => true,
                'productos' => $productos
            ]);
        } catch (\Exception $e) {
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
            
            // Crear items de la orden
            foreach ($request->items as $item) {
                $producto = inventario::findOrFail($item['inventario_id']);
                
                // Obtener ubicación del producto
                $warehouseInventory = WarehouseInventory::where('inventario_id', $producto->id)
                    ->where('cantidad', '>', 0)
                    ->with('warehouse')
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
     * Obtener asignaciones del pasillero actual
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
        
        $asignaciones = TCDAsignacion::porPasillero($pasilleroId)
            ->whereIn('estado', ['pendiente', 'en_proceso'])
            ->with(['orden', 'ordenItem', 'warehouse'])
            ->orderBy('created_at', 'desc')
            ->get();
        
        return Response::json([
            'estado' => true,
            'asignaciones' => $asignaciones
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
            ->with(['items', 'asignaciones.pasillero', 'asignaciones.ordenItem'])
            ->firstOrFail();
        
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
                'asignaciones' => $asigs->values()
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
            ->with(['items', 'asignaciones'])
            ->orderBy('created_at', 'desc');
        
        if ($estado) {
            $query->where('estado', $estado);
        }
        
        $ordenes = $query->get();
        
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
                    
                    if (!$warehouseInventory || $warehouseInventory->cantidad < $asignacion->cantidad_procesada) {
                        throw new \Exception("Stock insuficiente en ubicación para el producto: {$item->descripcion}");
                    }
                    
                    // Descontar cantidad y bloquear
                    $warehouseInventory->cantidad -= $asignacion->cantidad_procesada;
                    $warehouseInventory->cantidad_bloqueada = ($warehouseInventory->cantidad_bloqueada ?? 0) + $asignacion->cantidad_procesada;
                    $warehouseInventory->save();
                    
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
}

