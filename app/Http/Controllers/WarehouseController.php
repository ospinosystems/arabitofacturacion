<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Models\inventario;
use Illuminate\Http\Request;
use Response;
use DB;

class WarehouseController extends Controller
{
    /**
     * Generar combinaciones de ubicaciones (almacenes) y guardarlas en la tabla warehouses.
     * 
     * Pasillos: 'A' a 'F'
     * Caras: '1', '2'
     * Racks: 1-100
     * Niveles: 1-4
     */
    public function generarUbicaciones(Request $request)
    {
        $pasillos = range('A', 'F');
        $caras = ['1', '2'];
        $racks = range(1, 100);
        $niveles = range(1, 4);

        $creados = 0;
        DB::beginTransaction();
        try {
            foreach ($pasillos as $pasillo) {
                foreach ($caras as $cara) {
                    foreach ($racks as $rack) {
                        foreach ($niveles as $nivel) {
                            $codigo = $pasillo . $cara . '-' . str_pad($rack, 2, '0', STR_PAD_LEFT) . '-' . $nivel;

                            Warehouse::updateOrCreate(
                                [
                                    'pasillo' => $pasillo,
                                    'cara' => $cara,
                                    'rack' => $rack,
                                    'nivel' => $nivel,
                                ],
                                [
                                    'codigo' => $codigo,
                                    'nombre' => $codigo,
                                    'descripcion' => null,
                                    'tipo' => 'almacenamiento',
                                    'estado' => 'activa',
                                    'zona' => null,
                                    // Puedes agregar más campos por defecto aquí si tu tabla los requiere
                                ]
                            );
                            $creados++;
                        }
                    }
                }
            }
            DB::commit();
            return response()->json([
                'message' => "Ubicaciones generadas correctamente.",
                'cantidad' => $creados
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Error generando las ubicaciones: ' . $e->getMessage()
            ], 500);
        }
    }
    public function index(Request $request)
    {
        $query = Warehouse::with(['inventarios.inventario']);
        
        // Filtros
        if ($request->has('estado') && trim($request->estado) !== '') {
            $query->where('estado', $request->estado);
        }
        
        if ($request->has('tipo') && trim($request->tipo) !== '') {
            $query->where('tipo', $request->tipo);
        }
        
        if ($request->has('zona') && trim($request->zona) !== '') {
            $query->where('zona', $request->zona);
        }
        
        if ($request->has('buscar')) {
            $buscar = $request->buscar;
            $query->where(function($q) use ($buscar) {
                $q->where('codigo', 'like', "%{$buscar}%")
                  ->orWhere('nombre', 'like', "%{$buscar}%")
                  ->orWhere('pasillo', 'like', "%{$buscar}%");
            });
        }
        
        $warehouses = $query->orderBy('pasillo')
                           ->orderBy('cara')
                           ->orderBy('rack')
                           ->orderBy('nivel')
                           ->paginate(50);
        
        return view('warehouses.index', compact('warehouses'));
    }

    /**
     * Buscar ubicaciones para asignar productos
     */
    public function buscarUbicaciones(Request $request)
    {
        $buscar = $request->buscar;
        
        if (!$buscar || strlen($buscar) < 1) {
            return Response::json([
                'ubicaciones' => []
            ]);
        }
        
        $ubicaciones = Warehouse::where(function($query) use ($buscar) {
                $query->where('codigo', 'like', "%{$buscar}%")
                      ->orWhere('nombre', 'like', "%{$buscar}%");
            })
            ->where('estado', 'activa')
            ->limit(15)
            ->get()
            ->map(function($warehouse) {
                return [
                    'id' => $warehouse->id,
                    'codigo' => $warehouse->codigo,
                    'nombre' => $warehouse->nombre,
                    'tipo' => $warehouse->tipo,
                    'ubicacion' => $warehouse->ubicacion,
                    'capacidad_maxima' => $warehouse->capacidad_maxima,
                ];
            });
        
        return Response::json([
            'ubicaciones' => $ubicaciones
        ]);
    }

    /**
     * Mostrar formulario para crear nueva ubicación
     */
    public function create()
    {
        return view('warehouses.create');
    }

