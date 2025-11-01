<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Models\WarehouseMovement;
use App\Models\inventario;
use Illuminate\Http\Request;
use Response;
use DB;
use Auth;

class WarehouseInventoryController extends Controller
{
    /**
     * Vista principal de gestión de inventario en ubicaciones
     */
    public function index(Request $request)
    {
        $query = WarehouseInventory::with(['warehouse', 'inventario']);
        
        // Filtros
        if ($request->has('warehouse_id') && $request->warehouse_id) {
            $query->where('warehouse_id', $request->warehouse_id);
        }
        
        if ($request->has('inventario_id') && $request->inventario_id) {
            $query->where('inventario_id', $request->inventario_id);
        }
        
        if ($request->has('estado') && $request->estado) {
            $query->where('estado', $request->estado);
        }
        
        if ($request->has('lote') && $request->lote) {
            $query->where('lote', 'like', "%{$request->lote}%");
        }
        
        if ($request->has('buscar') && $request->buscar) {
            $buscar = $request->buscar;
            $query->whereHas('inventario', function($q) use ($buscar) {
                $q->where('descripcion', 'like', "%{$buscar}%")
                  ->orWhere('codigo_barras', 'like', "%{$buscar}%");
            });
        }
        
        $inventarios = $query->conStock()
                            ->orderBy('created_at', 'desc')
                            ->paginate(50);
        
        $warehouses = Warehouse::activas()->orderBy('codigo')->get();
        
        // Si es una petición AJAX, devolver JSON
        if ($request->ajax() || $request->wantsJson()) {
            return Response::json($inventarios);
        }
        
        return view('warehouse-inventory.index', compact('inventarios', 'warehouses'));
    }

    /**
     * Consultar inventario disponible por ubicación
     */
    public function porUbicacion(Request $request)
    {
        $warehouses = Warehouse::activas()->orderBy('codigo')->get();
        
        $inventarios = collect();
        $warehouse = null;
        
        if ($request->has('warehouse_id') && $request->warehouse_id) {
            $warehouse = Warehouse::with(['inventarios.inventario.proveedor', 'inventarios.inventario.categoria'])
                ->findOrFail($request->warehouse_id);
            
            $query = WarehouseInventory::with(['inventario.proveedor', 'inventario.categoria'])
                ->where('warehouse_id', $request->warehouse_id)
                ->conStock();
            
            // Filtros adicionales
            if ($request->has('estado') && $request->estado) {
                $query->where('estado', $request->estado);
            }
            
            if ($request->has('buscar') && $request->buscar) {
                $buscar = $request->buscar;
                $query->whereHas('inventario', function($q) use ($buscar) {
                    $q->where('descripcion', 'like', "%{$buscar}%")
                      ->orWhere('codigo_barras', 'like', "%{$buscar}%")
                      ->orWhere('codigo_proveedor', 'like', "%{$buscar}%");
                });
            }
            
            $inventarios = $query->orderBy('fecha_entrada', 'desc')->paginate(50);
            
            // Si es AJAX, devolver JSON
            if ($request->ajax() || $request->wantsJson()) {
                return Response::json([
                    'inventarios' => $inventarios,
                    'warehouse' => $warehouse,
                    'resumen' => [
                        'total_productos' => $inventarios->total(),
                        'total_cantidad' => $inventarios->sum('cantidad'),
                        'capacidad_utilizada' => $warehouse->inventarios()->conStock()->sum('cantidad'),
                        'capacidad_disponible' => $warehouse->capacidadDisponible(),
                    ]
                ]);
            }
        }
        
        return view('warehouse-inventory.por-ubicacion', compact('warehouses', 'inventarios', 'warehouse'));
    }

