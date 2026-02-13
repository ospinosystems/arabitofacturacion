<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/reset-printing-state/{id}', [App\Http\Controllers\tickera::class, 'resetPrintingState']);

// Ruta para obtener cajas/impresoras disponibles
Route::get('/get-cajas-disponibles', [App\Http\Controllers\tickera::class, 'getCajasDisponibles']);

// Ruta para imprimir tickets de garantía
Route::post('/imprimir-ticket-garantia', [App\Http\Controllers\tickera::class, 'imprimirTicketGarantia']);

/*
|--------------------------------------------------------------------------
| Sistema de Garantías y Devoluciones - API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('garantias')->middleware(['auth.user:api'])->group(function () {
    
    // ================ RUTAS DE CONECTIVIDAD Y SINCRONIZACIÓN ================
    
    // Verificar conectividad con arabitocentral
    Route::get('connection-status', function (Request $request) {
        $sendCentral = new App\Http\Controllers\sendCentral();
        $connected = $sendCentral->checkCentralConnection();
        
        return response()->json([
            'connected' => $connected,
            'central_url' => $sendCentral->path(),
            'timestamp' => now()
        ]);
    });

    // Obtener estadísticas de garantías
    Route::get('stats', function (Request $request) {
        $service = new App\Services\GarantiaCentralService();
        $stats = $service->obtenerEstadisticas();
        
        return response()->json($stats);
    });

    // Sincronizar solicitudes desde central
    Route::get('sync', function (Request $request) {
        try {
            $sendCentral = new App\Http\Controllers\sendCentral();
            
            $params = [
                'codigo_origen' => $sendCentral->getOrigen(),
                'fecha_inicio' => $request->input('fecha_inicio'),
                'fecha_fin' => $request->input('fecha_fin'),
                'estatus' => $request->input('estatus'),
                'tipo_solicitud' => $request->input('tipo_solicitud'),
                'page' => $request->input('page', 1),
                'per_page' => $request->input('per_page', 50)
            ];
            
            $response = Http::timeout(30)->get($sendCentral->path() . "/api/garantias/solicitudes", $params);
            
            if ($response->successful()) {
                $data = $response->json();
                
                return response()->json([
                    'success' => true,
                    'garantias' => $data['solicitudes'] ?? [],
                    'total' => $data['total'] ?? 0,
                    'current_page' => $data['current_page'] ?? 1,
                    'last_page' => $data['last_page'] ?? 1,
                    'per_page' => $data['per_page'] ?? 50,
                    'sync_timestamp' => now()
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al comunicarse con central: ' . $response->body(),
                    'garantias' => [],
                    'sync_timestamp' => now()
                ], 500);
            }
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de conexión con central: ' . $e->getMessage(),
                'garantias' => [],
                'sync_timestamp' => now()
            ], 500);
        }
    });

    // ================ GESTIÓN DE SOLICITUDES ================
    
    // Registrar nueva solicitud de garantía (unificado)
    Route::post('registrar-solicitud', [App\Http\Controllers\GarantiaController::class, 'registrarSolicitudGarantia']);
    
    // Ejecutar solicitud aprobada (NUEVO - usa SolicitudGarantia)
    Route::post('ejecutar-solicitud-moderna', [App\Http\Controllers\GarantiaController::class, 'ejecutarSolicitudGarantiaModerna']);
    
    // Ejecutar solicitud aprobada (LEGACY - mantener para compatibilidad)
    Route::post('ejecutar-solicitud', [App\Http\Controllers\GarantiaController::class, 'ejecutarSolicitudAprobada']);
    
    // Reversar garantía
    Route::post('reversar/{id}', [App\Http\Controllers\GarantiaController::class, 'reversarGarantia']);
    
    // Obtener solicitudes pendientes
    Route::get('solicitudes-pendientes', [App\Http\Controllers\GarantiaController::class, 'getSolicitudesPendientes']);
    
    // Obtener historial de solicitudes
    Route::get('historial', [App\Http\Controllers\GarantiaController::class, 'getHistorialSolicitudes']);
    
    // Obtener tasas de cambio
    Route::get('tasas-cambio', [App\Http\Controllers\GarantiaController::class, 'getTasasCambio']);

    // ================ RUTAS DE COMUNICACIÓN CON CENTRAL ================
    
    // Enviar solicitud a central
    Route::post('send-central', function (Request $request) {
        $service = new App\Services\GarantiaCentralService();
        $result = $service->enviarSolicitudGarantia($request->all());
        
        return response()->json($result);
    });
    
    // Obtener solicitud específica desde central
    Route::get('central/{id}', function (Request $request, $id) {
        $service = new App\Services\GarantiaCentralService();
        $result = $service->consultarEstadoSolicitud($id);
        
        return response()->json($result);
    });
    
    // Finalizar solicitud en central
    Route::post('central/{id}/finalizar', function (Request $request, $id) {
        $service = new App\Services\GarantiaCentralService();
        $detalles = $request->input('detalles_ejecucion', null);
        $result = $service->finalizarSolicitud($id, $detalles);
        
        return response()->json($result);
    });
    
    // Ejecutar garantía localmente usando el modelo SolicitudGarantia correcto
    Route::post('{id}/ejecutar', function (Request $request, $id) {
        // Llamar al nuevo método que usa SolicitudGarantia
        $request->merge(['solicitud_id' => $id]);
        
        $controller = new App\Http\Controllers\GarantiaController();
        return $controller->ejecutarSolicitudGarantiaModerna($request);
    });
    
    // Mantener el método legacy para compatibilidad (DEPRECATED)
    Route::post('{id}/ejecutar-legacy', function (Request $request, $id) {
        \Log::warning('Usando método legacy de ejecución de garantías', ['garantia_id' => $id]);
        
        $garantia = App\Models\garantia::find($id);
        
        if (!$garantia) {
            return response()->json([
                'success' => false,
                'message' => 'Garantía no encontrada'
            ], 404);
        }
        
        $service = new App\Services\GarantiaCentralService();
        $result = $service->ejecutarGarantia($garantia);
        
        return response()->json($result);
    });

    // ================ RUTAS DE VALIDACIÓN Y TRANSFERENCIAS ================
    
    // Validar transferencia con central
    Route::post('transferencias/validar', [App\Http\Controllers\GarantiaController::class, 'validarTransferenciaConCentral']);
    
    // Crear transferencia para aprobación
    Route::post('transferencias/aprobacion', [App\Http\Controllers\GarantiaController::class, 'crearTransferenciaAprobacion']);
    
    // Reversar en central
    Route::post('reversar-central', [App\Http\Controllers\GarantiaController::class, 'reversarGarantiaCentral']);

    // ================ RUTAS DE INVENTARIO DE GARANTÍAS ================
    
    // Obtener inventarios de garantía filtrados por sucursal
    Route::get('inventarios', function (Request $request) {
        $sendCentral = new App\Http\Controllers\sendCentral();
        $codigoOrigen = $sendCentral->getOrigen();
        
        $params = [
            'sucursal_codigo' => $codigoOrigen,
            'producto_nombre' => $request->input('producto_nombre'),
            'proveedor_id' => $request->input('proveedor_id'),
            'tipo_inventario' => $request->input('tipo_inventario'),
            'limit' => $request->input('limit', 50)
        ];
        
        try {
            $response = Http::timeout(30)->get($sendCentral->path() . "/api/dashboard/garantias/inventarios", $params);
            
            if ($response->successful()) {
                return response()->json($response->json());
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al obtener inventarios: ' . $response->body(),
                    'data' => ['data' => []],
                    'estadisticas' => [],
                    'resumen_proveedores' => []
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de conexión: ' . $e->getMessage(),
                'data' => ['data' => []],
                'estadisticas' => [],
                'resumen_proveedores' => []
            ], 500);
        }
    });
    
    // Obtener sucursales disponibles
    Route::get('sucursales', function (Request $request) {
        $sendCentral = new App\Http\Controllers\sendCentral();
        
        try {
            $response = Http::timeout(30)->get($sendCentral->path() . "/api/dashboard/garantias/sucursales");
            
            if ($response->successful()) {
                return response()->json($response->json());
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al obtener sucursales',
                    'data' => []
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de conexión: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    });
    
    // Obtener proveedores disponibles
    Route::get('proveedores', function (Request $request) {
        $sendCentral = new App\Http\Controllers\sendCentral();
        
        try {
            $response = Http::timeout(30)->get($sendCentral->path() . "/api/dashboard/garantias/proveedores");
            
            if ($response->successful()) {
                return response()->json($response->json());
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al obtener proveedores',
                    'data' => []
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de conexión: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    });
    

    
    // Obtener movimientos de un producto
    Route::get('movimientos', function (Request $request) {
        $sendCentral = new App\Http\Controllers\sendCentral();
        $codigoOrigen = $sendCentral->getOrigen();
        $params = [
            'sucursal_codigo' => $codigoOrigen,
            'producto_id' => $request->input('producto_id'),
            'fecha_inicio' => $request->input('fecha_inicio'),
            'fecha_fin' => $request->input('fecha_fin'),
            'tipo_movimiento' => $request->input('tipo_movimiento'),
            'tipo_inventario' => $request->input('tipo_inventario'),
            'limit' => $request->input('limit', 50),
            'page' => $request->input('page', 1)
        ];
        
        try {
            $response = Http::timeout(30)->get($sendCentral->path() . "/api/dashboard/garantias/movimientos", $params);
            
            if ($response->successful()) {
                return response()->json($response->json());
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al obtener movimientos: ' . $response->body(),
                    'data' => []
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de conexión: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    });

});

// ================ RUTAS DE INVENTARIO CÍCLICO ================
Route::prefix('inventario-ciclico')->middleware(['auth.user:api'])->group(function () {
    
    // Listar planillas
    Route::get('planillas', function (Request $request) {
        $sendCentral = new App\Http\Controllers\sendCentral();
        $codigoOrigen = $sendCentral->getOrigen();
        
        $params = array_merge($request->all(), ['sucursal_codigo' => $codigoOrigen]);
        
        try {
            $response = Http::timeout(30)->get($sendCentral->path() . "/api/inventario-ciclico/planillas", $params);
            return $response->body();
            if ($response->successful()) {
                return response()->json($response->json());
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al obtener planillas: ' . $response->body(),
                    'data' => []
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de conexión: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    });
    
    // Crear nueva planilla
    Route::post('planillas', function (Request $request) {
        $sendCentral = new App\Http\Controllers\sendCentral();
        $codigoOrigen = $sendCentral->getOrigen();
        
        $data = array_merge($request->all(), ['sucursal_codigo' => $codigoOrigen]);
        
        try {
            $response = Http::timeout(30)->post($sendCentral->path() . "/api/inventario-ciclico/planillas", $data);
            
            if ($response->successful()) {
                return response()->json($response->json());
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al crear planilla: ' . $response->body()
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de conexión: ' . $e->getMessage()
            ], 500);
        }
    });
    
    // Mostrar planilla específica
    Route::get('planillas/{id}', function (Request $request, $id) {
        $sendCentral = new App\Http\Controllers\sendCentral();
        
        try {
            $response = Http::timeout(30)->get($sendCentral->path() . "/api/inventario-ciclico/planillas/{$id}");
            
            if ($response->successful()) {
                return response()->json($response->json());
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al obtener planilla: ' . $response->body()
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de conexión: ' . $e->getMessage()
            ], 500);
        }
    });
    
    // Agregar producto a planilla (solo local: ya no envía a central; usar enviar-tareas para enviar en lote)
    Route::post('planillas/{id}/productos', function (Request $request, $id) {
        return response()->json([
            'success' => true,
            'message' => 'Use la interfaz para agregar productos (se guardan en local). Luego use "Enviar a Central" para enviar todas las tareas.',
            'data' => null
        ], 200);
    });

    // Enviar todas las tareas pendientes de la planilla a Central en un solo request (una llamada por tarea a central)
    Route::post('planillas/{id}/enviar-tareas', function (Request $request, $id) {
        try {
            $validator = Validator::make($request->all(), [
                'tareas' => 'required|array',
                'tareas.*.id_producto' => 'required|integer',
                'tareas.*.cantidad_fisica' => 'required|numeric|min:0',
                'tareas.*.observaciones' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $sendCentral = new App\Http\Controllers\sendCentral();
            $usuarioResponsable = session("usuario");
            $responsable = $usuarioResponsable ?: 'Usuario Sistema';
            $enviados = 0;
            $errores = [];

            foreach ($request->tareas as $idx => $tarea) {
                $producto = App\Models\inventario::find($tarea['id_producto']);
                if (!$producto) {
                    $errores[] = "Producto ID {$tarea['id_producto']} no encontrado.";
                    continue;
                }

                $cantidadSistema = $producto->cantidad ?? 0;
                $cantidadFisica = (int) $tarea['cantidad_fisica'];
                $diferenciaCantidad = $cantidadFisica - $cantidadSistema;

                $datosAntes = [
                    'id' => $producto->id,
                    'idinsucursal' => $producto->id,
                    'codigo_barras' => $producto->codigo_barras ?? '',
                    'codigo_proveedor' => $producto->codigo_proveedor ?? '',
                    'cantidad' => $cantidadSistema,
                    'precio' => $producto->precio ?? 0,
                    'precio_base' => $producto->precio_base ?? 0,
                    'descripcion' => $producto->descripcion ?? '',
                    'unidad' => $producto->unidad ?? '',
                    'id_categoria' => $producto->id_categoria ?? null,
                    'id_proveedor' => $producto->id_proveedor ?? null,
                    'id_marca' => $producto->id_marca ?? null,
                    'iva' => $producto->iva ?? 0,
                    'stockmin' => $producto->stockmin ?? 0,
                    'stockmax' => $producto->stockmax ?? 0,
                ];

                $datosDespues = [
                    'id' => $producto->id,
                    'idinsucursal' => $producto->id,
                    'cantidad' => $cantidadFisica,
                    'precio' => $producto->precio ?? 0,
                    'precio_base' => $producto->precio_base ?? 0,
                    'unidad' => $producto->unidad ?? '',
                    'id_categoria' => $producto->id_categoria ?? null,
                    'id_proveedor' => $producto->id_proveedor ?? null,
                    'id_marca' => $producto->id_marca ?? null,
                    'iva' => $producto->iva ?? 0,
                    'stockmin' => $producto->stockmin ?? 0,
                    'stockmax' => $producto->stockmax ?? 0,
                ];

                $arrproducto = [
                    'tipo_tarea' => 'inventario_ciclico',
                    'id_planilla' => (int) $id,
                    'id_producto' => $producto->id,
                    'descripcion' => "Ajuste de inventario por planilla #{$id}. Diferencia: {$diferenciaCantidad} unidades",
                    'cantidad_solicitada' => $cantidadFisica,
                    'cantidad_actual' => $cantidadSistema,
                    'usuario_aprobador' => null,
                    'observaciones' => $tarea['observaciones'] ?? null,
                    'antes' => $datosAntes,
                    'novedad' => $datosDespues,
                    'responsable' => $responsable,
                ];

                $resultado = $sendCentral->sendNovedadCentral($arrproducto);
                if (isset($resultado['estado']) && $resultado['estado']) {
                    $enviados++;
                } else {
                    $errores[] = $producto->descripcion . ': ' . ($resultado['msj'] ?? 'Error desconocido');
                }
            }

            $message = $enviados === count($request->tareas)
                ? "Se enviaron {$enviados} tarea(s) a Central correctamente."
                : "Se enviaron {$enviados} de " . count($request->tareas) . ". " . (count($errores) ? implode(' ', $errores) : '');

            return response()->json([
                'success' => $enviados > 0,
                'message' => $message,
                'enviados' => $enviados,
                'total' => count($request->tareas),
                'errores' => $errores,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar tareas: ' . $e->getMessage()
            ], 500);
        }
    });
    
    // Actualizar cantidad de producto
    Route::put('planillas/{planillaId}/productos/{detalleId}', function (Request $request, $planillaId, $detalleId) {
        $sendCentral = new App\Http\Controllers\sendCentral();
        
        try {
            $response = Http::timeout(30)->put($sendCentral->path() . "/api/inventario-ciclico/planillas/{$planillaId}/productos/{$detalleId}", $request->all());
            
            if ($response->successful()) {
                return response()->json($response->json());
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al actualizar cantidad: ' . $response->body()
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de conexión: ' . $e->getMessage()
            ], 500);
        }
    });
    
    // Cerrar planilla
    Route::post('planillas/{id}/cerrar', function (Request $request, $id) {
        $sendCentral = new App\Http\Controllers\sendCentral();
        
        try {
            $response = Http::timeout(30)->post($sendCentral->path() . "/api/inventario-ciclico/planillas/{$id}/cerrar", $request->all());
            
            if ($response->successful()) {
                return response()->json($response->json());
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al cerrar planilla: ' . $response->body()
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de conexión: ' . $e->getMessage()
            ], 500);
        }
    });
    
    // Buscar productos en inventario local
    Route::get('productos/buscar', function (Request $request) {
        try {
            $query = App\Models\inventario::query();
            
            // Parámetros de búsqueda
            $search = $request->input('termino', '');
            $limit = $request->input('limit', 50);
            $page = $request->input('page', 1);
            
            // Aplicar filtros de búsqueda
            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('descripcion', 'LIKE', "%{$search}%")
                      ->orWhere('codigo_barras', 'LIKE', "%{$search}%")
                      ->orWhere('codigo_proveedor', 'LIKE', "%{$search}%");
                });
            }
            
            // Filtrar solo productos activos
            // Ordenar por descripción
            $query->orderBy('descripcion', 'asc');
            
            // Paginar resultados
            $productos = $query->paginate($limit, ['*'], 'page', $page);
            
            // Formatear respuesta
            $data = $productos->map(function($producto) {
                return [
                    'id' => $producto->id,
                    'descripcion' => $producto->descripcion,
                    'codigo_barras' => $producto->codigo_barras,
                    'codigo_proveedor' => $producto->codigo_proveedor,
                    'precio' => $producto->precio,
                    'precio_base' => $producto->precio_base,
                    'cantidad' => $producto->cantidad,
                    'unidad' => $producto->unidad,
                    'id_categoria' => $producto->id_categoria,
                    'id_proveedor' => $producto->id_proveedor,
                    'id_marca' => $producto->id_marca,
                    'iva' => $producto->iva,
                    'stockmin' => $producto->stockmin,
                    'stockmax' => $producto->stockmax,
                ];
            });
            
            return response()->json([
                'success' => true,
                'message' => 'Productos encontrados',
                'data' => $data,
                'pagination' => [
                    'current_page' => $productos->currentPage(),
                    'last_page' => $productos->lastPage(),
                    'per_page' => $productos->perPage(),
                    'total' => $productos->total(),
                    'from' => $productos->firstItem(),
                    'to' => $productos->lastItem(),
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al buscar productos: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    });
    
    // Refrescar estado de tareas
    Route::post('planillas/{id}/refrescar-tareas', function (Request $request, $id) {
        $sendCentral = new App\Http\Controllers\sendCentral();
        
        try {
            $response = Http::timeout(30)->post($sendCentral->path() . "/api/inventario-ciclico/planillas/{$id}/refrescar-tareas");
            
            if ($response->successful()) {
                return response()->json($response->json());
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al refrescar tareas: ' . $response->body()
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de conexión: ' . $e->getMessage()
            ], 500);
        }
    });
    
    // Redirigir a la vista Blade del reporte (no generar PDF por API)
    Route::get('planillas/{id}/reporte-pdf', function (Request $request, $id) {
        return redirect()->route('inventario-ciclico.reporte', ['id' => $id]);
    });
    
    // Generar reporte Excel
    Route::get('planillas/{id}/reporte-excel', function (Request $request, $id) {
        $sendCentral = new App\Http\Controllers\sendCentral();
        
        try {
            $response = Http::timeout(60)->get($sendCentral->path() . "/api/inventario-ciclico/planillas/{$id}/reporte-excel");
            
            if ($response->successful()) {
                return response($response->body())
                    ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                    ->header('Content-Disposition', 'attachment; filename="Planilla_Inventario_' . $id . '_' . date('Y-m-d') . '.xlsx"');
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al generar reporte Excel: ' . $response->body()
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de conexión: ' . $e->getMessage()
            ], 500);
        }
    });

});

// ================ RUTAS DE SINCRONIZACIÓN CON CENTRAL ================

// Recibir actualización de inventario desde central (para tareas de inventario cíclico)
Route::post('actualizarInventarioDesdeCentral', function (Request $request) {
    try {
        $codigoSucursal = $request->input('codigo_sucursal');
        $datosProducto = $request->input('datos_producto');
        
        // Verificar que la sucursal coincida con la actual
        $sendCentral = new App\Http\Controllers\sendCentral();
        $sucursalActual = $sendCentral->getOrigen();
        
        if ($codigoSucursal !== $sucursalActual) {
            return response()->json([
                'success' => false,
                'message' => 'Código de sucursal no coincide'
            ], 400);
        }
        
        // Actualizar el inventario local
        $inventario = App\Models\inventario::find($datosProducto['id_producto']);
        
        if (!$inventario) {
            return response()->json([
                'success' => false,
                'message' => 'Producto no encontrado en inventario local'
            ], 404);
        }
        
        // Actualizar datos del producto
        $inventario->update([
            'cantidad' => $datosProducto['cantidad'],
            'precio' => $datosProducto['precio'],
            'precio_base' => $datosProducto['precio_base'],
            'descripcion' => $datosProducto['descripcion'],
            'codigo_barras' => $datosProducto['codigo_barras'],
            'codigo_proveedor' => $datosProducto['codigo_proveedor']
        ]);
        
        // Registrar el movimiento de inventario
        $movimiento = new App\Models\movimientosinventario();
        $movimiento->id_producto = $datosProducto['id_producto'];
        $movimiento->cantidad = $datosProducto['cantidad'];
        $movimiento->tipo = 'inventario_ciclico';
        $movimiento->descripcion = 'Ajuste por inventario cíclico aprobado desde central';
        $movimiento->usuario = $datosProducto['usuario_aprobador'] ?? 'Sistema Central';
        $movimiento->fecha = now();
        $movimiento->save();
        
        \Log::info("Inventario actualizado desde central", [
            'producto_id' => $datosProducto['id_producto'],
            'cantidad_nueva' => $datosProducto['cantidad'],
            'usuario_aprobador' => $datosProducto['usuario_aprobador']
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Inventario actualizado exitosamente',
            'producto_id' => $datosProducto['id_producto'],
            'cantidad_actualizada' => $datosProducto['cantidad']
        ]);
        
    } catch (\Exception $e) {
        \Log::error("Error al actualizar inventario desde central: " . $e->getMessage());
        
        return response()->json([
            'success' => false,
            'message' => 'Error al actualizar inventario: ' . $e->getMessage()
        ], 500);
    }
})->middleware(['auth.user:api']);