    /**
     * Guardar nueva ubicación
     */
    public function store(Request $request)
    {
        $request->validate([
            'pasillo' => 'required|string|max:10',
            'cara' => 'required|integer|min:1',
            'rack' => 'required|integer|min:1',
            'nivel' => 'required|integer|min:1',
            'tipo' => 'required|in:almacenamiento,picking,recepcion,despacho',
            'estado' => 'required|in:activa,inactiva,mantenimiento,bloqueada',
        ]);
        
        try {
            // Verificar si ya existe esa ubicación
            $codigo = $request->pasillo . $request->cara . '-' . $request->rack . '-' . $request->nivel;
            $existe = Warehouse::where('codigo', $codigo)->first();
            
            if ($existe) {
                return Response::json([
                    'msj' => 'Ya existe una ubicación con ese código: ' . $codigo,
                    'estado' => false
                ]);
            }
            
            $warehouse = Warehouse::create([
                'pasillo' => strtoupper($request->pasillo),
                'cara' => $request->cara,
                'rack' => $request->rack,
                'nivel' => $request->nivel,
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion,
                'tipo' => $request->tipo,
                'estado' => $request->estado,
                'zona' => $request->zona,
                'capacidad_peso' => $request->capacidad_peso,
                'capacidad_volumen' => $request->capacidad_volumen,
                'capacidad_unidades' => $request->capacidad_unidades,
            ]);
            
            return Response::json([
                'msj' => 'Ubicación creada exitosamente: ' . $warehouse->codigo,
                'estado' => true,
                'warehouse' => $warehouse
            ]);
            
        } catch (\Exception $e) {
            return Response::json([
                'msj' => 'Error: ' . $e->getMessage(),
                'estado' => false
            ], 500);
        }
    }

    /**
     * Mostrar detalle de una ubicación específica
     */
    public function show($id)
    {
        $warehouse = Warehouse::with([
            'inventarios.inventario.proveedor',
            'inventarios.inventario.categoria',
            'inventarios.inventario.marca'
        ])->findOrFail($id);
        
        // Calcular totales
        $totalProductos = $warehouse->inventarios->count();
        $totalUnidades = $warehouse->inventarios->sum('cantidad');
        $ocupacion = $warehouse->ocupacion;
        
        return view('warehouses.show', compact('warehouse', 'totalProductos', 'totalUnidades', 'ocupacion'));
    }

    /**
     * Mostrar formulario para editar ubicación
     */
    public function edit($id)
    {
        $warehouse = Warehouse::findOrFail($id);
        return view('warehouses.edit', compact('warehouse'));
    }