    /**
     * Asignar producto a ubicación
     */
    public function asignar(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'inventario_id' => 'required|exists:inventarios,id',
            'cantidad' => 'required|numeric|min:0.01',
            'fecha_entrada' => 'required|date',
        ]);
        
        try {
            DB::beginTransaction();
            
            $warehouse = Warehouse::findOrFail($request->warehouse_id);
            $producto = inventario::findOrFail($request->inventario_id);
            
            // Verificar capacidad
            if (!$warehouse->tieneCapacidad($request->cantidad)) {
                return Response::json([
                    'msj' => 'La ubicación no tiene capacidad suficiente',
                    'estado' => false
                ]);
            }
            
            // Verificar si ya existe el producto en la ubicación con el mismo lote
            $existente = WarehouseInventory::where('warehouse_id', $request->warehouse_id)
                ->where('inventario_id', $request->inventario_id)
                ->where('lote', $request->lote)
                ->first();
            
            if ($existente) {
                // Actualizar cantidad existente
                $existente->cantidad += $request->cantidad;
                $existente->save();
                
                $warehouseInventory = $existente;
                $accion = 'actualizado';
            } else {
                // Crear nuevo registro
                $warehouseInventory = WarehouseInventory::create([
                    'warehouse_id' => $request->warehouse_id,
                    'inventario_id' => $request->inventario_id,
                    'cantidad' => $request->cantidad,
                    'lote' => $request->lote,
                    'fecha_vencimiento' => $request->fecha_vencimiento,
                    'fecha_entrada' => $request->fecha_entrada,
                    'estado' => 'disponible',
                    'observaciones' => $request->observaciones,
                ]);
                
                $accion = 'asignado';
            }
            
            // Registrar movimiento
            WarehouseMovement::create([
                'tipo' => 'entrada',
                'inventario_id' => $request->inventario_id,
                'warehouse_origen_id' => null,
                'warehouse_destino_id' => $request->warehouse_id,
                'cantidad' => $request->cantidad,
                'lote' => $request->lote,
                'fecha_vencimiento' => $request->fecha_vencimiento,
                'usuario_id' => Auth::id(),
                'documento_referencia' => $request->documento_referencia,
                'observaciones' => $request->observaciones,
                'fecha_movimiento' => now(),
            ]);
            
            DB::commit();
            
            return Response::json([
                'msj' => "Producto {$accion} a ubicación {$warehouse->codigo} exitosamente",
                'estado' => true,
                'warehouse_inventory' => $warehouseInventory
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return Response::json([
                'msj' => 'Error: ' . $e->getMessage(),
                'estado' => false
            ], 500);
        }
    }

    /**
     * Transferir producto entre ubicaciones
     */
    public function transferir(Request $request)
    {
        $request->validate([
            'warehouse_origen_id' => 'required|exists:warehouses,id',
            'warehouse_destino_id' => 'required|exists:warehouses,id|different:warehouse_origen_id',
            'inventario_id' => 'required|exists:inventarios,id',
            'cantidad' => 'required|numeric|min:0.01',
        ]);
        
        try {
            DB::beginTransaction();
            
            $warehouseOrigen = Warehouse::findOrFail($request->warehouse_origen_id);
            $warehouseDestino = Warehouse::findOrFail($request->warehouse_destino_id);
            
            // Buscar inventario en origen
            $inventarioOrigen = WarehouseInventory::where('warehouse_id', $request->warehouse_origen_id)
                ->where('inventario_id', $request->inventario_id)
                ->where('estado', 'disponible')
                ->conStock()
                ->first();
            
            if (!$inventarioOrigen) {
                return Response::json([
                    'msj' => 'No hay stock disponible en la ubicación de origen',
                    'estado' => false
                ]);
            }
            
            if ($inventarioOrigen->cantidad < $request->cantidad) {
                return Response::json([
                    'msj' => "Stock insuficiente. Disponible: {$inventarioOrigen->cantidad}",
                    'estado' => false
                ]);
            }
            
            // Verificar capacidad en destino
            if (!$warehouseDestino->tieneCapacidad($request->cantidad)) {
                return Response::json([
                    'msj' => 'La ubicación de destino no tiene capacidad suficiente',
                    'estado' => false
                ]);
            }
            
            // Restar del origen
            $inventarioOrigen->cantidad -= $request->cantidad;
            $inventarioOrigen->save();
            
            // Buscar o crear en destino con el mismo lote
            $inventarioDestino = WarehouseInventory::where('warehouse_id', $request->warehouse_destino_id)
                ->where('inventario_id', $request->inventario_id)
                ->where('lote', $inventarioOrigen->lote)
                ->first();
            
            if ($inventarioDestino) {
                $inventarioDestino->cantidad += $request->cantidad;
                $inventarioDestino->save();
            } else {
                $inventarioDestino = WarehouseInventory::create([
                    'warehouse_id' => $request->warehouse_destino_id,
                    'inventario_id' => $request->inventario_id,
                    'cantidad' => $request->cantidad,
                    'lote' => $inventarioOrigen->lote,
                    'fecha_vencimiento' => $inventarioOrigen->fecha_vencimiento,
                    'fecha_entrada' => now(),
                    'estado' => 'disponible',
                    'observaciones' => $request->observaciones,
                ]);
            }
            
            // Registrar movimiento
            WarehouseMovement::create([
                'tipo' => 'transferencia',
                'inventario_id' => $request->inventario_id,
                'warehouse_origen_id' => $request->warehouse_origen_id,
                'warehouse_destino_id' => $request->warehouse_destino_id,
                'cantidad' => $request->cantidad,
                'lote' => $inventarioOrigen->lote,
                'fecha_vencimiento' => $inventarioOrigen->fecha_vencimiento,
                'usuario_id' => Auth::id(),
                'documento_referencia' => $request->documento_referencia,
                'observaciones' => $request->observaciones,
                'fecha_movimiento' => now(),
            ]);
            
            DB::commit();
            
            return Response::json([
                'msj' => "Transferencia exitosa de {$warehouseOrigen->codigo} a {$warehouseDestino->codigo}",
                'estado' => true
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return Response::json([
                'msj' => 'Error: ' . $e->getMessage(),
                'estado' => false
            ], 500);
        }
    }

    /**
     * Retirar producto de ubicación (salida)
     */
    public function retirar(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'inventario_id' => 'required|exists:inventarios,id',
            'cantidad' => 'required|numeric|min:0.01',
        ]);
        
        try {
            DB::beginTransaction();
            
            $warehouse = Warehouse::findOrFail($request->warehouse_id);
            
            // Buscar inventario disponible
            $warehouseInventory = WarehouseInventory::where('warehouse_id', $request->warehouse_id)
                ->where('inventario_id', $request->inventario_id)
                ->where('estado', 'disponible')
                ->conStock()
                ->first();
            
            if (!$warehouseInventory) {
                return Response::json([
                    'msj' => 'No hay stock disponible en esta ubicación',
                    'estado' => false
                ]);
            }
            
            if ($warehouseInventory->cantidad < $request->cantidad) {
                return Response::json([
                    'msj' => "Stock insuficiente. Disponible: {$warehouseInventory->cantidad}",
                    'estado' => false
                ]);
            }
            
            // Restar cantidad
            $warehouseInventory->cantidad -= $request->cantidad;
            $warehouseInventory->save();
            
            // Registrar movimiento de salida
            WarehouseMovement::create([
                'tipo' => 'salida',
                'inventario_id' => $request->inventario_id,
                'warehouse_origen_id' => $request->warehouse_id,
                'warehouse_destino_id' => null,
                'cantidad' => $request->cantidad,
                'lote' => $warehouseInventory->lote,
                'fecha_vencimiento' => $warehouseInventory->fecha_vencimiento,
                'usuario_id' => Auth::id(),
                'documento_referencia' => $request->documento_referencia,
                'observaciones' => $request->observaciones,
                'fecha_movimiento' => now(),
            ]);
            
            DB::commit();
            
            return Response::json([
                'msj' => "Retiro exitoso de {$warehouse->codigo}",
                'estado' => true
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return Response::json([
                'msj' => 'Error: ' . $e->getMessage(),
                'estado' => false
            ], 500);
        }
    }

    /**
     * Consultar ubicaciones de un producto
     */
    public function consultarUbicaciones($inventarioId)
    {
        $producto = inventario::with(['proveedor', 'categoria', 'marca'])->findOrFail($inventarioId);
        
        $ubicaciones = WarehouseInventory::with(['warehouse'])
            ->where('inventario_id', $inventarioId)
            ->conStock()
            ->orderBy('fecha_entrada', 'asc')
            ->get();
        
        $totalStock = $ubicaciones->sum('cantidad');
        
        return view('warehouse-inventory.consultar', compact('producto', 'ubicaciones', 'totalStock'));
    }

    /**
     * Consultar productos en una ubicación específica
     */
    public function consultarPorUbicacion($warehouseId)
    {
        $warehouse = Warehouse::findOrFail($warehouseId);
        
        $productos = WarehouseInventory::with(['inventario.proveedor', 'inventario.categoria'])
            ->where('warehouse_id', $warehouseId)
            ->conStock()
            ->orderBy('fecha_entrada', 'asc')
            ->get();
        
        $totalProductos = $productos->count();
        $totalUnidades = $productos->sum('cantidad');
        
        return view('warehouse-inventory.por-ubicacion', compact('warehouse', 'productos', 'totalProductos', 'totalUnidades'));
    }

    /**
     * Historial de movimientos
     */
    public function historial(Request $request)
    {
        $query = WarehouseMovement::with(['inventario', 'warehouseOrigen', 'warehouseDestino', 'usuario']);
        
        // Filtros
        if ($request->has('tipo')) {
            $query->tipo($request->tipo);
        }
        
        if ($request->has('inventario_id')) {
            $query->porProducto($request->inventario_id);
        }
        
        if ($request->has('warehouse_id')) {
            $query->where(function($q) use ($request) {
                $q->where('warehouse_origen_id', $request->warehouse_id)
                  ->orWhere('warehouse_destino_id', $request->warehouse_id);
            });
        }
        
        if ($request->has('fecha_inicio') && $request->has('fecha_fin')) {
            $query->entreFechas($request->fecha_inicio, $request->fecha_fin);
        }
        
        $movimientos = $query->orderBy('fecha_movimiento', 'desc')->paginate(50);
        
        $warehouses = Warehouse::activas()->orderBy('codigo')->get();
        
        return view('warehouse-inventory.historial', compact('movimientos', 'warehouses'));
    }

    /**
     * Actualizar estado de inventario en ubicación
     */
    public function actualizarEstado(Request $request, $id)
    {
        $request->validate([
            'estado' => 'required|in:disponible,reservado,bloqueado,dañado'
        ]);
        
        try {
            $warehouseInventory = WarehouseInventory::findOrFail($id);
            $warehouseInventory->estado = $request->estado;
            $warehouseInventory->observaciones = $request->observaciones;
            $warehouseInventory->save();
            
            return Response::json([
                'msj' => 'Estado actualizado exitosamente',
                'estado' => true
            ]);
            
        } catch (\Exception $e) {
            return Response::json([
                'msj' => 'Error: ' . $e->getMessage(),
                'estado' => false
            ], 500);
        }
    }

    /**
     * Productos próximos a vencer
     */
    public function proximosVencer(Request $request)
    {
        $dias = $request->dias ?? 30;
        
        $productos = WarehouseInventory::with(['inventario', 'warehouse'])
            ->proximosVencer($dias)
            ->conStock()
            ->orderBy('fecha_vencimiento', 'asc')
            ->get();
        
        return view('warehouse-inventory.proximos-vencer', compact('productos', 'dias'));
    }

    /**
     * Buscar productos para asignar a ubicación
     */
    public function buscarProductos(Request $request)
    {
        $buscar = $request->buscar;
        
        if (!$buscar || strlen($buscar) < 2) {
            return Response::json([
                'productos' => []
            ]);
        }
        
        $productos = inventario::with(['proveedor', 'categoria', 'marca'])
            ->where(function($query) use ($buscar) {
                $query->where('codigo_barras', 'like', "%{$buscar}%")
                      ->orWhere('codigo_proveedor', 'like', "%{$buscar}%")
                      ->orWhere('descripcion', 'like', "%{$buscar}%");
            })
            ->limit(20)
            ->get()
            ->map(function($producto) {
                return [
                    'id' => $producto->id,
                    'codigo_barras' => $producto->codigo_barras,
                    'codigo_proveedor' => $producto->codigo_proveedor,
                    'descripcion' => $producto->descripcion,
                    'proveedor' => $producto->proveedor->razonsocial ?? 'N/A',
                    'categoria' => $producto->categoria->nombre ?? 'N/A',
                    'precio' => $producto->precio_venta,
                ];
            });
        
        return Response::json([
            'productos' => $productos
        ]);
    }
}
