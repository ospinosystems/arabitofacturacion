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
        // Consulta unificada de productos
        $query = inventario::with(['proveedor', 'categoria', 'marca', 'warehouseInventory.warehouse']);
        
        // Filtro de búsqueda
        if ($request->has('buscar') && $request->buscar) {
            $buscar = $request->buscar;
            $campo = $request->campo_busqueda ?? 'todos';
            
            $query->where(function($q) use ($buscar, $campo) {
                if ($campo === 'codigo_barras' || $campo === 'todos') {
                    $q->orWhere('codigo_barras', 'like', "%{$buscar}%");
                }
                if ($campo === 'codigo_proveedor' || $campo === 'todos') {
                    $q->orWhere('codigo_proveedor', 'like', "%{$buscar}%");
                }
                if ($campo === 'descripcion' || $campo === 'todos') {
                    $q->orWhere('descripcion', 'like', "%{$buscar}%");
                }
            });
        }
        
        // Filtro por estado de warehouse (nuevo filtro unificado)
        if ($request->has('filtro_warehouse') && $request->filtro_warehouse !== '') {
            if ($request->filtro_warehouse === 'con_warehouse') {
                // Solo productos que tienen warehouse asignado
                $query->whereHas('warehouseInventory', function($q) {
                    $q->where('cantidad', '>', 0);
                });
            } elseif ($request->filtro_warehouse === 'sin_warehouse') {
                // Solo productos que NO tienen warehouse asignado
                $query->whereDoesntHave('warehouseInventory', function($q) {
                    $q->where('cantidad', '>', 0);
                });
            }
            // Si es 'ambos' o vacío, no filtrar
        }
        
        // Filtro por categoría
        if ($request->has('categoria_id') && $request->categoria_id) {
            $query->where('id_categoria', $request->categoria_id);
        }
        
        // Filtro por proveedor
        if ($request->has('proveedor_id') && $request->proveedor_id) {
            $query->where('id_proveedor', $request->proveedor_id);
        }
        
        // Filtro por marca
        if ($request->has('marca_id') && $request->marca_id) {
            $query->where('id_marca', $request->marca_id);
        }
        
        // NUEVO: Filtro por ubicación específica
        if ($request->has('ubicacion_id') && $request->ubicacion_id) {
            $query->whereHas('warehouseInventory', function($q) use ($request) {
                $q->where('warehouse_id', $request->ubicacion_id)
                  ->where('cantidad', '>', 0);
            });
        }
        
        $productos = $query->orderBy('descripcion', 'asc')->paginate(50);
        
        // Agregar información adicional a cada producto
        $productos->getCollection()->transform(function($producto) {
            $warehouseInventories = $producto->warehouseInventory()->where('cantidad', '>', 0)->get();
            
            $producto->tiene_warehouse = $warehouseInventories->count() > 0;
            $producto->total_en_warehouses = $warehouseInventories->sum('cantidad');
            $producto->numero_ubicaciones = $warehouseInventories->count();
            
            // Calcular cantidad sin asignar (stock total - stock en warehouses)
            $stockTotal = $producto->cantidad ?? 0;
            $producto->sin_ubicar = max(0, $stockTotal - $producto->total_en_warehouses);
            
            $producto->ubicaciones_detalle = $warehouseInventories->map(function($wi) {
                return [
                    'id' => $wi->id,
                    'warehouse_id' => $wi->warehouse_id,
                    'warehouse' => $wi->warehouse->codigo ?? 'N/A',
                    'warehouse_nombre' => $wi->warehouse->nombre ?? '',
                    'cantidad' => $wi->cantidad,
                    'lote' => $wi->lote,
                    'estado' => $wi->estado,
                    'fecha_entrada' => $wi->fecha_entrada,
                    'fecha_vencimiento' => $wi->fecha_vencimiento,
                ];
            });
            
            return $producto;
        });
        
        // Obtener datos para filtros
        $warehouses = Warehouse::activas()->orderBy('codigo')->get();
        $categorias = \App\Models\categorias::orderBy('descripcion')->get();
        $proveedores = \App\Models\proveedores::orderBy('descripcion')->get();
        $marcas = \App\Models\marcas::orderBy('descripcion')->get();
        
        // Si es AJAX, devolver JSON
        if ($request->ajax() || $request->wantsJson()) {
            return Response::json([
                'estado' => true,
                'data' => $productos
            ]);
        }
        
        return view('warehouse-inventory.index', compact('productos', 'warehouses', 'categorias', 'proveedores', 'marcas'));
    }

    /**
     * Buscar productos generales del inventario con información de warehouse
     */
    private function buscarProductosGenerales(Request $request)
    {
        $query = inventario::with(['proveedor', 'categoria', 'marca', 'warehouseInventory.warehouse']);
        
        // Filtro por campo de búsqueda
        if ($request->has('buscar') && $request->buscar) {
            $buscar = $request->buscar;
            $campo = $request->campo_busqueda ?? 'todos';
            
            $query->where(function($q) use ($buscar, $campo) {
                if ($campo === 'codigo_barras' || $campo === 'todos') {
                    $q->orWhere('codigo_barras', 'like', "%{$buscar}%");
                }
                if ($campo === 'codigo_proveedor' || $campo === 'todos') {
                    $q->orWhere('codigo_proveedor', 'like', "%{$buscar}%");
                }
                if ($campo === 'descripcion' || $campo === 'todos') {
                    $q->orWhere('descripcion', 'like', "%{$buscar}%");
                }
            });
        }
        
        // Filtro por asignación a warehouse
        if ($request->has('filtro_warehouse')) {
            if ($request->filtro_warehouse === 'con_warehouse') {
                // Productos que tienen al menos un warehouse asignado
                $query->whereHas('warehouseInventory', function($q) {
                    $q->where('cantidad', '>', 0);
                });
            } elseif ($request->filtro_warehouse === 'sin_warehouse') {
                // Productos que NO tienen warehouse asignado
                $query->whereDoesntHave('warehouseInventory', function($q) {
                    $q->where('cantidad', '>', 0);
                });
            }
        }
        
        // Filtro por categoría
        if ($request->has('categoria_id') && $request->categoria_id) {
            $query->where('id_categoria', $request->categoria_id);
        }
        
        // Filtro por proveedor
        if ($request->has('proveedor_id') && $request->proveedor_id) {
            $query->where('id_proveedor', $request->proveedor_id);
        }
        
        $productos = $query->orderBy('descripcion', 'asc')->paginate(50);
        
        // Agregar información adicional a cada producto
        $productos->getCollection()->transform(function($producto) {
            $warehouseInventories = $producto->warehouseInventory()->where('cantidad', '>', 0)->get();
            
            $producto->tiene_warehouse = $warehouseInventories->count() > 0;
            $producto->total_en_warehouses = $warehouseInventories->sum('cantidad');
            $producto->numero_ubicaciones = $warehouseInventories->count();
            $producto->ubicaciones_resumen = $warehouseInventories->map(function($wi) {
                return [
                    'warehouse' => $wi->warehouse->codigo,
                    'cantidad' => $wi->cantidad,
                    'lote' => $wi->lote,
                ];
            });
            
            return $producto;
        });
        
        $warehouses = Warehouse::activas()->orderBy('codigo')->get();
        $categorias = \App\Models\categoria::orderBy('nombre')->get();
        $proveedores = \App\Models\proveedores::orderBy('razonsocial')->get();
        
        // Si es AJAX, devolver JSON
        if ($request->ajax() || $request->wantsJson()) {
            return Response::json($productos);
        }
        
        return view('warehouse-inventory.index', compact('productos', 'warehouses', 'categorias', 'proveedores'));
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
     * Imprimir ticket de ubicación
     */
    public function imprimirTicket($id)
    {
        $inventario = WarehouseInventory::with(['warehouse', 'inventario'])
            ->findOrFail($id);
        
        return view('warehouse-inventory.ticket', compact('inventario'));
    }

    /**
     * Imprimir ticket de producto (sin ubicación) - Recibe datos del frontend
     */
    public function imprimirTicketProducto(Request $request)
    {
        // Crear objeto con los datos recibidos
        $producto = (object) [
            'codigo_barras' => $request->input('codigo_barras'),
            'codigo_proveedor' => $request->input('codigo_proveedor'),
            'descripcion' => $request->input('descripcion'),
            'marca' => $request->input('marca')
        ];
        
        return view('warehouse-inventory.ticket-producto', compact('producto'));
    }

    /**
     * Buscar por código de barras (pistoleo)
     */
    public function buscarPorCodigo(Request $request)
    {
        $codigo = $request->codigo;
        
        if (!$codigo) {
            return view('warehouse-inventory.buscar-codigo');
        }
        
        // Extraer el ID del código (formato: WH-000123)
        $id = null;
        if (preg_match('/WH-?(\d+)/i', $codigo, $matches)) {
            $id = (int) $matches[1];
        }
        
        if (!$id) {
            return view('warehouse-inventory.buscar-codigo', [
                'error' => 'Código de barras no válido. Formato esperado: WH-000123'
            ]);
        }
        
        $inventario = WarehouseInventory::with(['warehouse', 'inventario.proveedor', 'inventario.categoria'])
            ->find($id);
        
        if (!$inventario) {
            return view('warehouse-inventory.buscar-codigo', [
                'error' => 'No se encontró el registro con código: ' . $codigo
            ]);
        }
        
        return view('warehouse-inventory.buscar-codigo', compact('inventario', 'codigo'));
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
     * Obtener ubicaciones de un producto en formato JSON (API)
     */
    public function obtenerUbicacionesProducto($inventarioId)
    {
        try {
            $producto = inventario::findOrFail($inventarioId);
            
            $ubicaciones = WarehouseInventory::with(['warehouse'])
                ->where('inventario_id', $inventarioId)
                ->conStock()
                ->orderBy('fecha_entrada', 'asc')
                ->get();
            
            $totalStock = $ubicaciones->sum('cantidad');
            
            $ubicacionesData = $ubicaciones->map(function($ubicacion) {
                return [
                    'id' => $ubicacion->id,
                    'warehouse_id' => $ubicacion->warehouse_id,
                    'codigo' => $ubicacion->warehouse->codigo ?? 'N/A',
                    'nombre' => $ubicacion->warehouse->nombre ?? 'Sin nombre',
                    'cantidad' => $ubicacion->cantidad,
                    'fecha_entrada' => $ubicacion->fecha_entrada,
                    'lote' => $ubicacion->lote,
                ];
            });
            
            return Response::json([
                'estado' => true,
                'producto' => [
                    'id' => $producto->id,
                    'codigo_barras' => $producto->codigo_barras,
                    'descripcion' => $producto->descripcion,
                ],
                'ubicaciones' => $ubicacionesData,
                'total_stock' => $totalStock,
                'total_ubicaciones' => $ubicaciones->count()
            ]);
        } catch (\Exception $e) {
            return Response::json([
                'estado' => false,
                'msj' => 'Error al obtener ubicaciones: ' . $e->getMessage()
            ], 500);
        }
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

    /**
     * Obtener o crear la ubicación genérica
     */
    private function obtenerUbicacionGenerica()
    {
        $ubicacionGenerica = Warehouse::where('codigo', 'GENERICA')->first();
        
        if (!$ubicacionGenerica) {
            $ubicacionGenerica = Warehouse::create([
                'pasillo' => 'GEN',
                'cara' => 0,
                'rack' => 0,
                'nivel' => 0,
                'codigo' => 'GENERICA',
                'nombre' => 'Ubicación Genérica',
                'descripcion' => 'Ubicación genérica para productos sin asignación específica',
                'tipo' => 'almacenamiento',
                'estado' => 'activa',
                'zona' => 'general',
                'capacidad_peso' => null,
                'capacidad_volumen' => null,
                'capacidad_unidades' => null, // Sin límite
            ]);
        }
        
        return $ubicacionGenerica;
    }

    /**
     * Buscar productos con cantidad en inventario pero sin ubicación asignada
     */
    public function buscarProductosSinUbicacion(Request $request)
    {
        try {
            // Productos con cantidad > 0 que NO tienen ningún registro en warehouse_inventory
            // O que tienen menos cantidad en warehouse_inventory que en inventarios
            $productos = inventario::where('cantidad', '>', 0)
                ->get()
                ->filter(function($producto) {
                    $cantidadEnWarehouses = WarehouseInventory::where('inventario_id', $producto->id)
                        ->where('cantidad', '>', 0)
                        ->sum('cantidad');
                    
                    // Retornar si hay diferencia (cantidad sin ubicar)
                    return $producto->cantidad > $cantidadEnWarehouses;
                })
                ->map(function($producto) {
                    $cantidadEnWarehouses = WarehouseInventory::where('inventario_id', $producto->id)
                        ->where('cantidad', '>', 0)
                        ->sum('cantidad');
                    
                    return [
                        'id' => $producto->id,
                        'codigo_barras' => $producto->codigo_barras,
                        'codigo_proveedor' => $producto->codigo_proveedor,
                        'descripcion' => $producto->descripcion,
                        'cantidad_total' => $producto->cantidad,
                        'cantidad_en_warehouses' => $cantidadEnWarehouses,
                        'cantidad_sin_ubicar' => $producto->cantidad - $cantidadEnWarehouses,
                    ];
                })
                ->values();
            
            return Response::json([
                'estado' => true,
                'total_productos' => $productos->count(),
                'total_unidades_sin_ubicar' => $productos->sum('cantidad_sin_ubicar'),
                'productos' => $productos
            ]);
            
        } catch (\Exception $e) {
            return Response::json([
                'estado' => false,
                'msj' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Asignar todos los productos sin ubicación a la ubicación genérica
     */
    public function asignarProductosAUbicacionGenerica(Request $request)
    {
        try {
            DB::beginTransaction();
            
            // Obtener o crear la ubicación genérica
            $ubicacionGenerica = $this->obtenerUbicacionGenerica();
            
            // Buscar productos con cantidad sin ubicar
            $productos = inventario::where('cantidad', '>', 0)->get();
            
            $productosAsignados = 0;
            $unidadesAsignadas = 0;
            $errores = [];
            
            foreach ($productos as $producto) {
                $cantidadEnWarehouses = WarehouseInventory::where('inventario_id', $producto->id)
                    ->where('cantidad', '>', 0)
                    ->sum('cantidad');
                
                $cantidadSinUbicar = $producto->cantidad - $cantidadEnWarehouses;
                
                if ($cantidadSinUbicar > 0) {
                    // Verificar si ya existe registro en ubicación genérica
                    $existente = WarehouseInventory::where('warehouse_id', $ubicacionGenerica->id)
                        ->where('inventario_id', $producto->id)
                        ->first();
                    
                    if ($existente) {
                        // Actualizar cantidad existente
                        $existente->cantidad += $cantidadSinUbicar;
                        $existente->save();
                    } else {
                        // Crear nuevo registro
                        WarehouseInventory::create([
                            'warehouse_id' => $ubicacionGenerica->id,
                            'inventario_id' => $producto->id,
                            'cantidad' => $cantidadSinUbicar,
                            'cantidad_bloqueada' => 0,
                            'lote' => null,
                            'fecha_vencimiento' => null,
                            'fecha_entrada' => now(),
                            'estado' => 'disponible',
                            'observaciones' => 'Asignación automática a ubicación genérica',
                        ]);
                    }
                    
                    // Registrar movimiento
                    WarehouseMovement::create([
                        'tipo' => 'entrada',
                        'inventario_id' => $producto->id,
                        'warehouse_origen_id' => null,
                        'warehouse_destino_id' => $ubicacionGenerica->id,
                        'cantidad' => $cantidadSinUbicar,
                        'lote' => null,
                        'fecha_vencimiento' => null,
                        'usuario_id' => Auth::id(),
                        'documento_referencia' => 'ASIG-GEN-' . now()->format('YmdHis'),
                        'observaciones' => 'Asignación automática a ubicación genérica',
                        'fecha_movimiento' => now(),
                    ]);
                    
                    $productosAsignados++;
                    $unidadesAsignadas += $cantidadSinUbicar;
                }
            }
            
            DB::commit();
            
            return Response::json([
                'estado' => true,
                'msj' => "Proceso completado exitosamente",
                'ubicacion_generica' => [
                    'id' => $ubicacionGenerica->id,
                    'codigo' => $ubicacionGenerica->codigo,
                    'nombre' => $ubicacionGenerica->nombre,
                ],
                'resumen' => [
                    'productos_asignados' => $productosAsignados,
                    'unidades_asignadas' => $unidadesAsignadas,
                ],
                'errores' => $errores
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
     * Consultar stock disponible en una ubicación específica para un producto
     */
    public function consultarStockUbicacion(Request $request)
    {
        try {
            $warehouseId = $request->warehouse_id;
            $inventarioId = $request->inventario_id;
            
            if (!$warehouseId || !$inventarioId) {
                return Response::json([
                    'estado' => false,
                    'cantidad' => 0,
                    'msj' => 'Parámetros faltantes'
                ]);
            }
            
            $warehouseInventory = WarehouseInventory::where('warehouse_id', $warehouseId)
                ->where('inventario_id', $inventarioId)
                ->where('estado', 'disponible')
                ->where('cantidad', '>', 0)
                ->first();
            
            if ($warehouseInventory) {
                return Response::json([
                    'estado' => true,
                    'cantidad' => $warehouseInventory->cantidad,
                    'lote' => $warehouseInventory->lote,
                    'fecha_entrada' => $warehouseInventory->fecha_entrada,
                    'fecha_vencimiento' => $warehouseInventory->fecha_vencimiento
                ]);
            }
            
            return Response::json([
                'estado' => true,
                'cantidad' => 0,
                'msj' => 'No hay stock disponible en esta ubicación'
            ]);
            
        } catch (\Exception $e) {
            return Response::json([
                'estado' => false,
                'cantidad' => 0,
                'msj' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}