    /**
     * Actualizar ubicación
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'pasillo' => 'required|string|max:10',
            'cara' => 'required|integer|min:1',
            'rack' => 'required|integer|min:1',
            'nivel' => 'required|integer|min:1',
            'tipo' => 'required|in:almacenamiento,picking,recepcion,despacho',
            'estado' => 'required|in:activa,inactiva,mantenimiento,bloqueada',
        ]);
        
        try {
            $warehouse = Warehouse::findOrFail($id);
            
            // Verificar si el nuevo código no existe (excepto el mismo registro)
            $codigo = $request->pasillo . $request->cara . '-' . $request->rack . '-' . $request->nivel;
            $existe = Warehouse::where('codigo', $codigo)
                               ->where('id', '!=', $id)
                               ->first();
            
            if ($existe) {
                return Response::json([
                    'msj' => 'Ya existe otra ubicación con ese código: ' . $codigo,
                    'estado' => false
                ]);
            }
            
            $warehouse->update([
                'pasillo' => strtoupper($request->pasillo),
                'cara' => $request->cara,
                'rack' => $request->rack,
                'nivel' => $request->nivel,
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion,
                'tipo' => $request->tipo,
                'estado' => $request->estado,
                'zona' => $request->zona,
                'capacidad_peso' => $request->capacidad_peso,
                'capacidad_volumen' => $request->capacidad_volumen,
                'capacidad_unidades' => $request->capacidad_unidades,
            ]);
            
            return Response::json([
                'msj' => 'Ubicación actualizada exitosamente',
                'estado' => true,
                'warehouse' => $warehouse
            ]);
            
        } catch (\Exception $e) {
            return Response::json([
                'msj' => 'Error: ' . $e->getMessage(),
                'estado' => false
            ], 500);
        }
    }

    /**
     * Eliminar ubicación
     */
    public function destroy($id)
    {
        try {
            $warehouse = Warehouse::findOrFail($id);
            
            // Verificar si tiene inventario
            $tieneInventario = $warehouse->inventarios()->conStock()->count();
            
            if ($tieneInventario > 0) {
                return Response::json([
                    'msj' => 'No se puede eliminar la ubicación porque tiene productos en stock',
                    'estado' => false
                ]);
            }
            
            $warehouse->delete();
            
            return Response::json([
                'msj' => 'Ubicación eliminada exitosamente',
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
     * Obtener ubicaciones disponibles (AJAX)
     */
    public function getDisponibles(Request $request)
    {
        $query = Warehouse::activas();
        
        if ($request->has('tipo')) {
            $query->tipo($request->tipo);
        }
        
        $warehouses = $query->orderBy('codigo')->get();
        
        return Response::json([
            'warehouses' => $warehouses,
            'estado' => true
        ]);
    }

    /**
     * Buscar ubicación por código (AJAX)
     */
    public function buscarPorCodigo(Request $request)
    {
        $codigo = $request->codigo;
        
        $warehouse = Warehouse::where('codigo', $codigo)
            ->where('estado', 'activa')
            ->first();
        
        if ($warehouse) {
            return Response::json([
                'data' => $warehouse,
                'estado' => true
            ]);
        }
        
        return Response::json([
            'estado' => false,
            'msj' => 'No se encontró ubicación con ese código'
        ]);
    }

    /**
     * Sugerir ubicaciones óptimas para un producto
     */
    public function sugerirUbicacion(Request $request)
    {
        $inventarioId = $request->inventario_id;
        $cantidad = $request->cantidad ?? 1;
        
        // Buscar ubicaciones disponibles con capacidad
        $warehouses = Warehouse::activas()
            ->tipo('almacenamiento')
            ->get()
            ->filter(function($w) use ($cantidad) {
                return $w->tieneCapacidad($cantidad);
            })
            ->take(5);
        
        return Response::json([
            'warehouses' => $warehouses,
            'estado' => true
        ]);
    }

    /**
     * Reporte de ocupación
     */
    public function reporteOcupacion()
    {
        $warehouses = Warehouse::with(['inventarios'])
            ->get()
            ->map(function($w) {
                return [
                    'codigo' => $w->codigo,
                    'tipo' => $w->tipo,
                    'estado' => $w->estado,
                    'capacidad_unidades' => $w->capacidad_unidades,
                    'ocupacion' => $w->ocupacion,
                    'total_productos' => $w->inventarios->count(),
                    'total_unidades' => $w->inventarios->sum('cantidad'),
                ];
            });
        
        return view('warehouses.reporte-ocupacion', compact('warehouses'));
    }

    /**
     * Vista para cargar ubicaciones por rango
     */
    public function cargarPorRango()
    {
        return view('warehouses.cargar-por-rango');
    }

    /**
     * Generar ubicaciones por rango
     */
    public function generarPorRango(Request $request)
    {
        $request->validate([
            'pasillo' => 'required|string|max:1|regex:/^[A-Z]$/',
            'cara_desde' => 'required|integer|min:1',
            'cara_hasta' => 'required|integer|min:1|gte:cara_desde',
            'rack_desde' => 'required|integer|min:1',
            'rack_hasta' => 'required|integer|min:1|gte:rack_desde',
            'nivel_desde' => 'required|integer|min:1',
            'nivel_hasta' => 'required|integer|min:1|gte:nivel_desde',
            'tipo' => 'nullable|in:almacenamiento,picking,recepcion,despacho',
            'estado' => 'nullable|in:activa,inactiva,mantenimiento,bloqueada',
            'zona' => 'nullable|string|max:50',
            'capacidad_unidades' => 'nullable|integer|min:0',
        ]);

        try {
            DB::beginTransaction();

            $pasillo = strtoupper($request->pasillo);
            $caras = range($request->cara_desde, $request->cara_hasta);
            $racks = range($request->rack_desde, $request->rack_hasta);
            $niveles = range($request->nivel_desde, $request->nivel_hasta);

            $tipo = $request->tipo ?? 'almacenamiento';
            $estado = $request->estado ?? 'activa';
            $zona = $request->zona;
            $capacidad_unidades = $request->capacidad_unidades;

            $creados = 0;
            $actualizados = 0;
            $errores = [];

            foreach ($caras as $cara) {
                foreach ($racks as $rack) {
                    foreach ($niveles as $nivel) {
                        try {
                            $codigo = $pasillo . $cara . '-' . str_pad($rack, 2, '0', STR_PAD_LEFT) . '-' . $nivel;

                            $warehouse = Warehouse::updateOrCreate(
                                [
                                    'pasillo' => $pasillo,
                                    'cara' => (string)$cara,
                                    'rack' => $rack,
                                    'nivel' => $nivel,
                                ],
                                [
                                    'codigo' => $codigo,
                                    'nombre' => $codigo,
                                    'descripcion' => null,
                                    'tipo' => $tipo,
                                    'estado' => $estado,
                                    'zona' => $zona,
                                    'capacidad_unidades' => $capacidad_unidades,
                                ]
                            );

                            if ($warehouse->wasRecentlyCreated) {
                                $creados++;
                            } else {
                                $actualizados++;
                            }
                        } catch (\Exception $e) {
                            $errores[] = "Error en {$codigo}: " . $e->getMessage();
                        }
                    }
                }
            }

            DB::commit();

            $total = $creados + $actualizados;
            $mensaje = "Proceso completado. Creadas: {$creados}, Actualizadas: {$actualizados}, Total: {$total}";

            return Response::json([
                'estado' => true,
                'msj' => $mensaje,
                'creados' => $creados,
                'actualizados' => $actualizados,
                'total' => $total,
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
}
