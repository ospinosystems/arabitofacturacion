<?php

namespace App\Http\Controllers;

set_time_limit(600000);
ini_set('memory_limit', '4095M');
use App\Models\cajas;
use App\Models\catcajas;
use App\Models\inventarios_novedades;
use App\Models\pagos_referencias;
use App\Models\usuarios;
use Illuminate\Http\Request;
use App\Models\pago_pedidos;
use App\Models\tareaslocal;
use App\Models\movimientosinventariounitario;
use App\Models\movimientosinventario;
use App\Models\devoluciones;
use App\Models\movimientos;
use App\Models\sucursal;
use App\Models\moneda;
use App\Models\factura;
use App\Models\items_factura;


use App\Models\categorias;
use App\Models\proveedores;
use App\Models\pedidos;
use App\Models\items_pedidos;
use App\Models\tareas;

use App\Models\cierres;
use App\Models\inventario;

use App\Models\clientes;
use App\Models\fallas;
use App\Models\garantia;
use App\Models\vinculosucursales;
use App\Models\transferencias_inventario_items;
use App\Models\transferencias_inventario;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;

use App\Http\Controllers\SyncProgressController;
use App\Mail\enviarCierre;
use App\Jobs\PostSyncJob;

use Http;
use Response;
use DB;
use Schema;
use Hash;


class sendCentral extends Controller
{

    public function path()
    {
       //return "http://127.0.0.1:8001";
       return "https://phplaravel-1009655-3565285.cloudwaysapps.com";
    }

    public function sends()
    {
        return [
            /**/ "admon.arabito@gmail.com",   
            "omarelhenaoui@hotmail.com",           
            "yeisersalah2@gmail.com",           
            "amerelhenaoui@outlook.com",           
            "yesers982@hotmail.com", 
            "alvaroospino79@gmail.com"  
        ];
    }

    /**\
     * Buscar una solicitud de garantía específica en arabitocentral
     */
    public function buscarSolicitudGarantiaCentral($id)
    {
        try {
            $codigoOrigen = $this->getOrigen();
            $response = Http::timeout(30)->get($this->path() . "/api/garantias/solicitudes/{$id}", [
                "codigo_origen" => $codigoOrigen
            ]);

            if ($response->ok()) {
                $data = $response->json();
                if ($data['success']) {
                    return [
                        'success' => true,
                        'data' => $data['data']
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => $data['message'] ?? 'Error al obtener la solicitud'
                    ];
                }
            } else {
                \Log::error('Error al buscar solicitud de garantía en central', [
                    'id' => $id,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Error de comunicación con arabitocentral: ' . $response->status()
                ];
            }
        } catch (\Exception $e) {
            
            return [
                'success' => false,
                'message' => 'Error de conexión: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Enviar solicitud de reverso de garantía a central
     */
    public function enviarSolicitudReverso($data)
    {
        try {
            $response = Http::post($this->path() . "/api/solicitudes-reverso", $data);
            
            if ($response->ok()) {
                $result = $response->json();
                if ($result['success']) {
                    return [
                        'estado' => true,
                        'id' => $result['solicitud_id'] ?? null,
                        'msj' => $result['message'] ?? 'Solicitud enviada exitosamente'
                    ];
                } else {
                    return [
                        'estado' => false,
                        'msj' => $result['message'] ?? 'Error al enviar solicitud'
                    ];
                }
            } else {
                return [
                    'estado' => false,
                    'msj' => 'Error de conexión con central: ' . $response->status()
                ];
            }
        } catch (\Exception $e) {
            \Log::error('Error enviando solicitud de reverso a central', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            
            return [
                'estado' => false,
                'msj' => 'Error de conexión: ' . $e->getMessage()
            ];
                }
    }

  

    public function getAllInventarioFromCentral() {
        try {
            // Verificar si hay pedidos pendientes
            /* $pedidosPendientes = pedidos::where("estado", 0)->pluck('id');
            if ($pedidosPendientes->isNotEmpty()) {
                return Response::json([
                    "estado" => false,
                    "msj" => "No se puede sincronizar. Debe completar o eliminar los pedidos pendientes: " . $pedidosPendientes->implode(', ')
                ]);
            } */
            
            // Check if there are tasks before proceeding
            $checkTasks = Http::get($this->path() . "/thereAreTasks", [
                "codigo_origen" => $this->getOrigen()
            ]);

            if (!$checkTasks->ok()) {
                \Log::info('Error al verificar tareas pendientes');
                return 'Error al verificar tareas pendientes';
            }

            $response = $checkTasks->json();
            if ($response === null) {
                \Log::info('No hay tareas pendientes para procesar');
                return 'No hay permisos para procesar tareas';
            }

            if ($response === true) {
                \Log::info('Hay tareas pendientes, continuando con el proceso');
            } 

            \Log::info('Iniciando proceso de sincronización de inventario');
            
            $codigo_origen = $this->getOrigen();
            // $reportController = new ReportController(); // Ya no se usa, se utiliza $this->generateReport()

            $idsSuccess = [];
            
            \Log::info('Enviando petición a central con código origen: ' . $codigo_origen);
            
            // Primera petición: Obtener datos
            $response = Http::get($this->path() . "/getTareasEliminacionInventarioFromCentral", [
                "codigo_origen" => $codigo_origen
            ]);

            if ($response->ok()) {
                $res = $response->json();
                if ($res["estado"]) {
                    $stats = [
                        'tasks_success' => 0,
                        'tasks_error' => 0,
                        'inventory_updated' => 0,
                        'inventory_unchanged' => 0,
                        'tasks_details' => [
                            'successful' => [],
                            'failed' => []
                        ]
                    ];

                    \Log::info('Respuesta recibida de central. Iniciando procesamiento de tareas');
                    
                    DB::beginTransaction();

                    // Process tasks
                    if (isset($res["tareas"]) && is_array($res["tareas"])) {
                        \Log::info('Procesando ' . count($res["tareas"]) . ' tareas');
                        
                        $taskChanges = [];
                        $chunks = array_chunk($res["tareas"], 20);
                        
                        foreach ($chunks as $chunkIndex => $chunk) {
                            \Log::info('Procesando chunk ' . ($chunkIndex + 1) . ' de ' . count($chunks));
                            
                            DB::beginTransaction();
                            
                            foreach ($chunk as $task) {
                                try {
                                    $taskResult = [
                                        'id' => $task["id"],
                                        'id_producto_rojo' => $task["id_producto_rojo"],
                                        'id_producto_verde' => $task["id_producto_verde"],
                                        'error' => null
                                    ];

                                    // Track task changes
                                    $taskChange = [
                                        'id' => $task["id"],
                                        'original_product' => $task["id_producto_rojo"],
                                        'replacement_product' => $task["id_producto_verde"],
                                        'status' => 'Pendiente',
                                        'details' => 'Iniciando procesamiento'
                                    ];

                                    // Verificar si el producto verde ya existe o es igual al rojo
                                    if ($task["id_producto_rojo"] == $task["id_producto_verde"]) {
                                        $taskResult['error'] = 'ID producto verde igual al rojo';
                                        $stats['tasks_error']++;
                                        $stats['tasks_details']['failed'][] = $taskResult;
                                        $taskChange['status'] = 'Error';
                                        $taskChange['details'] = 'ID producto verde igual al rojo';
                                        $taskChanges[] = $taskChange;
                                        \Log::warning('Tarea fallida: ID producto verde igual al rojo', $taskResult);
                                        continue;
                                    }

                                    // Verificar si el producto verde existe
                                    $producto_verde_existente = inventario::find($task["id_producto_verde"]);
                                    $producto_rojo = inventario::find($task["id_producto_rojo"]);
                                    
                                    if($producto_verde_existente && !$producto_rojo){
                                        $stats['tasks_success']++;
                                        $taskResult['updates'] = [];
                                        $stats['tasks_details']['successful'][] = $taskResult;
                                        \Log::info('Tarea exitosa procesada. Rojo no existe, verde si existe', $taskResult);

                                        // Update task change status
                                        $taskChange['status'] = 'Exitoso';
                                        $taskChange['details'] = 'Tarea procesada correctamente';
                                        $taskChanges[] = $taskChange;
                                        continue;
                                    }
                                    if(!$producto_rojo){
                                        $taskResult['error'] = 'ID producto rojo no encontrado '.$task["id_producto_rojo"];
                                        $stats['tasks_error']++;
                                        $stats['tasks_details']['failed'][] = $taskResult;
                                        $taskChange['status'] = 'Error';
                                        $taskChange['details'] = 'ID producto rojo no encontrado';
                                        $taskChanges[] = $taskChange;
                                        \Log::warning('Tarea fallida: ID producto rojo no encontrado '.$task["id_producto_rojo"], $taskResult);
                                        continue;
                                    }
                                    
                                    // Actualizar todas las tablas relacionadas
                                    if($task["verde_sucursal"]==$codigo_origen){
                                        $updates = [
                                            'items_pedidos' => DB::table('items_pedidos')->where("id_producto", $task["verde_idinsucursal"])->update(["id_producto" => $task["id_producto_verde"]]),
                                            'garantias' => DB::table('garantias')->where("id_producto", $task["verde_idinsucursal"])->update(["id_producto" => $task["id_producto_verde"]]),
                                            'fallas' => DB::table('fallas')->where("id_producto", $task["verde_idinsucursal"])->update(["id_producto" => $task["id_producto_verde"]]),
                                            'movimientos_inventario' => DB::table('movimientos_inventarios')->where("id_producto", $task["verde_idinsucursal"])->update(["id_producto" => $task["id_producto_verde"]]),
                                            'movimientos_inventario_unitario' => DB::table('movimientos_inventariounitarios')->where("id_producto", $task["verde_idinsucursal"])->update(["id_producto" => $task["id_producto_verde"]]),
                                            'vinculo_sucursales' => DB::table('vinculosucursales')->where("id_producto", $task["verde_idinsucursal"])->update(["id_producto" => $task["id_producto_verde"]]),
                                            'inventarios_novedades' => DB::table('inventarios_novedades')->where("id_producto", $task["verde_idinsucursal"])->update(["id_producto" => $task["id_producto_verde"]]),
                                            'items_factura' => function() use ($task) {
                                                // Primero identificamos los registros que causarían duplicados
                                                $duplicates = DB::table('items_facturas as if1')
                                                    ->join('items_facturas as if2', function($join) use ($task) {
                                                        $join->on('if1.id_factura', '=', 'if2.id_factura')
                                                            ->where('if1.id_producto', '=', $task["verde_idinsucursal"])
                                                            ->where('if2.id_producto', '=', $task["id_producto_verde"]);
                                                    })
                                                    ->select('if1.id')
                                                    ->get();
                                                
                                                // Eliminamos los registros que causarían duplicados
                                                if ($duplicates->count() > 0) {
                                                    DB::table('items_facturas')
                                                        ->whereIn('id', $duplicates->pluck('id'))
                                                        ->delete();
                                                }
                                                
                                                // Ahora podemos actualizar sin problemas de duplicados
                                                return DB::table('items_facturas')
                                                    ->where("id_producto", $task["verde_idinsucursal"])
                                                    ->update(["id_producto" => $task["id_producto_verde"]]);
                                            },
                                            'transferencias_inventario_items' => DB::table('transferencias_inventario_items')->where("id_producto", $task["verde_idinsucursal"])->update(["id_producto" => $task["id_producto_verde"]])
                                        ];
                                    }
                                    $updates = [
                                       'items_pedidos' => DB::table('items_pedidos')->where("id_producto", $task["id_producto_rojo"])->update(["id_producto" => $task["id_producto_verde"]]),
                                       'garantias' => DB::table('garantias')->where("id_producto", $task["id_producto_rojo"])->update(["id_producto" => $task["id_producto_verde"]]),
                                       'fallas' => DB::table('fallas')->where("id_producto", $task["id_producto_rojo"])->update(["id_producto" => $task["id_producto_verde"]]),
                                       'movimientos_inventario' => DB::table('movimientos_inventarios')->where("id_producto", $task["id_producto_rojo"])->update(["id_producto" => $task["id_producto_verde"]]),
                                       'movimientos_inventario_unitario' => DB::table('movimientos_inventariounitarios')->where("id_producto", $task["id_producto_rojo"])->update(["id_producto" => $task["id_producto_verde"]]),
                                       'vinculo_sucursales' => DB::table('vinculosucursales')->where("id_producto", $task["id_producto_rojo"])->update(["id_producto" => $task["id_producto_verde"]]),
                                       'inventarios_novedades' => DB::table('inventarios_novedades')->where("id_producto", $task["id_producto_rojo"])->update(["id_producto" => $task["id_producto_verde"]]),
                                       'items_factura' => function() use ($task) {
                                           // Primero identificamos los registros que causarían duplicados
                                           $duplicates = DB::table('items_facturas as if1')
                                               ->join('items_facturas as if2', function($join) use ($task) {
                                                   $join->on('if1.id_factura', '=', 'if2.id_factura')
                                                       ->where('if1.id_producto', '=', $task["id_producto_rojo"])
                                                       ->where('if2.id_producto', '=', $task["id_producto_verde"]);
                                               })
                                               ->select('if1.id')
                                               ->get();
                                           
                                           // Eliminamos los registros que causarían duplicados
                                           if ($duplicates->count() > 0) {
                                               DB::table('items_facturas')
                                                   ->whereIn('id', $duplicates->pluck('id'))
                                                   ->delete();
                                           }
                                           
                                           // Ahora podemos actualizar sin problemas de duplicados
                                           return DB::table('items_facturas')
                                               ->where("id_producto", $task["id_producto_rojo"])
                                               ->update(["id_producto" => $task["id_producto_verde"]]);
                                       },
                                       'transferencias_inventario_items' => DB::table('transferencias_inventario_items')->where("id_producto", $task["id_producto_rojo"])->update(["id_producto" => $task["id_producto_verde"]])
                                    
                                    ];

                                    if ($producto_verde_existente) {
                                        // Si el producto verde existe, sumar las cantidades
                                        $cantidad_anterior = $producto_verde_existente->cantidad;
                                        $producto_verde_existente->cantidad += $producto_rojo->cantidad;
                                        $producto_verde_existente->save();
                                        \Log::info('Sumas de CT. CT_ROJO= '.$producto_rojo->cantidad.' id_rojo= '.$producto_rojo->id.' & CT_VERDE= '.$producto_verde_existente->cantidad.' id_verde= '.$producto_verde_existente->id);

                                        // Eliminar el producto rojo después de la fusión
                                        $producto_rojo->delete();
                                        $stats['tasks_success']++;
                                        $idsSuccess[] = $task["id_producto_verde"];
                                        $taskResult['updates'] = $updates;
                                        $stats['tasks_details']['successful'][] = $taskResult;
                                        \Log::info('Tarea exitosa procesada producto_verde_existente==true', $taskResult);
                                        // Generar reporte del movimiento
                                        $this->generateReport('product_movement', [
                                            'id_producto' => $task["id_producto_verde"],
                                            'cantidad_anterior' => $cantidad_anterior,
                                            'cantidad_nueva' => $producto_verde_existente->cantidad,
                                            'tipo' => 'Fusión de productos',
                                            'descripcion' => 'Fusión de producto ' . $task["id_producto_rojo"] . ' en ' . $task["id_producto_verde"]
                                        ]);
                                        continue;
                                    }
                                    
                                    // Registrar el reemplazo de producto
                                    $this->generateReport('product_replacement', [[
                                        'deleted_id' => $task["id_producto_rojo"],
                                        'replacement_id' => $task["id_producto_verde"],
                                        'old_quantity' => 0,
                                        'new_quantity' => 0,
                                        'date' => date('Y-m-d H:i:s')
                                    ]]);

                                    // Actualizar el ID en la tabla inventario
                                    $producto_rojo = inventario::find($task["id_producto_rojo"]);
                                    if ($producto_rojo) {
                                        $producto_rojo->id = $task["id_producto_verde"];
                                        $producto_rojo->save();
                                        $stats['tasks_success']++;
                                        $idsSuccess[] = $task["id_producto_verde"];
                                        $taskResult['updates'] = $updates;
                                        
                                        if($task["verde_sucursal"]==$codigo_origen){
                                            $producto_verde_insucursal = inventario::find($task["verde_idinsucursal"]);
                                            if($producto_verde_insucursal){
                                                $ct_verde = $producto_verde_insucursal->cantidad;
                                                $producto_verde = inventario::find($task["id_producto_verde"]);
                                                $producto_verde->cantidad += $ct_verde;
                                                if($producto_verde->save()){    
                                                    $idsSuccess[] = $task["id_producto_verde"];
                                                    // Generar reporte del movimiento
                                                    $this->generateReport('product_movement', [
                                                        'id_producto' => $task["id_producto_verde"],
                                                        'cantidad_anterior' => $ct_verde,
                                                        'cantidad_nueva' => $producto_verde->cantidad,
                                                        'tipo' => 'Fusión de productos',
                                                        'descripcion' => 'Fusión de producto ' . $task["id_producto_rojo"] . ' en ' . $task["id_producto_verde"]
                                                    ]);

                                                    // Eliminar el producto verde después de actualizar
                                                    $producto_verde_insucursal->delete();
                                                }
                                            }
                                        }
                                        $stats['tasks_details']['successful'][] = $taskResult;
                                        \Log::info('Tarea exitosa procesada', $taskResult);
                                    } else {
                                        $taskResult['error'] = 'Producto rojo no encontrado';
                                        $stats['tasks_error']++;
                                        $stats['tasks_details']['failed'][] = $taskResult;
                                        \Log::warning('Tarea fallida: Producto rojo no encontrado', $taskResult);
                                    }

                                    $stats['tasks_success']++;
                                    $taskResult['updates'] = $updates;
                                    $stats['tasks_details']['successful'][] = $taskResult;
                                    \Log::info('Tarea exitosa procesada', $taskResult);

                                    // Update task change status
                                    $taskChange['status'] = 'Exitoso';
                                    $taskChange['details'] = 'Tarea procesada correctamente';
                                    $taskChanges[] = $taskChange;

                                } catch (\Exception $e) {
                                    $stats['tasks_error']++;
                                    $taskResult['error'] = $e->getMessage();
                                    $stats['tasks_details']['failed'][] = $taskResult;
                                    $taskChange['status'] = 'Error';
                                    $taskChange['details'] = $e->getMessage();
                                    $taskChanges[] = $taskChange;
                                    \Log::error('Error procesando tarea: ' . $e->getMessage(), $taskResult);
                                }
                            }
                            
                            // Commit de la transacción para este chunk
                            DB::commit();
                            \Log::info('Chunk ' . ($chunkIndex + 1) . ' procesado y guardado exitosamente');
                        }

                        // Generate task report
                        $this->generateReport('tasks', $taskChanges);
                    }

                    \Log::info('Procesando inventario de la respuesta');
                    
                    // Procesar el inventario que viene en la respuesta
                    if (isset($res["inventarios"])) {
                        $inventoryChanges = [];
                        // Decodificar los datos del inventario
                        $inventariosDecoded = base64_decode($res["inventarios"]);
                        $inventariosDecompressed = gzdecode($inventariosDecoded);
                        $inventariosArray = json_decode($inventariosDecompressed, true);

                        if (is_array($inventariosArray)) {
                            \Log::info('Procesando ' . count($inventariosArray) . ' productos del inventario');
                            
                            foreach ($inventariosArray as $inv) {
                                $producto = inventario::find($inv["id"]);
                                /* if (!$producto) {
                                    if ($inv["sucursal"]["codigo"] == $codigo_origen) {
                                        $producto = inventario::where('id', $inv["idinsucursal"])->first();
                                    } 
                                } */
                                if ($producto) {
                                    $changed = false;
                                    $fields = [
                                        'codigo_barras', 'codigo_proveedor', 'id_proveedor', 
                                        'id_categoria', 'id_marca', 'unidad', 'descripcion', 
                                        'precio_base', 'precio', 'stockmin', 'stockmax'
                                    ];
                                    
                                    foreach ($fields as $field) {
                                        if (isset($inv[$field]) && $producto->$field != $inv[$field]) {
                                            // Skip updating if conditions are met
                                            if($field=="descripcion" && $inv["uptdescripcion"]==0) continue;

                                            if($field=="precio" && $inv["uptprecios_venta"]==0) continue;
                                            if($field=="precio_base" && $inv["uptprecios_base"]==0) continue;
                                            
                                            if($field=="codigo_barras" && $inv["uptcodigo_barras"]==0) continue;
                                            if($field=="codigo_proveedor" && $inv["uptcodigo_proveedor"]==0) continue;
                                            if (($field == 'precio' || $field == 'precio_base') && (!is_numeric($inv[$field]) || $inv[$field] <= 0)) continue;

                                            // Track inventory changes
                                            $inventoryChanges[] = [
                                                'product_id' => $producto->id,
                                                'descripcion' => $producto->descripcion,
                                                'codigo_barras' => $producto->codigo_barras,
                                                'codigo_proveedor' => $producto->codigo_proveedor,
                                                'field' => $field,
                                                'old_value' => $producto->$field,
                                                'new_value' => $inv[$field],
                                                'date' => date('Y-m-d H:i:s')
                                            ];

                                            $producto->$field = $inv[$field];
                                            $changed = true;
                                            $idsSuccess[] = $producto->id;
                                        }
                                    }
                                    
                                    if ($changed) {
                                        $producto->save();
                                        $stats['inventory_updated']++;
                                    } else {
                                        $stats['inventory_unchanged']++;
                                    }   
                                } else {
                                    if ($inv["uppnew"]==1) {
                                        inventario::create([
                                            "id" => $inv["id"],
                                            'codigo_barras' => $inv["codigo_barras"], 
                                            'codigo_proveedor' => $inv["codigo_proveedor"], 
                                            'id_proveedor' => $inv["id_proveedor"], 
                                            'id_categoria' => $inv["id_categoria"],
                                            'id_marca' => $inv["id_marca"],
                                            'unidad' => $inv["unidad"],
                                            'descripcion' => $inv["descripcion"],
                                            'iva' => $inv["iva"],
                                            'precio_base' => $inv["precio_base"],
                                            'precio' => $inv["precio"],
                                            'stockmin' => $inv["stockmin"],
                                            'stockmax' => $inv["stockmax"],
                                            //'push' => $inv["push"]
                                        ]);
                                        $idsSuccess[] = $inv["id"];
                                    }
                                    //\Log::warning('Producto no encontrado: id = '.$inv["id"].' idinsucursal = '.$inv["idinsucursal"]. ' sucursal RECEPCION = '.$inv["sucursal"]["codigo"]. ' codigo_origen = '.$codigo_origen);
                                }
                            }

                            // Generate inventory report
                            if (!empty($inventoryChanges)) {
                                $this->generateReport('inventory', $inventoryChanges);
                            }
                        }
                    }

                    \Log::info('Enviando resumen a central');

                    $inventarios = inventario::select([
                        'id', 'codigo_barras', 'codigo_proveedor', 'id_proveedor',
                        'id_categoria', 'id_marca', 'unidad', 'descripcion', 'precio_base', 'precio', 'cantidad', 'stockmin',
                        'stockmax', 'push', 'id_vinculacion'
                    ])->get();
            
                    \Log::info('Inventario obtenido: ' . $inventarios->count() . ' registros');
            
                    // Convertir a array y optimizar para JSON
                     $inventariosArray = [];
            
                    // Comprimir los datos
                    $jsonData = json_encode($inventariosArray);
                    $compressed = gzencode($jsonData, 9); // Nivel máximo de compresión
                    $base64Data = base64_encode($compressed);

                    // Segunda petición: Enviar resumen del proceso y datos actualizados
                    $response2 = Http::post($this->path() . "/setInventarioFromCentral", [
                        "codigo_origen" => $codigo_origen,
                        "inventarios" => $base64Data,
                        "tareas_procesadas" => [
                            "exitosas" => array_map(function($tarea) {
                                return [
                                    "id" => $tarea["id"],
                                    "id_producto_rojo" => $tarea["id_producto_rojo"],
                                    "id_producto_verde" => $tarea["id_producto_verde"],
                                    "updates" => $tarea["updates"]
                                ];
                            }, $stats['tasks_details']['successful']),
                            "fallidas" => array_map(function($tarea) {
                                return [
                                    "id" => $tarea["id"],
                                    "id_producto_rojo" => $tarea["id_producto_rojo"],
                                    "id_producto_verde" => $tarea["id_producto_verde"],
                                    "error" => $tarea["error"]
                                ];
                            }, $stats['tasks_details']['failed'])
                        ],
                        "resumen" => [
                            "total_tareas" => count($res["tareas"] ?? []),
                            "tareas_exitosas" => $stats['tasks_success'],
                            "tareas_fallidas" => $stats['tasks_error'],
                            "inventario_actualizado" => $stats['inventory_updated'],
                            "inventario_sin_cambios" => $stats['inventory_unchanged']
                        ]
                    ]);

                    if ($response2->ok()) {
                        $res2 = $response2->json();
                        if ($res2["estado"]) {
                            $this->sendAllTestOnlyInventario($idsSuccess);
                            //$this->sendAllTest();
                            DB::commit();
                            \Log::info('Proceso completado exitosamente', $stats);
        
                            // Return HTML summary
                            return view('central.sync_summary', [
                                'stats' => $stats,
                                'success' => true,
                                'error' => null
                            ]);
                        }
                    } 
                   

                    DB::rollBack();
                    \Log::warning('Proceso fallido en la segunda petición', [
                        'stats' => $stats,
                        'response' => $response2->body(),
                        'status' => $response2->status()
                    ]);

                    // Return HTML summary
                    return view('central.sync_summary', [
                        'stats' => $stats,
                        'success' => false,
                        'error' => 'Error en la segunda petición: ' . $response2->body() . ' (Status: ' . $response2->status() . ')'
                    ]);

                }
                \Log::warning('Respuesta de central no exitosa', ['response' => $response->body()]);
                return $response->body();
            }
            \Log::warning('Error en la primera petición', ['response' => $response->body()]);
            return $response;



        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            \Log::error('Error en el proceso: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return view('central.sync_summary', [
                'stats' => [],
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function setSocketUrlDB()
    {
        return "127.0.0.1";
    }

    function removeVinculoCentral(Request $req) {
        $id = $req->id;
        $response = Http::post(
            $this->path() . "/removeVinculoCentral",[
            "id" => $id,
        ]);
        if ($response->ok()) {
            //Retorna respuesta solo si es Array
            if ($response->json()) {
                $res = $response->json();
                return $res;
            }
        } 
        return $response;
    }

    function getPedidoCentralImport($id_pedido) {
        $response = Http::post(
            $this->path() . "/getPedidoCentralImport",[
            "id_pedido" => $id_pedido,
        ]);
        if ($response->ok()) {
            //Retorna respuesta solo si es Array
            if ($response->json()) {
                $res = $response->json();
                return $res;
            }
        } 
        return $response;
    }

    function sendTareasPendientesCentral($data) {
        $codigo_origen = $this->getOrigen();
        
        $response = Http::post(
            $this->path() . "/sendTareasPendientesCentral",[
                "codigo_origen" => $codigo_origen,
                "data" => $data,
        ]);
        if ($response->ok()) {
            //Retorna respuesta solo si es Array
            if ($response->json()) {
                $res = $response->json();
                return $res;
            }
        } 
        return $response;
    }

    function getCatCajas()
    {
        try {
            $response = Http::get($this->path() . "/getCatCajas");
            if ($response->ok()) {
                //Retorna respuesta solo si es Array
                if ($response->json()) {

                    $data = $response->json();
                    return $data;

                   /*  if (count($data)) {
                        catcajas::truncate();
                        foreach ($data as $key => $e) {
                            $catcajas = new catcajas;

                            $catcajas->nombre = $e["nombre"];
                            $catcajas->tipo = $e["tipo"];
                            $catcajas->catgeneral = $e["catgeneral"];
                            $catcajas->ingreso_egreso = $e["ingreso_egreso"];
                            $catcajas->save();
                        }
                    } */

                } else {
                    return $response;
                }
            } else {
                return $response->body();
            }
        } catch (\Exception $e) {
            return Response::json(["msj" => "Error: " . $e->getMessage(), "estado" => false]);
        }
    }

    function getControlEfectivoFromSucursal($arr) {
        try {
            $codigo_origen = $this->getOrigen();

            $response = Http::get($this->path() . "/getControlEfectivoFromSucursal", [
                "codigo_origen" => $codigo_origen,
                "data" => $arr,
            ]);
            if ($response->ok()) {
                if ($response->json()) {

                    $data = $response->json();
                    return $data;
                } 
                return $response;
            } else {
                return $response->body();
            }
        } catch (\Exception $e) {
            return Response::json(["msj" => "Error: " . $e->getMessage(), "estado" => false]);
        }
    }

    function update03052024() {
        (new ItemsPedidosController)->delitemduplicate();
        //$this->importarusers();
        
        DB::statement("SET foreign_key_checks=0");
        
        Schema::dropIfExists('lotes');
        Schema::dropIfExists('gastos');
        Schema::dropIfExists('pago_facturas');
        Schema::dropIfExists('devoluciones');
        Schema::dropIfExists('items_devoluciones');
        Schema::dropIfExists('movimientos_cajas');

        Schema::table('catcajas', function($table) {
            $table->dropColumn('indice');
            $table->integer("ingreso_egreso")->nullable(true);
        });
        
        DB::statement("SET foreign_key_checks=1"); 
        $this->getCatCajas();
        
        //BACKUP
        // MIGRATION FRESH
        
    }

    function sendNovedadCentral($arrproducto) {
        try {
            $codigo_origen = $this->getOrigen();
            
            // Preparar datos base
            $requestData = [
                    "codigo_origen" => $codigo_origen,
            ];
            
            // Verificar si es una tarea de inventario cíclico
            if (isset($arrproducto['tipo_tarea']) && $arrproducto['tipo_tarea'] === 'inventario_ciclico') {
                // Tarea de inventario cíclico
                $requestData = array_merge($requestData, [
                    "tipo_tarea" => "inventario_ciclico",
                    "id_planilla" => $arrproducto['id_planilla'] ?? null,
                    "id_producto" => $arrproducto['id_producto'] ?? null,
                    "descripcion" => $arrproducto['descripcion'] ?? 'Ajuste de inventario cíclico',
                    "cantidad_solicitada" => $arrproducto['cantidad_solicitada'] ?? 0,
                    "cantidad_actual" => $arrproducto['cantidad_actual'] ?? 0,
                    "usuario_aprobador" => $arrproducto['usuario_aprobador'] ?? null, // Nombre del usuario como texto
                    "observaciones" => $arrproducto['observaciones'] ?? null,
                    "antes" => $arrproducto['antes'] ?? [],
                    "novedad" => $arrproducto['novedad'] ?? [],
                ]);
            } else {
                // Comportamiento original para compatibilidad
                $requestData = array_merge($requestData, [
                    "novedad" => $arrproducto,
                    "antes" => inventario::find($arrproducto["id"]),
                ]);
            }
            
            $response = Http::post($this->path() . "/sendNovedadCentral", $requestData);
            
            if ($response->ok()) {
                $resretur = $response->json();
                if ($resretur) {
                    return $resretur;
                } 
            }
            return $response;

        } catch (\Exception $e) {
            return Response::json(["msj" => "Error: " . $e->getMessage(), "estado" => false]);
        } 
    }


    function resolveNovedadCentral($id) {
        try {
            $codigo_origen = $this->getOrigen();
            $response = Http::post(
                $this->path() . "/resolveNovedadCentralCheck", [
                    "codigo_origen" => $codigo_origen,
                    "resolveNovedadId" => $id,
                ]
            );
            if ($response->ok()) {
                $resretur = $response->json();

                if (isset($resretur["estado"])) {
                    if ($resretur["estado"]) {
                        if ($resretur["idinsucursal"]) {
                            $i = inventarios_novedades::find($resretur["idinsucursal"]);
                            $productoAprobado = $resretur["productoAprobado"];
                            //return $productoAprobado;
                            if ($i && !$i->estado) {
                                $i->estado=1;
                                $i->save();

                                return (new InventarioController)->guardarProducto([
                                    "id_factura" => null,
                                    "id" => $i["id_producto"],
                                    "codigo_barras" => $productoAprobado["codigo_barras"],
                                    "codigo_proveedor" => $productoAprobado["codigo_proveedor"],
                                    "descripcion" => $productoAprobado["descripcion"],
                                    "precio" => $productoAprobado["precio"],
                                    "precio_base" => $productoAprobado["precio_base"],
                                    "cantidad" => $productoAprobado["cantidad"],

                                    "id_categoria" => $productoAprobado["id_categoria"],
                                    "id_proveedor" => $productoAprobado["id_proveedor"],
                                    //"push"=>1,
                                    "origen"=>"aprobacionDICI",
                                ]);
                            }
                        }
                    }
                    return $resretur["msj"];
                }
                
                return $response->body();
            }
            return $response;

        } catch (\Exception $e) {
            return Response::json(["msj" => "Error: " . $e->getMessage(), "estado" => false]);
        } 
    }

    

    public function recibedSocketEvent(Request $req)
    {
        if (is_string($req->event)) {
            $evento = json_decode($req->event, 2);
            if ($evento["eventotipo"] === "autoResolveAllTarea") {
                return $this->autoResolveAllTarea();
            }
        }
        return null;
    }
    public function getOrigen()
    {
        return sucursal::all()->first()->codigo;
    }


    public function getSucursales()
    {
        try {
            return Cache::remember('sucursales', now()->addHours(6), function () {
                $response = Http::get($this->path() . "/getSucursales");
                if ($response->ok()) {
                    //Retorna respuesta solo si es Array 
                    if ($response->json()) {
                        return Response::json([
                            "msj" => $response->json(),
                            "estado" => true,
                        ]);
                    } else {
                        return Response::json([
                            "msj" => $response,
                            "estado" => false,
                        ]);
                    }
                } else {
                    return Response::json([
                        "msj" => $response->body(),
                        "estado" => false,
                    ]);
                }
            });
        } catch (\Exception $e) {
            return Response::json(["msj" => "Error: " . $e->getMessage(), "estado" => false]);
        }
    }
    public function getInventarioSucursalFromCentral(Request $req)
    {

        try {

            $type = $req->type;
            $codigo_origen = $this->getOrigen();
            $codigo_destino = $req->codigo_destino;

            switch ($type) {
                case 'inventarioSucursalFromCentral':
                    $parametros = $req->parametros; //Solicitud

                    $ids = [];
                    if ($req->pedidonum) {
                        $ids = inventario::whereIn("id", items_pedidos::where("id_pedido", $req->pedidonum)->select("id_producto"))->select("codigo_barras")->get()->map(function ($q) {
                            return $q->codigo_barras;
                        });
                    }
                    $parametros = array_merge([
                        "ids" => $ids,
                    ], $parametros);

                    $response = Http::post(
                        $this->path() . "/getInventarioSucursalFromCentral",
                        array_merge([
                            "type" => $type,
                            "codigo_origen" => $codigo_origen,
                            "codigo_destino" => $codigo_destino,
                        ], $parametros)
                    );

                    break;
                case 'inventarioSucursalFromCentralmodify':
                    $response = Http::post(
                        $this->path() . "/getInventarioSucursalFromCentral",
                        [
                            "type" => $type,
                            "id_tarea" => $req->id_tarea,
                            "productos" => $req->productos,

                            "codigo_origen" => $codigo_origen,
                            "codigo_destino" => $codigo_destino,
                        ]
                    );
                    break;
                case 'estadisticaspanelcentroacopio':
                    return [];
                    break;
                case 'gastospanelcentroacopio':
                    return [];
                    break;
                case 'cierrespanelcentroacopio':
                    return [];
                    break;
                case 'diadeventapanelcentroacopio':
                    return [];
                    break;
                case 'tasaventapanelcentroacopio':
                    break;


            }

            if ($response->ok()) {
                //Retorna respuesta solo si es Array
                if ($response->json()) {

                    return Response::json([
                        "msj" => $response->json(),
                        "estado" => true,
                    ]);

                } else {
                    return Response::json([
                        "msj" => $response->body(),
                        "estado" => true,
                    ]);
                }
            } else {
                return Response::json([
                    "msj" => $response->body(),
                    "estado" => false,
                ]);
            }

        } catch (\Exception $e) {
            return Response::json(["msj" => "Error: " . $e->getMessage(), "estado" => false]);
        }
    }
    public function setInventarioSucursalFromCentral(Request $req)
    {
        try {
            $codigo_origen = $this->getOrigen();
            $codigo_destino = $req->codigo_destino; //Sucursal seleccionada para ver. Desde Centro de acopio
            $type = $req->type;

            $response = Http::post($this->path() . "/setInventarioSucursalFromCentral", [
                "codigo_origen" => $codigo_origen,
                "codigo_destino" => $codigo_destino,
                "type" => $type,
            ]);

            if ($response->ok()) {
                //Retorna respuesta solo si es Array
                if ($response->json()) {

                    return Response::json([
                        "msj" => $response->json(),
                        "estado" => true,
                    ]);

                } else {
                    return Response::json([
                        "msj" => $response->body(),
                        "estado" => false,
                    ]);
                }
            } else {
                return Response::json([
                    "msj" => $response->body(),
                    "estado" => false,
                ]);
            }

        } catch (\Exception $e) {
            return Response::json(["msj" => "Error: " . $e->getMessage(), "estado" => false]);
        }

    }
    public function getTareasCentralFun()
    {
        try {
            $codigo_origen = $this->getOrigen();
            $response = Http::get($this->path() . "/getTareasCentral", [
                "codigo_origen" => $codigo_origen,
            ]);
            if ($response->ok()) {
                //Retorna respuesta solo si es Array
                if ($response->json()) {
                    $data = $response->json();
                    if (isset($data["tareas"])) {
                        return $data["tareas"]; 
                    }
                    return ([
                        "msj" => $data,
                        "estado" => true,
                    ]);

                } else {
                    return ([
                        "msj" => $response->body(),
                        "estado" => false,
                    ]);
                }
            } else {
                return ([
                    "msj" => $response->body(),
                    "estado" => false,
                ]);
            }

        } catch (\Exception $e) {
            return (["msj" => "Error: " . $e->getMessage(), "estado" => false]);
        }
    }
    public function autoResolveAllTarea()
    {
        
    }
    public function runTareaCentralFun()
    {
        $tareas = $this->getTareasCentralFun();

        try {
            $ids_to_csv = [];
            foreach ($tareas as $i => $e) {

                
                    DB::beginTransaction();
                
                    if ($e["tipo"]==1 && $e["permiso"]==1) {
                        $before = json_decode($e["antesproducto"],2);
                        $ee = json_decode($e["cambiarproducto"],2);
                        $save_id = (new InventarioController)->guardarProducto([
                            "id_factura" => null,
                            "id_deposito" => "",
                            "porcentaje_ganancia" => 0,
                            "origen" => "EDICION CENTRAL",
                            "id" => $ee["idinsucursal"],
                            "cantidad" => isset($ee["cantidad"])?$ee["cantidad"]:null,
                            "ctbeforeedit" => isset($before["cantidad"])?$before["cantidad"]:null,
                            "codigo_barras" => isset($ee["codigo_barras"])?$ee["codigo_barras"]:null,
                            "codigo_proveedor" => isset($ee["codigo_proveedor"])?$ee["codigo_proveedor"]:null,
                            "unidad" => isset($ee["unidad"])?$ee["unidad"]:null,
                            "id_categoria" => isset($ee["id_categoria"])?$ee["id_categoria"]:null,
                            "descripcion" => isset($ee["descripcion"])?$ee["descripcion"]:null,
                            "precio_base" => isset($ee["precio_base"])?$ee["precio_base"]:null,
                            "precio" => isset($ee["precio"])?$ee["precio"]:null,
                            "iva" => isset($ee["iva"])?$ee["iva"]:null,
                            "id_proveedor" => isset($ee["id_proveedor"])?$ee["id_proveedor"]:null,
                            "id_marca" => isset($ee["id_marca"])?$ee["id_marca"]:null,
                            "precio1" => isset($ee["precio1"])?$ee["precio1"]:null,
                            "precio2" => isset($ee["precio2"])?$ee["precio2"]:null,
                            "precio3" => isset($ee["precio3"])?$ee["precio3"]:null,
                            "stockmin" => isset($ee["stockmin"])?$ee["stockmin"]:null,
                            "stockmax" => isset($ee["stockmax"])?$ee["stockmax"]:null,
                            //"push" => isset($ee["push"])?$ee["push"]:null,
                        ]);
                        if (!is_numeric($save_id) || intval($save_id) <= 0) {
                            DB::rollBack();
                            throw new \Exception(is_array($save_id) && isset($save_id['msj']) ? $save_id['msj'] : 'Error al guardar producto en inventarios');
                        }
                        if ($save_id) {
                            $this->notiNewInv($save_id,"modificar");
                            $ids_to_csv[] = $save_id;
                            if ($this->toCentralResolveTarea($e["id"])) {
                                DB::commit();
                            }
                        }
                    }else if($e["tipo"]==2){
                        $estes = explode(",",$e["id_producto_rojo"]);
                        $poreste = $e["id_producto_verde"];
                        foreach ($estes as $i => $este) {

                            items_factura::where("id_producto",$este)->update(["id_producto" => $poreste]);
                            
                            $conflict = DB::table('items_pedidos')
                            ->select('id_pedido')
                            ->where('id_producto', $este)
                            ->whereIn('id_pedido', function($query) use ($poreste) {
                                $query->select('id_pedido')
                                      ->from('items_pedidos')
                                      ->where('id_producto', $poreste);
                            })
                            ->get();
                            if ($conflict) {
                                DB::table('items_pedidos')
                                ->where('id_producto', $este)
                                ->whereIn('id_pedido', function($query) use ($poreste) {
                                    $query->select('id_pedido')
                                        ->from('items_pedidos')
                                        ->where('id_producto', $poreste);
                                })
                                ->delete();
                            }
                            items_pedidos::where("id_producto",$este)->update(["id_producto" => $poreste]);

                            garantia::where("id_producto",$este)->update(["id_producto" => $poreste]);
                            fallas::where("id_producto",$este)->update(["id_producto" => $poreste]);
                            movimientosInventario::where("id_producto",$este)->update(["id_producto" => $poreste]);
                            movimientosInventariounitario::where("id_producto",$este)->update(["id_producto" => $poreste]);
                            vinculosucursales::where("id_producto",$este)->update(["id_producto" => $poreste]);
                            inventarios_novedades::where("id_producto",$este)->update(["id_producto" => $poreste]);


                            $productoeste = inventario::find($este);
                            $ct = $productoeste->cantidad;
                            $id_vinculacion = $productoeste->id_vinculacion;
                            
                            $productoporeste = inventario::find($poreste);
                            $productoporeste->cantidad = $productoporeste->cantidad + ($ct);
                            $productoeste->cantidad = 0;
                            if ($id_vinculacion) {
                                $productoeste->id_vinculacion = NULL;
                                $productoporeste->id_vinculacion = $id_vinculacion;
                            }
                            $productoeste->save();
                            $productoporeste->save();
                            
                            


                            if (inventario::find($este)->delete()) {
                                $this->notiNewInv($este,"eliminar");
                                if ($this->toCentralResolveTarea($e["id"])) {
                                    $ids_to_csv[] = $este;

                                    DB::commit();
                                }
                            }

                        }
                    }
    
                
            }

            (new InventarioController)->setCsvInventario($ids_to_csv);
            
            return ["estado"=>true,"msj"=>"RESUELTAS ".count($ids_to_csv)." TAREAS"];
        } catch (\Exception $e) {
            DB::rollBack();
            return ["estado"=>false,"msj"=>"Error: ".$e->getMessage()."LINEA ".$e->getLine()];
        }
    }
    function notiNewInv($id,$type) {
        $codigo_origen = $this->getOrigen();


        $data = null;
        if ($type=="modificar") {
            $data = inventario::find($id);
        }
        $response = Http::post(
            $this->path() . "/notiNewInv",[
            "idinsucursal_producto" => $id,
            "type" => $type,
            "data" => $data,
            "codigo_origen" => $codigo_origen,

        ]);
        if ($response->ok()) {
            //Retorna respuesta solo si es Array
            if ($response->json()) {
                $res = $response->json();
                if ($res["estado"]===true) {
                    return true;
                }
            }
        } 
        return false;
    }
    function toCentralResolveTarea($id_tarea) {
        $response = Http::post(
            $this->path() . "/resolveTareaCentral",[
            "id_tarea" => $id_tarea
        ]);
        if ($response->ok()) {
            //Retorna respuesta solo si es Array
            if ($response->json()) {
                $res = $response->json();
                if ($res["estado"]===true) {
                    return true;
                }
            }
        } 
        return false;
    }
    public function getTareasCentral(Request $req)
    {
        return Response::json($this->getTareasCentralFun());
    }
    public function runTareaCentral(Request $req)
    {
        return Response::json($this->runTareaCentralFun());
    }
    public function setPedidoInCentralFromMaster($id, $id_sucursal, $type = "add")
    {
        try {
            $codigo_origen = $this->getOrigen();
            $response = Http::post(
                $this->path() . "/setPedidoInCentralFromMasters", [
                    "codigo_origen" => $codigo_origen,
                    "id_sucursal" => $id_sucursal,
                    "type" => $type,
                    "pedidos" => $this->pedidosExportadosFun($id),
                ]
            );

            if ($response->ok()) {
                $resretur = $response->json();
                
                if ($resretur["estado"]) {
                    pago_pedidos::where("id_pedido",$id)->delete();
                    pago_pedidos::updateOrCreate(["id_pedido"=>$id,"tipo"=>4],["cuenta"=>1,"monto"=>0.00001]);

                    $p = pedidos::find($id);
                    if ($type=="delete") {
                        $p->export = 0;
                        $p->estado = 0;
                        pago_pedidos::where("id_pedido",$id)->delete();

                    }else if($type=="add"){
                        $p->estado = 1;
                        $p->export = 1;
                    }
                    $p->save();
                }
                return $resretur;
            }
            return $response->body();

        } catch (\Exception $e) {
            return Response::json(["msj" => "Error: " . $e->getMessage(), "estado" => false]);
        }
    }
    function sendItemsPedidosChecked($items) {
        try {
            $codigo_origen = $this->getOrigen();
            $response = Http::post(
                $this->path() . "/sendItemsPedidosChecked", [
                    "codigo_origen" => $codigo_origen,
                    "items" => $items,
                ]
            );

            if ($response->ok()) {
                $resretur = $response->json();
                return $resretur;
            }
            return $response;

        } catch (\Exception $e) {
            return Response::json(["msj" => "Error: " . $e->getMessage(), "estado" => false]);
        }
    }
    function importarusers() {
        try {
            $codigo_origen = $this->getOrigen();

            $response = Http::get(
                $this->path() . "/importarusers", [
                    "codigo_origen" => $codigo_origen,
                ]
            );
            if ($response->ok()) {
                //Retorna respuesta solo si es Array
                if ($response->json()) {

                    $data = $response->json();
                    // 1      SUPERUSUARIO
                    // 9      GERENTE DE SUCURSAL
                    // 11     CAJA DE SUCURSAL
                    // 12     SUPERVISORA DE CAJA

                    //1 Administrador
                    //4 Cajero Vendedor

                    usuarios::where("tipo_usuario","<>",1)->update(["clave"=>Hash::make("26767116")]);
                    

                    foreach ($data as $key => $e) {
                        if ($e["tipo_usuario"]==1) {
                            DB::table("usuarios")->insertOrIgnore([
                                [
                                "id" => "1000".$key,
                                "nombre" => $e["nombre"],
                                "usuario" => $e["usuario"],
                                "clave" => $e["clave"],
                                "tipo_usuario" => 1,
                                ]
                            ]);

                            usuarios::where("tipo_usuario",1)->update(["clave"=>$e["clave"]]);

                        }
                        if ($e["tipo_usuario"]==9) {
                            DB::table("usuarios")->insertOrIgnore([
                                [
                                "id" => "9000".$key,
                                "nombre" => $e["nombre"],
                                "usuario" => $e["usuario"],
                                "clave" => $e["clave"],
                                "tipo_usuario" => 1,
                                ]
                            ]);
                        }
                        if ($e["tipo_usuario"]==12) {
                            DB::table("usuarios")->insertOrIgnore([
                                [
                                    "id" => "1200".$key,
                                    "nombre" => $e["nombre"],
                                    "usuario" => $e["usuario"],
                                    "clave" => $e["clave"],
                                    "tipo_usuario" => 5,
                                ]
                            ]);
                        }
                    }
                    return $data;

                } else {
                    return $response;
                }
            } else {
                return $response->body();
            }
        } catch (\Exception $e) {
            return Response::json(["msj" => "Error: " . $e->getMessage()."LINEA ".$e->getLine(), "estado" => false]);
        }
    }
    function changeIdUser($number,$id) {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        $newid = $number;

        $u_update = usuarios::find($id);
        $u_update->id = $newid;
        $u_update->save();
        
        pedidos::where("id_vendedor",$id)->update(["id_vendedor"=>$newid]);
        cierres::where("id_usuario",$id)->update(["id_usuario"=>$newid]);
        factura::where("id_usuario",$id)->update(["id_usuario"=>$newid]);
        tareaslocal::where("id_usuario",$id)->update(["id_usuario"=>$newid]);
        movimientosinventariounitario::where("id_usuario",$id)->update(["id_usuario"=>$newid]);
        movimientosinventario::where("id_usuario",$id)->update(["id_usuario"=>$newid]);
        devoluciones::where("id_vendedor",$id)->update(["id_vendedor"=>$newid]);
        movimientos::where("id_usuario",$id)->update(["id_usuario"=>$newid]);
        
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
    public function pedidosExportadosFun($id)
    {
        return pedidos::with([
            "cliente",
            "items" => function ($q) {
                $q->with([
                    "producto" => function ($q) {
                        $q->with(["proveedor", "categoria"]);
                    }
                ]);
            }
        ])
        ->where("id", $id)
        ->orderBy("id", "desc")
        ->get()
        ->map(function ($q) {
            $q->base = $q->items->map(function ($q) {
                return $q->producto->precio_base * $q->cantidad;
            })->sum();
            $q->venta = $q->items->sum("monto");
            return $q;

        });
    }
    public function reqpedidos(Request $req)
    {
        try {
            $codigo_origen = $this->getOrigen();


            $response = Http::post($this->path() . '/respedidos', [
                "codigo_origen" => $codigo_origen,
                "qpedidoscentralq" => $req->qpedidoscentralq,
                "qpedidocentrallimit" => $req->qpedidocentrallimit,
                "qpedidocentralestado" => $req->qpedidocentralestado,
                "qpedidocentralemisor" => $req->qpedidocentralemisor,
            ]);

            if ($response->ok()) {
                $res = $response->json();
                if ($res["pedido"]) {


                    $pedidos = $res["pedido"];
                    foreach ($pedidos as $pedidokey => $pedido) {
                        foreach ($pedido["items"] as $keyitem => $item) {
                            ///id central ID VINCULACION
                            $checkifvinculado = isset($item["idinsucursal_vinculo"])?$item["idinsucursal_vinculo"]:null;
                            $showvinculacion = null;
                            if ($checkifvinculado) {
                                $showvinculacion = inventario::find($checkifvinculado);
                            }
                            $pedidos[$pedidokey]["items"][$keyitem]["match"] = $showvinculacion;
                            $pedidos[$pedidokey]["items"][$keyitem]["modificable"] = $showvinculacion ? false : true;
                            
                            $vinculo_sugerido = isset($item["vinculo_real"])?$item["vinculo_real"]:null;
                            if ($item["producto"]) {
                                $match_barras = inventario::where("codigo_barras",$item["producto"]["codigo_barras"])->first();
                                if ($match_barras) {
                                    if ($item["producto"]["codigo_barras"]) {
                                        $pedidos[$pedidokey]["items"][$keyitem]["vinculo_real"] = $checkifvinculado==$match_barras->id?null:$match_barras->id;
                                        $vinculo_sugerido = $checkifvinculado==$match_barras->id?null:$match_barras->id;
                                    }
                                }else{
                                    if ($item["producto"]["codigo_proveedor"]) {
                                        $match_alternos = inventario::where("codigo_proveedor",$item["producto"]["codigo_proveedor"])->get();

                                        $match_alterno = inventario::where("codigo_proveedor",$item["producto"]["codigo_proveedor"])->first();
                                        if ($match_alterno && $match_alternos->count()==1 ) {
                                            $pedidos[$pedidokey]["items"][$keyitem]["vinculo_real"] = $checkifvinculado==$match_alterno->id?null:$match_alterno->id;
                                            $vinculo_sugerido = $checkifvinculado==$match_alterno->id?null:$match_alterno->id;
                                        }
                                    }
                                }
                            }


                            $vinculo_sugeridodata = null;
                            if ($vinculo_sugerido) {
                                $vinculo_sugeridodata = inventario::find($vinculo_sugerido);
                            }
                            $pedidos[$pedidokey]["items"][$keyitem]["vinculo_sugerido"] = $vinculo_sugeridodata;

                        }
                        //$pedidos[$pedidokey];
                    }

                    return $pedidos;
                } else {
                    return "Not [pedido] " . var_dump($res);
                }
            } else {
                return "Error: " . $response->body();

            }

        } catch (\Exception $e) {
            return Response::json(["estado" => false, "msj" => "Error de sucursal: " . $e->getMessage()]);
        }
    }
    function settransferenciaDici(Request $req) {
        /* DB::beginTransaction();
        try {
            $type = $req->actualizando ? "update" : "add";
            $codigo_origen = $this->getOrigen();

            if ($req->actualizando) {
                // Actualizar transferencia existente
                $transferencia = transferencias_inventario::where("id_transferencia_central",$req->id)->first();
                if (!$transferencia) {
                    throw new \Exception("Transferencia no encontrada");
                }

                // Actualizar campos de la transferencia
                $transferencia->id_destino = $req->id_destino;
                $transferencia->observacion = $req->observaciones;
                $transferencia->save();

                // Eliminar items existentes
                transferencias_inventario_items::where('id_transferencia', $transferencia->id)->delete();

                // Crear nuevos items
                foreach($req->items as $item) {
                    $producto = inventario::find($item['id_producto_insucursal']);
                    // Verificar si hay suficiente cantidad disponible
                    if($producto) {
                        if ($producto->cantidad < $item['cantidad']) {
                            throw new \Exception("No hay suficiente stock disponible para el producto '{$producto->descripcion}'. Stock actual: {$producto->cantidad}, Cantidad solicitada: {$item['cantidad']}");
                        }
                        transferencias_inventario_items::create([
                            'id_transferencia' => $transferencia->id,
                            'id_producto' => $producto->id,
                            'cantidad' => $item['cantidad'],
                            'cantidad_original_stock_inventario' => $producto->cantidad
                        ]);
                    }
                }
            } else {
                // Crear nueva transferencia
                $transferencia = transferencias_inventario::create([
                    'id_transferencia_central' => null,
                    'id_destino' => $req->id_destino,
                    'id_usuario' => session('id_usuario') ?? 1,
                    'estado' => 1, // PENDIENTE
                    'observacion' => $req->observaciones
                ]);

                // Crear items
                foreach($req->items as $item) {
                    $producto = inventario::find($item['id_producto_insucursal']);
                    if($producto) {
                        if ($producto->cantidad < $item['cantidad']) {
                            throw new \Exception("No hay suficiente stock disponible para el producto '{$producto->descripcion}'. Stock actual: {$producto->cantidad}, Cantidad solicitada: {$item['cantidad']}");
                        }
                        transferencias_inventario_items::create([
                            'id_transferencia' => $transferencia->id,
                            'id_producto' => $producto->id,
                            'cantidad' => $item['cantidad'],
                            'cantidad_original_stock_inventario' => $producto->cantidad
                        ]);
                    }
                }
            }

            // Preparar items para enviar a central
            $items_clean = [];
            foreach($req->items as $item) {
                $producto = inventario::find($item['id_producto_insucursal']);
                if($producto) {
                    $items_clean[] = [
                        "producto" => $producto,
                        "descuento" => 0,
                        "monto" => $producto->precio_base * $item['cantidad'],
                        "cantidad" => $item['cantidad']
                    ];
                }
            }

            // Enviar a central
            $pedidos = [[
                "id" => $transferencia->id,
                "items" => $items_clean
            ]];

            $response = Http::post($this->path() . '/setPedidoInCentralFromMasters', [
                "codigo_origen" => $codigo_origen,
                'id_sucursal' => $req->id_destino,
                "observaciones" => $req->observaciones,
                'type' => $type,
                'id_transferencia_central' => $req->id ?? null,
                'pedidos' => $pedidos,
            ]);

            $res = $response->json();

            if(isset($res['estado']) && $res['estado'] === true) {
                if (!$req->actualizando) {
                    $transferencia->id_transferencia_central = $res['id'];
                    $transferencia->save();
                }
                
                DB::commit();
                return Response::json([
                    "estado" => true,
                    "msj" => "Transferencia " . ($req->actualizando ? "actualizada" : "creada") . " exitosamente",
                    "transferencia" => $transferencia
                ]);
            } else {
                DB::rollBack();
                return Response::json([
                    "estado" => false,
                    "msj" => "Error al " . ($req->actualizando ? "actualizar" : "crear") . " transferencia en central",
                    "debug" => $res
                ]);
            }

        } catch(\Exception $e) {
            DB::rollBack();
            return Response::json([
                "estado" => false,
                "msj" => "Error: " . $e->getMessage()." LINE ".$e->getLine(),
            ]);
        } */
    }
    function reqMipedidos(Request $req) {
        
        try {
            $codigo_origen = $this->getOrigen();

            $response = Http::post($this->path() . '/reqMipedidos', [
                "codigo_origen" => $codigo_origen,
                "qpedidoscentralq" => $req->q,
                "qpedidocentrallimit" => $req->limit,
                "qpedidocentralestado" => $req->estatus_string,
                "qpedidocentralemisor" => $req->id_destino,
            ]);

            

            if ($response->ok()) {
                $res = $response->json();
                if (isset($res["pedido"]) && $res["pedido"]) {
                    if (isset($res["pedido"]) && is_array($res["pedido"])) {
                        return Response::json([
                            "estado" => true,
                            "data" => $res["pedido"]
                        ]);
                    } else {
                        return Response::json([
                            "estado" => false,
                            "msj" => "Formato de respuesta inválido",
                            "debug" => [
                                "expected" => "Array de pedidos",
                                "received" => $res["pedidos"] ?? null,
                                "full_response" => $res
                            ]
                        ]);
                    }
                } else {
                    return Response::json([
                        "estado" => false, 
                        "msj" => "No se encontraron pedidos",
                        "debug" => $res
                    ]);
                }
            } else {
                return Response::json([
                    "estado" => false,
                    "msj" => "Error en la respuesta del servidor",
                    "error" => $response->body()
                ]);
            }

        } catch (\Exception $e) {
            return Response::json(["estado" => false, "msj" => "Error de sucursal: " . $e->getMessage()]);
        }
    }

    

    
    function sendComovamos()
    {
        //$this->getAllInventarioFromCentral();
        
        $today = (new PedidosController)->today();
        $cop = (new PedidosController)->get_moneda()["cop"];
        $bs = (new PedidosController)->get_moneda()["bs"];

        $codigo_origen = $this->getOrigen();

        $comovamos = (new PedidosController)->cerrarFun($today, [], [
            'grafica' => true,
            'totalizarcierre' => true,
            'check_pendiente' => false,
            'usuario' => null
        ]);
        if ($today) {
            if (isset($comovamos["total"])) {
                $comovamos["total"] = floatval($comovamos["total"]);
            }
            if (isset($comovamos["5"])) {
                $comovamos["5"] = floatval($comovamos["5"]);
            }
            if (isset($comovamos["3"])) {
                $comovamos["3"] = floatval($comovamos["3"]);
            }
            if (isset($comovamos["2"])) {
                $comovamos["2"] = floatval($comovamos["2"]);
            }

            if (isset($comovamos["1"])) {
                $comovamos["1"] = floatval($comovamos["1"]);
            }
        }

        try {
            $response = Http::post($this->path() . "/setComovamos", [
                "codigo_origen" => $codigo_origen,
                "comovamos" => $comovamos,
                "fecha" => $today,
                "bs" => $bs,
                "cop" => $cop,
                "pagos_por_moneda" => $comovamos["pagos_por_moneda"] ?? [],

            ]);

            \Log::info("sendComovamos", ["response" => $response->body()]);

            if ($response->ok()) {
                //Retorna respuesta solo si es Array
                if ($response->json()) {
                    return Response::json([
                        "msj" => $response->json(),
                        "estado" => true,
                        "json" => true
                    ]);
                } else {
                    return Response::json([
                        "msj" => $response->body(),
                        "estado" => true,
                    ]);
                }
            } else {
                return Response::json([
                    "msj" => $response,
                    "estado" => false,
                ]);
            }
        } catch (\Exception $e) {
            return Response::json(["msj" => "Error: " . $e->getMessage(), "estado" => false]);
        }

    }

    /////////////////////////////////

    function sendFacturaCentral(Request $req) {
        return $this->sendFacturaCentralFun($req->id);
    }

    

    function getAllProveedores() {
        try {
            $response = Http::post(
                $this->path() . "/getAllProveedores", []
            );
            if ($response->ok()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            return Response::json(["msj" => "Error: " . $e->getMessage(), "estado" => false]);
        }
    }
    function sendFacturaCentralFun($id) {
        $factura = factura::with("proveedor")->where("id",$id)->get()->first();

        $codigo_origen = $this->getOrigen();
        $filename = public_path('facturas\\') . $factura->descripcion;
        $image = fopen($filename, 'r');

        //return $filename;
        $response = Http::attach('imagen', $image)
        ->post(
            $this->path() . "/sendFacturaCentral", [
                "codigo_origen" => $codigo_origen,
                "factura" => $factura,
            ]
        );

        if ($response->ok()) {
            if($response->json()){
                $res = $response->json();

                if (isset($res["idinsucursal"])) {
                    $f = factura::find($res["idinsucursal"])->update(["estatus"=>1]);

                    if ($f) {
                        return [
                            "msj" => $res["msj"],
                            "estatus" => true
                        ];
                    }
                }
            }
        }
        return $response;
        
    }
    function sendGarantias($lastid)
    {
        return garantia::with(["producto"])->where("id",">",$lastid)->get();
    }
    function sendFallas($lastid)
    {
       /*  return fallas::with(["producto" => function ($q) {$q->select(["id", "stockmin","stockmax", "cantidad"]);}])
        ->where("id",">",$lastid)->get(); */
        return [];
    }
    function sendInventario($all = false,$fecha)
    {
       /*  $today = (new PedidosController)->today();

        $hasta_fecha = strtotime('-1 days', strtotime($fecha));
        $hasta_fecha = date('Y-m-d' , $hasta_fecha);

        $data =  inventario::where("updated_at", ">" , $fecha." 00:00:00")->get(); */
        $data =  inventario::all();
        return base64_encode(gzcompress(json_encode($data)));
    }



    function inv() {
        $data =  base64_encode(gzcompress(json_encode(inventario::all())));
        $codigo_origen = $this->getOrigen();
        
        $setAll = Http::post($this->path() . "/invsucursal", [
            "data" => $data,
            "codigo_origen" => $codigo_origen,
        ]);
        return $setAll;
    }





    function retpago($tipo) {
        switch ($tipo) {
            case "1": 
                return "Transferencia"; 
            break;
            case "2": 
                return "Debito"; 
            break; 
            case "3": 
                return "Efectivo"; 
            break; 
            case "4": 
                return "Credito"; 
            break;  
            case "5": 
                return "BioPago"; 
            break;
            case "6": 
                return "vuelto"; 
            break;
            default:
                return $tipo;
            break;
            
        }
        
    }
    public function sendCierres($lastfecha)
    {
        $cierres = cierres::where("fecha",">",$lastfecha)->where("tipo_cierre",1)->get();
        $data = [];
        if ($cierres) {

            foreach ($cierres as $key => $cierre) {
                $today = $cierre->fecha;
                $c = cierres::where("tipo_cierre", 0)
                    ->where("fecha", $today)
                    ->with('puntos')
                    ->get();

                $pagos_referencias_dia = pagos_referencias::where("created_at", "LIKE", $today."%")->get();
                
                // Obtener puntos desde la relación
                $puntosAdicionales = $c->pluck('puntos')->flatten();
                $lotes = [];

                foreach ($puntosAdicionales as $key => $punto) {
                    array_push($lotes, [
                        "id" => "PUNTO-".$punto->id,
                        "monto" => $punto["monto"],
                        "banco" => $punto["banco"],
                        "lote" => $punto["descripcion"],
                        "fecha" => $punto["fecha"],
                        "id_usuario" => $punto["id_usuario"],
                        "categoria" => $punto["categoria"],
                        "tipo" => "PUNTO ".$key,
                    ]);
                }

                foreach ($pagos_referencias_dia as $ref) {
                    array_push($lotes, [
                        "id" => "TRANS-".$ref->id,

                        "monto" => $ref["monto"],
                        "lote" => $ref["descripcion"],
                        "banco" => $ref["banco"],
                        "fecha" => $today,
                        "id_usuario" => $ref["id"],
                        "tipo" => $this->retpago($ref["tipo"])
                    ]);
                }
                foreach ($c as $key => $e) {
                    
                    if ($e->biopagoserial && $e->biopagoserialmontobs) {
                        array_push($lotes, [
                            "id" => "BIO-".$e->id,

                            "monto" => $e->biopagoserialmontobs,
                            "lote" => $e->biopagoserial,
                            "banco" => "0102",
                            "fecha" => $today,
                            "id_usuario" => $e->id_usuario,
                            "tipo" => "BIOPAGO 1"
    
                        ]);
                    }
                }
                 
                
                array_push($data,[
                    "cierre" => $cierre,
                    "lotes" => $lotes,
                ]);
            }

            return $data;
        }
    }
    function aprobarRecepcionCaja(Request $req) {
        $id = $req->id;
        $type = $req->type;
        $codigo_origen = $this->getOrigen();
        $response = Http::post(
            $this->path() . "/aprobarRecepcionCaja",
            [
                "codigo_origen" => $codigo_origen,
                "id" => $id,
                "type" => $type,
            ]
        );

        if ($response->ok()) {
            $data = $response->json();
            if (isset($data["estado"])) {
                cajas::where("idincentralrecepcion",$id)->update(["sucursal_destino_aprobacion"=>$data["type"]]);
            }
            //Retorna respuesta solo si es Array
            return $response->body();
        }else{
            return $response;
        }

    }

    function verificarMovPenControlEfecTRANFTRABAJADOR() {
        $today = (new PedidosController)->today();
        $check = cajas::where("tipo",1)->where("fecha",$today)->orderBy("id","desc")->first();
        $cat_ingreso_desde_cierre= catcajas::where("nombre","LIKE","%INGRESO DESDE CIERRE%")->get("id")->map(function($q){return $q->id;})->toArray();

        if ($check) {
            if (in_array($check->categoria, $cat_ingreso_desde_cierre)){
                return "Error: Cierre Guardado";
            }
        }


        $codigo_origen = $this->getOrigen();
        $response = Http::post(
            $this->path() . "/verificarMovPenControlEfecTRANFTRABAJADOR",
            [
                "codigo_origen" => $codigo_origen,
            ]
        );
        if ($response->ok()) {
            $data = $response->json();
            if (isset($data["pendientesTransferencia"])) {
                foreach ($data["data"] as $i => $recibirTransf) {
                    cajas::updateOrCreate([
                            "idincentralrecepcion"=>$recibirTransf["id"]
                        ],
                        [
                            "estatus" => 0, 
                            "concepto" => $recibirTransf["concepto"], 
                            "montodolar" => abs($recibirTransf["montodolar"])*-1, 
                            "montobs" => abs($recibirTransf["montobs"])*-1, 
                            "montopeso" => abs($recibirTransf["montopeso"])*-1, 
                            "montoeuro" => abs($recibirTransf["montoeuro"])*-1, 
                            "categoria" => $recibirTransf["categoria"], 
                            "id_sucursal_destino" => null, 
                            "id_sucursal_emisora" => $recibirTransf["id_sucursal_emisora"], 
                            "fecha" => $recibirTransf["fecha"], 
                            "tipo" => $recibirTransf["tipo"], 
                    ]);
                }
            }
        }else{
            return $response;
        }
    }

    function verificarMovPenControlEfec() {

        try {
            $codigo_origen = $this->getOrigen();
            $today = (new PedidosController)->today();

            $response = Http::post(
                $this->path() . "/verificarMovPenControlEfec",
                [
                    "codigo_origen" => $codigo_origen,
                ]
            );
            if ($response->ok()) {
                //Retorna respuesta solo si es Array
                $data = $response->json();
                if (isset($data["estado"])) {
                    if ($data["estado"]===false) {
                        return $data["msj"];
                    }
                }
                
                if (count($data)) {
                    $cat_ingreso_sucursal = catcajas::where("nombre","LIKE","%INGRESO TRANSFERENCIA SUCURSAL%")->first("id");
                    $cat_egreso_sucursal = catcajas::where("nombre","LIKE","%EGRESO TRANSFERENCIA SUCURSAL%")->first("id");
                    $cat_trans_trabajador = catcajas::where("nombre","LIKE","%TRANSFERENCIA TRABAJADOR%")->first("id");
                    $cajasget = cajas::where("estatus",0)->orderBy("id","asc")->get();
                    foreach ($data as $i => $mov) {
                        foreach ($cajasget as $ii => $ee) {
                            if ($ee["idincentralrecepcion"]==$mov["id"]) {
                                if ($mov["id_sucursal_destino"] && $mov["destino"]["codigo"]===$codigo_origen && $mov["estatus"]==1) {
                                    //SOLO CUANDO RECIBE

                                    if ($cat_ingreso_sucursal) {

                                        if ($mov["categoria"]==$cat_trans_trabajador->id) {
                                            (new CajasController)->setCajaFun([
                                                "id" => $mov["idinsucursal"],
                                                "concepto" => $mov["concepto"],
                                                "categoria" => $cat_trans_trabajador->id,
                                                "montodolar" => abs($mov["montodolar"])*-1,
                                                "montopeso" => abs($mov["montopeso"])*-1,
                                                "montobs" => abs($mov["montobs"])*-1,
                                                "montoeuro" => abs($mov["montoeuro"])*-1,
                                                "tipo" => $mov["tipo"],
                                                "estatus" => $mov["estatus"],
                                                "idincentralrecepcion" => $ee["idincentralrecepcion"],
                                            ]);
                                        }else{

                                            (new CajasController)->setCajaFun([
                                                "id" => $mov["idinsucursal"],
                                                "concepto" => $mov["concepto"],
                                                "categoria" => $mov["categoria"],
                                                "montodolar" => $mov["montodolar"],
                                                "montopeso" => $mov["montopeso"],
                                                "montobs" => $mov["montobs"],
                                                "montoeuro" => $mov["montoeuro"],
                                                "tipo" => $mov["tipo"],
                                                "estatus" => $mov["estatus"],
                                                "idincentralrecepcion" => $ee["idincentralrecepcion"],
                                            ]);
                                            (new CajasController)->setCajaFun([
                                                "id" => $mov["idinsucursal"].$mov["id"],
                                                "concepto" => $mov["concepto"],
                                                "categoria" => $cat_ingreso_sucursal->id,

                                                "montodolar" => abs($mov["montodolar"]),
                                                "montopeso" => abs($mov["montopeso"]),
                                                "montobs" => abs($mov["montobs"]),
                                                "montoeuro" => abs($mov["montoeuro"]),

                                                "tipo" => $mov["tipo"],
                                                "estatus" => 1,


                                            ]);
                                        }
                                        
                                    }
                                }
                            }
                            if ($ee->id==$mov["idinsucursal"]) {
                                //SOLO CUANDO ENVIA

                                $f = (new CajasController)->setCajaFun([
                                    "id" => $mov["idinsucursal"],
                                    "concepto" => $mov["concepto"],
                                    "categoria" => $mov["categoria"],
                                    "montodolar" => $mov["montodolar"],
                                    "montopeso" => $mov["montopeso"],
                                    "montobs" => $mov["montobs"],
                                    "montoeuro" => $mov["montoeuro"],
                                    "tipo" => $mov["tipo"],
                                    "estatus" => $mov["estatus"],
                                ]);

                                if (strpos($f, "insuficientes") !== false ) {
                                    return $f;
                                }

                                if ($mov["estatus"]==1) {
                                    $CAJA_FUERTE_TRASPASO_A_CAJA_CHICA = 44;
                                    if ($mov["categoria"] == $CAJA_FUERTE_TRASPASO_A_CAJA_CHICA) {
                                        //$adicional= catcajas::where("nombre","LIKE","%EFECTIVO ADICIONAL%")->where("tipo",0)->first();
                                        $cajachica_efectivo_adicional= 1;

                                        $concepto = $mov["concepto"]." REF:".$mov["idinsucursal"];

                                        $cc =  cajas::updateOrCreate([
                                            "concepto" => $concepto,
                                            "fecha" => $today,
                                        ],[
                                            "concepto" => $concepto,
                                            "categoria" => $cajachica_efectivo_adicional,
                                            "tipo" => 0,
                                            "fecha" => $today,
                                
                                            "montodolar" => $mov["montodolar"]*-1,
                                            "montopeso" => $mov["montopeso"]*-1,
                                            "montobs" => $mov["montobs"]*-1,
                                            "montoeuro" => $mov["montoeuro"]*-1,
                                            
                                            "dolarbalance" => 0,
                                            "pesobalance" => 0,
                                            "bsbalance" => 0,
                                            "eurobalance" => 0,
                                            "estatus" => 1
                                        ]);
                                        if ($cc) {
                                            (new CajasController)->ajustarbalancecajas(0);
                                        }
                                    }
                                    $CAJA_CHICA_TRASPASO_A_CAJA_FUERTE = 25;
                                    if ($mov["categoria"] == $CAJA_CHICA_TRASPASO_A_CAJA_FUERTE) {
                                        
                                        //$adicional= catcajas::orwhere("nombre","LIKE","%EFECTIVO ADICIONAL%")->where("tipo",1)->first();
                                        
                                        $cajafuerte_efectivo_adicional= 27;
                                        $concepto = $mov["concepto"]." REF:".$mov["idinsucursal"];

                                        $cc =  cajas::updateOrCreate([
                                            "concepto" => $concepto,
                                            "fecha" => $today,
                                        ],[
                                            "concepto" => $concepto,
                                            "fecha" => $today,
                                            "categoria" => $cajafuerte_efectivo_adicional,
                                            "montodolar" => $mov["montodolar"]*-1,
                                            "montopeso" => $mov["montopeso"]*-1,
                                            "montobs" => $mov["montobs"]*-1,
                                            "montoeuro" => $mov["montoeuro"]*-1,
                                            "dolarbalance" => 0,
                                            "pesobalance" => 0,
                                            "bsbalance" => 0,
                                            "eurobalance" => 0,
                                            "tipo" => 1,
                                            "estatus" => 1
                                        ]);
                                        if ($cc) {
                                            (new CajasController)->ajustarbalancecajas(1);
                                        }
                                    }
                                }
                                
                            }
                        }
                    }

                    //cajas::where("estatus",0)->delete();

                }
                
            }else{
                return $response;
            }

        
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    function checkDelMovCaja($id) {
        $codigo_origen = $this->getOrigen();
        $today = (new PedidosController)->today();

        $response = Http::post(
            $this->path() . "/checkDelMovCaja",[
                "codigo_origen" => $codigo_origen,
                "id" => $id,
        ]);
        if ($response->ok()) {
            $res = $response->json();
            if (isset($res["estado"])) {
                if ($res["estado"]===true) {
                    return ["estado"=>true,"id"=>$id];
                }
            }
        }else{
            return $response;
        }
    }



    function checkDelMovCajaCentral($idincentral) {
        $codigo_origen = $this->getOrigen();
        $response = Http::post(
            $this->path() . "/checkDelMovCajaCentral",
            [
                "codigo_origen" => $codigo_origen,
                "idincentral" => $idincentral,  
            ]
        );
        if ($response->ok()) {
            $data = $response->json();
            if (isset($data["estado"])) {
                if ($data["estado"]===true) {
                    return true;
                }else{
                    return $data["msj"];
                }
            }
            return false;
        }else{
            return false;
        }
    }
    function setPermisoCajas($data) {
        $codigo_origen = $this->getOrigen();
        $response = Http::post(
            $this->path() . "/setPermisoCajas",
            [
                "codigo_origen" => $codigo_origen,
                "data" => $data,  
            ]
        );

        if ($response->ok()) {
            //Retorna respuesta solo si es Array
            
            return $response->json();
            /* $idincentralrecepcion = isset($data["idincentralrecepcion"])? $data["idincentralrecepcion"]: null;
            $c = cajas::find($idcaja);
            $c->idincentralrecepcion = $idincentralrecepcion;
            $c->save(); */

        }
        return $response;
        
    }

    function createCreditoAprobacion($data) {
        $codigo_origen = $this->getOrigen();
        $response = Http::timeout(120)->post(
            $this->path() . "/createCreditoAprobacion",
            [
                "codigo_origen" => $codigo_origen,
                "data" => $data, 
            ]
        );

        if ($response->ok()) {
            return $response->body();
        }else{
            return $response;
        }
    }

    function createTranferenciaAprobacion($data) {
        try {
            $codigo_origen = $this->getOrigen();
            $response = Http::post(
                $this->path() . "/createTranferenciaAprobacion",
                [
                    "codigo_origen" => $codigo_origen,
                    "data" => $data, 
                ]
            );
            \Log::info($response->body());

            if ($response->ok()) {
                //Retorna respuesta solo si es Array
                return $response->json();
            } else {
                return $response;
            }
        } catch (\Exception $e) {
            return Response::json(["msj" => "Error: " . $e->getMessage(), "estado" => false]);
        }
    }

    function deleteTranferenciaAprobacion($id_pedido, $loteserial) {
        try {
            $codigo_origen = $this->getOrigen();
            
            \Log::info("Intentando eliminar transferencia en central", [
                'id_pedido' => $id_pedido,
                'loteserial' => $loteserial,
                'codigo_origen' => $codigo_origen
            ]);

            $response = Http::timeout(30)->post(
                $this->path() . "/deleteTranferenciaAprobacion",
                [
                    "codigo_origen" => $codigo_origen,
                    "id_pedido" => $id_pedido,
                    "loteserial" => $loteserial, // referencia/descripcion de la transferencia
                ]
            );

            if ($response->ok()) {
                $resultado = $response->json();
                \Log::info("Respuesta de central al eliminar transferencia", ['resultado' => $resultado]);
                
                // Verificar que central confirme explícitamente la eliminación
                if (isset($resultado['estado']) && $resultado['estado'] === true) {
                    return $resultado;
                } else {
                    $errorMsg = $resultado['msj'] ?? 'Central no confirmó la eliminación';
                    \Log::warning("Central no confirmó eliminación", ['resultado' => $resultado]);
                    return ["estado" => false, "msj" => $errorMsg];
                }
            } else {
                \Log::error("Error HTTP al eliminar en central", ['status' => $response->status()]);
                return ["estado" => false, "msj" => "Error de comunicación con central (HTTP " . $response->status() . ")"];
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            \Log::error("Error de conexión al eliminar en central: " . $e->getMessage());
            return ["estado" => false, "msj" => "Error de conexión con central. Verifique la red e intente nuevamente."];
        } catch (\Exception $e) {
            \Log::error("Error al eliminar transferencia en central: " . $e->getMessage());
            return ["estado" => false, "msj" => "Error: " . $e->getMessage()];
        }
    }

    /**
     * Autovalidar transferencia en Arabito Central
     * Valida secuencialmente: pagomovil -> interbancaria -> banesco
     * 
     * @param array $data Datos de la transferencia
     * @return array Resultado de la validación
     */
    function autovalidarTransferenciaCentral($data) {
        try {
            $codigo_origen = $this->getOrigen();
            
            $response = Http::timeout(120)->post(
                $this->path() . "/autovalidarTransferenciaSecuencial",
                [
                    "codigo_origen" => $codigo_origen,
                    "referencia" => $data['referencia'],
                    "telefono" => $data['telefono'],
                    "banco_origen" => $data['banco_origen'],
                    "fecha_pago" => $data['fecha_pago'],
                    "monto" => $data['monto'],
                    "id_pedido" => $data['id_pedido'],
                    "cedula" => $data['cedula'] ?? null,
                ]
            );

            \Log::info("Respuesta de autovalidarTransferenciaCentral:", [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->ok()) {
                return $response->json();
            } else {
                return [
                    "estado" => false, 
                    "msj" => "Error de comunicación con central: HTTP " . $response->status()
                ];
            }
        } catch (\Exception $e) {
            \Log::error("Error en autovalidarTransferenciaCentral: " . $e->getMessage());
            return [
                "estado" => false, 
                "msj" => "Error de conexión con central: " . $e->getMessage()
            ];
        }
    }

    function createAnulacionPedidoAprobacion($data) {
        $codigo_origen = $this->getOrigen();
        $response = Http::post(
            $this->path() . "/createAnulacionPedidoAprobacion",
            [
                "codigo_origen" => $codigo_origen,
                "data" => $data, 
            ]
        );

        if ($response->ok()) {
            //Retorna respuesta solo si es Array
            return $response->json();
        }else{
            return $response;
        }
    }


    
    function sendEfec($lastid)
    {
        return cajas::where("id",">",$lastid)->get();
    }

    function sendCreditos() {
        $today = (new PedidosController)->today();
        $clientes =  clientes::selectRaw("*,(SELECT created_at FROM pago_pedidos WHERE id_pedido IN (SELECT id FROM pedidos WHERE id_cliente=clientes.id) AND tipo=4 AND created_at IS NOT NULL ORDER BY id desc LIMIT 1) as creacion , @credito := (SELECT COALESCE(sum(monto),0) FROM pago_pedidos WHERE id_pedido IN (SELECT id FROM pedidos WHERE id_cliente=clientes.id) AND tipo=4) as credito, @abono := (SELECT COALESCE(sum(monto),0) FROM pago_pedidos WHERE id_pedido IN (SELECT id FROM pedidos WHERE id_cliente=clientes.id) AND cuenta=0) as abono, (@abono-@credito) as saldo, @vence := (SELECT fecha_vence FROM pedidos WHERE id_cliente=clientes.id AND fecha_vence > $today ORDER BY pedidos.fecha_vence ASC LIMIT 1) as vence , (COALESCE(DATEDIFF(@vence,'$today 00:00:00'),0)) as dias")
        // ->where("saldo","<",0)
        ->having("saldo","<",0)
        ->orderBy("saldo","asc")
        ->get();

        return base64_encode(gzcompress(json_encode($clientes)));


    }

    function sendAllLotes() {
        ini_set('memory_limit', '4095M');

        $i = items_pedidos::whereNotNull("id_producto")->whereIn("id_pedido",pedidos::whereIn("id",pago_pedidos::where("tipo","<>",4)->select("id_pedido"))->select("id"))
        ->orderBy("id","desc")
        ->get(["id","id_pedido","cantidad","id_producto","created_at"]); 

        $pedidos = pedidos::whereIn("id",$i->pluck("id_pedido"))->get();
        $pagos = pago_pedidos::whereIn("id_pedido",$pedidos->pluck("id"))->get();


        //$movs = movimientosInventariounitario::all();
        $inventariofull = inventario::all();
        $vinculos = [];

        $id_last_movs = movimientosInventariounitario::orderBy("id","desc")->first();
        $id_last_items = items_pedidos::orderBy("id","desc")->first();

        $data =  base64_encode(gzcompress(json_encode([
            "movs" => [],
            "vinculos" => $vinculos,
            "items" => [
                "items" => $i,
                "pedidos" => $pedidos,
                "pagos" => $pagos,
            ],
            "inventariofull" => $inventariofull,
            "id_last_movs" => $id_last_movs->id,
            "id_last_items" => $id_last_items->id,

        ])));
        $codigo_origen = $this->getOrigen();

        $res = Http::post($this->path() . "/sendAllLotes", [
            "data"=>$data,
            "codigo_origen" => $codigo_origen,
        ]);


        if ($res->ok()) {
            return $res;
        }
        return $res;

    }


    function sendestadisticasVenta($id_last) {
        
        ini_set('memory_limit', '4095M');
        
        \Log::info("    [Estadísticas] Iniciando con id_last: $id_last");

        // Procesar datos en lotes para evitar max_allowed_packet, pero devolver estructura consolidada
        $batchSize = 500; // Ajustar según necesidades
        $allItems = collect();
        $allPedidos = collect();
        $allPagos = collect();
        
        // Obtener items en lotes
        \Log::info("    [Estadísticas] Construyendo query de items_pedidos...");
        $t1 = microtime(true);
        $itemsQuery = items_pedidos::where("id",">",$id_last)
        ->whereNotNull("id_producto")
        ->whereIn("id_pedido", function($query) {
            $query->select("id")
                ->from("pedidos")
                ->where(function($q) {
                    // Pedidos con pagos tipo <> 4
                    $q->whereIn("id", function($subQuery) {
                        $subQuery->select("id_pedido")
                            ->from("pago_pedidos")
                            ->where("tipo", "<>", 4);
                    })
                    // O pedidos sin ningún pago
                    ->orWhereNotIn("id", function($subQuery) {
                        $subQuery->select("id_pedido")
                            ->from("pago_pedidos");
                    });
                });
        })
        ->orderBy("id","desc");
        \Log::info("    [Estadísticas] Query construido en " . round(microtime(true) - $t1, 2) . "s");
            
        \Log::info("    [Estadísticas] Contando total de items...");
        $t2 = microtime(true);
        $totalItems = $itemsQuery->count();
        \Log::info("    [Estadísticas] Total items encontrados: $totalItems en " . round(microtime(true) - $t2, 2) . "s");
        
        // Si no hay items, devolver estructura vacía
        if ($totalItems == 0) {
            $data = [
                "items" => [],
                "pedidos" => [],
                "pagos" => [],
            ];
            return base64_encode(gzcompress(json_encode($data)));
        }
        
        $allPedidoIds = collect();
        
        // Procesar en lotes para evitar sobrecarga de memoria y DB
        \Log::info("    [Estadísticas] Procesando items en lotes de $batchSize...");
        $t3 = microtime(true);
        $totalBatches = ceil($totalItems / $batchSize);
        for ($offset = 0; $offset < $totalItems; $offset += $batchSize) {
            $batchNum = intval($offset / $batchSize) + 1;
            \Log::info("    [Estadísticas] Procesando lote $batchNum/$totalBatches de items...");
            
            $itemsBatch = $itemsQuery->skip($offset)->take($batchSize)
                ->get(["id","id_pedido","cantidad","id_producto","created_at",
                "descuento",
                "entregado",
                "condicion"
            ]);
            
            if ($itemsBatch->isEmpty()) {
                break;
            }
            
            // Acumular items
            $allItems = $allItems->merge($itemsBatch);
            
            // Acumular IDs de pedidos únicos
            $pedidoIds = $itemsBatch->pluck("id_pedido")->unique();
            $allPedidoIds = $allPedidoIds->merge($pedidoIds)->unique();
        }
        \Log::info("    [Estadísticas] Items procesados en " . round(microtime(true) - $t3, 2) . "s. Total pedidos únicos: " . $allPedidoIds->count());
        
        // Obtener todos los pedidos únicos (en lotes si es necesario)
        \Log::info("    [Estadísticas] Obteniendo pedidos...");
        $t4 = microtime(true);
        $pedidosBatchSize = 1000;
        $pedidoIdsArray = $allPedidoIds->toArray();
        
        for ($i = 0; $i < count($pedidoIdsArray); $i += $pedidosBatchSize) {
            $pedidoIdsBatch = array_slice($pedidoIdsArray, $i, $pedidosBatchSize);
            $pedidosBatch = pedidos::whereIn("id", $pedidoIdsBatch)->get();
            $allPedidos = $allPedidos->merge($pedidosBatch);
        }
        \Log::info("    [Estadísticas] Pedidos obtenidos en " . round(microtime(true) - $t4, 2) . "s. Total: " . $allPedidos->count());
        
        // Obtener todos los pagos únicos (en lotes si es necesario)
        \Log::info("    [Estadísticas] Obteniendo pagos...");
        $t5 = microtime(true);
        for ($i = 0; $i < count($pedidoIdsArray); $i += $pedidosBatchSize) {
            $pedidoIdsBatch = array_slice($pedidoIdsArray, $i, $pedidosBatchSize);
            $pagosBatch = pago_pedidos::whereIn("id_pedido", $pedidoIdsBatch)->get();
            $allPagos = $allPagos->merge($pagosBatch);
        }
        \Log::info("    [Estadísticas] Pagos obtenidos en " . round(microtime(true) - $t5, 2) . "s. Total: " . $allPagos->count());
        
        // Devolver estructura consolidada como se esperaba originalmente
        $data = [
            "items" => $allItems,
            "pedidos" => $allPedidos->unique('id'),
            "pagos" => $allPagos,
        ];

        return base64_encode(gzcompress(json_encode($data)));
    }

    function procesarEstadisticasVentaLotes($id_last_estadisticas) {
        try {
            $estadisticasBatches = $this->sendestadisticasVenta($id_last_estadisticas);
            
            // Si es un solo lote (string), devolver como antes
            if (is_string($estadisticasBatches)) {
                return $estadisticasBatches;
            }
            
            // Si son múltiples lotes, procesar uno por uno y devolver resumen
            $totalLotes = count($estadisticasBatches);
            $lotesEnviados = 0;
            
            foreach ($estadisticasBatches as $index => $batch) {
                // Aquí podrías enviar cada lote por separado si fuera necesario
                // Por ahora, solo contamos los lotes procesados
                $lotesEnviados++;
            }
            
            // Devolver resumen del procesamiento
            return base64_encode(gzcompress(json_encode([
                "resumen_lotes" => [
                    "total_lotes" => $totalLotes,
                    "lotes_procesados" => $lotesEnviados,
                    "status" => "success"
                ]
            ])));
            
        } catch (\Exception $e) {
            return base64_encode(gzcompress(json_encode([
                "error" => "Error procesando estadísticas: " . $e->getMessage()
            ])));
        }
    }

    function sendmovsinv($id_last) {
       
        // Enviar datos en lotes para evitar max_allowed_packet
        $batchSize = 1000; // Ajustar según necesidades
        $allBatches = [];
        
        $query = movimientosinventariounitario::where("id",">",$id_last)
            ->orderBy("id","desc");
            
        $totalItems = $query->count();
        
        for ($offset = 0; $offset < $totalItems; $offset += $batchSize) {
            $batch = $query->skip($offset)->take($batchSize)->get();
            
            if ($batch->isEmpty()) {
                break;
            }
            
            $batchData = [
                "data" => $batch,
                "batch_info" => [
                    "batch_number" => intval($offset / $batchSize) + 1,
                    "total_batches" => ceil($totalItems / $batchSize),
                    "is_last_batch" => ($offset + $batchSize) >= $totalItems
                ]
            ];
            
            $allBatches[] = base64_encode(gzcompress(json_encode($batchData)));
        }
        
        // Si no hay datos, devolver estructura vacía
        if (empty($allBatches)) {
            $emptyData = [
                "data" => [],
                "batch_info" => [
                    "batch_number" => 1,
                    "total_batches" => 1,
                    "is_last_batch" => true
                ]
            ];
            return base64_encode(gzcompress(json_encode($emptyData)));
        }
        
        // Devolver todos los lotes
        return $allBatches;
    }
    function sendAllMovs()  {
        ini_set('memory_limit', '4095M');

        try {
            $codigo_origen = $this->getOrigen();
                
            $getLast = Http::get($this->path() . "/getLast", [
                "codigo_origen" => $codigo_origen,
            ]);
    
            if ($getLast->ok()) {
                $getLast = $getLast->json();
                if ($getLast==null) {
                    $id_last_movs = 0;
                }else{
                    $id_last_movs = $getLast["id_last_movs"]?$getLast["id_last_movs"]:0;
                }
                
                $movsBatches = $this->sendmovsinv($id_last_movs);
                
                // Si es un solo lote (estructura anterior), procesar como antes
                if (is_string($movsBatches)) {
                    $data = [
                        "movsinventario" => $movsBatches,
                        "codigo_origen" => $codigo_origen,
                    ];
                    $setAll = Http::post($this->path() . "/sendAllMovs", $data);
                } else {
                    // Si son múltiples lotes, enviar uno por uno
                    $results = [];
                    foreach ($movsBatches as $index => $batch) {
                        $data = [
                            "movsinventario" => $batch,
                            "codigo_origen" => $codigo_origen,
                            "batch_info" => [
                                "current_batch" => $index + 1,
                                "total_batches" => count($movsBatches)
                            ]
                        ];
                        $setAll = Http::post($this->path() . "/sendAllMovs", $data);
                        
                        if (!$setAll->ok()) {
                            return "ERROR en lote " . ($index + 1) . ": " . $setAll;
                        }
                        $results[] = $setAll->json();
                    }
                    return $results;
                }
                
                if (!$setAll->json()) {
                    return $setAll;
                }
                if ($setAll->ok()) {
                    return $setAll->json();
                }else{
                    return "ERROR: ".$setAll;
                }
                return $setAll;
                
            }
            return $getLast;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    
    }
    

    function sendAllTest() {
        return view('sync.dashboard');
    }

    function sendAllTestOnlyInventario($idsSuccess) {
        ini_set('memory_limit', '4095M');

        try {
            $codigo_origen = $this->getOrigen();
            $data = inventario::all();

            $requestData = [
                "sendInventarioCt" => base64_encode(gzcompress(json_encode($data))),
                "codigo_origen" => $codigo_origen,
            ];

            $response = Http::post($this->path() . "/setAllInventario", $requestData);

            if ($response->ok()) {
                if ($response->json()) {
                    return $response->json();
                }
                return $response;
            }

            \Log::error('Error al enviar inventario a central', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return response()->json([
                'error' => 'Error al enviar inventario',
                'message' => $response->body()
            ], 500);
        } catch (\Exception $e) {
            return $e->getMessage();
        }
            
    }

    function sendAll($correo) {
        return $this->sendAllDirect($correo, false);
    }

    ////////////////////

    function getNomina()
    {
        try {
            $codigo_origen = $this->getOrigen();

            $response = Http::post($this->path() . "/getNomina", [
                "codigo_origen" => $codigo_origen,
            ]);

            if ($response->ok()) {
                //Retorna respuesta solo si es Array
                return $response->json();
            }
            return $response;
        } catch (\Exception $e) {
            return Response::json(["msj" => "Error: " . $e->getMessage(), "estado" => false]);
        }
    }

    function getAlquileres()
    {
        try {
            $codigo_origen = $this->getOrigen();

            $response = Http::post($this->path() . "/getAlquileresSucursal", [
                "codigo_origen" => $codigo_origen,
            ]);

            if ($response->ok()) {
                //Retorna respuesta solo si es Array
                return $response->json();
            }
        } catch (\Exception $e) {
            return Response::json(["msj" => "Error: " . $e->getMessage(), "estado" => false]);
        }
    }
    

    function sendEstadisticas()
    {
        $data = [
            "fechaQEstaInve" => "",
            "fechaFromEstaInve" => "",
            "fechaToEstaInve" => "",
            "orderByEstaInv" => "DESC",
            "orderByColumEstaInv" => "cantidadtotal",
            "categoriaEstaInve" => "",
        ];
        $codigo_origen = $this->getOrigen();


        try {
            $response = Http::post($this->path() . "/setEstadisticas", [
                "codigo_origen" => $codigo_origen,
                "estadisticas" => (new InventarioController)->getEstadisticasFun($data),


            ]);

            if ($response->ok()) {
                //Retorna respuesta solo si es Array
                if ($response->json()) {
                    return Response::json([
                        "msj" => $response->json(),
                        "estado" => true,
                        "json" => true
                    ]);
                } else {
                    return Response::json([
                        "msj" => $response->body(),
                        "estado" => true,
                    ]);
                }
            } else {
                return Response::json([
                    "msj" => $response,
                    "estado" => false,
                ]);
            }
        } catch (\Exception $e) {
            return Response::json(["msj" => "Error: " . $e->getMessage(), "estado" => false]);
        }
    }











    ////////////////////////////////////////////////77

    

    public function setNuevaTareaCentral(Request $req)
    {
        $type = $req->type;
        $response = Http::post($this->path() . "/setNuevaTareaCentral", ["type" => $type]);

        if ($response->ok()) {
            $res = $response->json();
            return $res;
        } else {
            return "Error: " . $response->body();

        }
    }
    public function index()
    {
        return view("central.index");
    }
    // public function update($new_version)
    // {}
    //     $runproduction = "npm run production";        
    //     // $phpArtisan = "php artisan key:generate && php artisan view:cache && php artisan route:cache && php artisan config:cache";

    //     $pull = shell_exec("cd C:\sinapsisfacturacion && git stash && git pull https://github.com/alvaritojose2712/sinapsisfacturacion.git && composer install --optimize-autoloader --no-dev");

    //     if (!str_contains($pull, "Already up to date")) {
    //         echo "Éxito al Pull. Building...";
    //         exec("cd C:\sinapsisfacturacion && ".$runproduction." && ".$phpArtisan,$output, $retval);

    //         if (!$retval) {
    //             echo "Éxito al Build. Actualizado...";

    //             sucursal::update(["app_version",$new_version]);
    //         }
    //     }else{
    //         echo "Pull al día. No requiere actualizar <br>";
    //         echo "<pre>$pull</pre>";

    //     }
    // }


    //req

    public function getip()
    {
        return getHostByName(getHostName());
    }
    public function getmastermachine()
    {
        return ["192.168.0.103:8001", "192.168.0.102:8001", "127.0.0.1:8001"];
    }
    public function changeExportStatus($pathcentral, $id)
    {
        $response = Http::post($this->path() . "/changeExtraidoEstadoPed", ["id" => $id]);
    }
    public function setnewtasainsucursal(Request $req)
    {
        $tipo = $req->tipo;
        $valor = $req->valor;
        $id_sucursal = $req->id_sucursal;



        $response = Http::post($this->path() . "/setnewtasainsucursal", [
            "tipo" => $tipo,
            "valor" => $valor,
            "id_sucursal" => $id_sucursal,
        ]);
        if ($response->ok()) {
            if ($response->json()) {
                return $response->json();
            } else {
                return $response;
            }
        } else {
            return "Error: " . $response->body();
        }
    }
    public function changeEstatusProductoProceced($ids, $id_sucursal)
    {
        $response = Http::post($this->path() . "/changeEstatusProductoProceced", [
            "ids" => $ids,
            "id_sucursal" => $id_sucursal,
        ]);

        if ($response->ok()) {
            if ($response->json()) {

                if ($response->json()) {
                    return $response->json();
                } else {
                    return $response;
                }
            } else {
                return $response;
            }
        } else {

            return "Error de Local Centro de Acopio: " . $response->body();
        }
    }
    public function setCambiosInventarioSucursal(Request $req)
    {
        $response = Http::post($this->path() . "/setCambiosInventarioSucursal", [
            "productos" => $req->productos,
            "sucursal" => $req->sucursal,
        ]);

        if ($response->ok()) {
            if ($response->json()) {
                return $response->json();
            } else {
                return $response;
            }
        } else {

            return "Error de Local Centro de Acopio: " . $response->body();
        }
    }

    public function getInventarioFromSucursal(Request $req)
    {
        $sucursal = $this->getOrigen();
        $response = Http::post($this->path() . "/getInventarioFromSucursal", [
            "sucursal" => $sucursal,
        ]);

        if ($response->ok()) {
            $res = $response->json();
            if ($res) {
                if (isset($res["estado"])) {
                    return $res;
                } else {
                    $arr_convert = [];
                    foreach ($res as $key => $e) {
                        $find = inventario::with(["categoria", "proveedor"])->where("id", $e["id_pro_sucursal_fixed"])->first();
                        if ($find) {
                            $find["type"] = "original";
                            array_push($arr_convert, $find);

                        }
                        $e["type"] = "replace";
                        array_push($arr_convert, $e);
                    }
                    return $arr_convert;
                }
            } else {
                return $response;
            }
        } else {

            return "Error de Local Centro de Acopio: " . $response->body();
        }

    }
    //res

    /* public function respedidos(Request $req)
    {
        

        if ($ped) {
            return Response::json([
                "msj"=>"Tenemos algo :D",
                "pedido"=>$ped,
                "estado"=>true
            ]);
        }else{
            return Response::json([
                "msj"=>"No hay pedidos pendientes :(",
                "estado"=>false
            ]);
        }
    } */
    public function resinventario(Request $req)
    {
        //return "exportinventario";
        return [
            "inventario" => inventario::all(),
            "categorias" => categorias::all(),
            "proveedores" => proveedores::all(),
        ];
    }




    public function updateApp()
    {
        try {

            $sucursal = $this->getOrigen();
            $actually_version = $sucursal["app_version"];

            $getVersion = Http::get($this->path . "/getVersionRemote");

            if ($getVersion->ok()) {

                $server_version = $getVersion->json();
                if ($actually_version != $server_version) {
                    $this->update($server_version);
                } else if ($actually_version == $server_version) {
                    return "Sistema al día :)";
                } else {
                    return "Upps.. :(" . "V-Actual=" . $actually_version . " V-Remote" . $server_version;

                }
                ;
            }
        } catch (\Exception $e) {
            return "Error: " . $e->getMessage();
        }

    }


    public function getInventarioCentral()
    {
        try {
            $sucursal = $this->getOrigen();
            $response = Http::post($this->path . '/getInventario', [
                "sucursal_code" => $sucursal->codigo,

            ]);
        } catch (\Exception $e) {
            return Response::json(["estado" => false, "msj" => "Error de sucursal: " . $e->getMessage()]);

        }

    }


    public function setFacturasCentral()
    {
        try {
            $sucursal = $this->getOrigen();
            $facturas = factura::with([
                "proveedor",
                "items" => function ($q) {
                    $q->with("producto");
                }
            ])
                ->where("push", 0)->get();


            if (!$facturas->count()) {
                return Response::json(["msj" => "Nada que enviar", "estado" => false]);
            }


            $response = Http::post($this->path . '/setConfirmFacturas', [
                "sucursal_code" => $sucursal->codigo,
                "facturas" => $facturas
            ]);

            //ids_ok => id de movimiento 

            if ($response->ok()) {
                $res = $response->json();
                if (isset($res["estado"])) {
                    if ($res["estado"]) {
                        factura::where("push", 0)->update(["push" => 1]);
                        return $res["msj"];
                    }

                } else {

                    return $response;
                }
            } else {
                return $response->body();
            }
        } catch (\Exception $e) {
            return Response::json(["estado" => false, "msj" => "Error de sucursal: " . $e->getMessage()]);

        }
    }
    public function setCentralData()
    {
        try {
            $sucursal = $this->getOrigen();
            $fallas = fallas::all();

            if (!$fallas->count()) {
                return Response::json(["msj" => "Nada que enviar", "estado" => false]);
            }


            $response = Http::post($this->path . '/setFalla', [
                "sucursal_code" => $sucursal->codigo,
                "fallas" => $fallas
            ]);

            //ids_ok => id de productos 

            if ($response->ok()) {
                $res = $response->json();
                // code...

                if ($res["estado"]) {

                    return $res["msj"];
                }
            } else {

                return $response;
            }
        } catch (\Exception $e) {
            return Response::json(["estado" => false, "msj" => "Error de sucursal: " . $e->getMessage()]);

        }


    }

    
    public function updatetasasfromCentral()
    {
        try {
            $sucursal = $this->getOrigen();

            $response = Http::post($this->path() . '/getMonedaSucursal', ["codigo" => $sucursal->codigo]);

            if ($response->ok()) {
                $res = $response->json();
                foreach ($res as $key => $e) {
                    moneda::updateOrCreate(["tipo" => $e["tipo"]], [
                        "tipo" => $e["tipo"],
                        "valor" => $e["valor"]
                    ]);
                }

                Cache::forget('bs');
                Cache::forget('cop');

            } else {
                return "Error: " . $response->body();

            }

        } catch (\Exception $e) {
            return Response::json(["estado" => false, "msj" => "Error de sucursal: " . $e->getMessage()]);
        }
    }

    private function generateReport($type, $data) {
        try {
            $reportPath = storage_path('app/reports');
            \Log::info('Generando reporte en: ' . $reportPath);
            
            if (!file_exists($reportPath)) {
                \Log::info('Creando directorio de reportes');
                if (!mkdir($reportPath, 0755, true)) {
                    \Log::error('No se pudo crear el directorio de reportes');
                    return false;
                }
            }

            $filename = "global_report.html";
            $filepath = "{$reportPath}/{$filename}";
            
            // Si el archivo no existe, crear el HTML inicial
            if (!file_exists($filepath)) {
                $html = "<!DOCTYPE html>
                <html>
                <head>
                    <title>Global Report</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        table { border-collapse: collapse; width: 100%; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f2f2f2; }
                        tr:nth-child(even) { background-color: #f9f9f9; }
                        .section { margin-bottom: 30px; }
                        .section-title { color: #333; margin-bottom: 10px; }
                    </style>
                </head>
                <body>
                    <h1>Global Report</h1>";
            } else {
                // Leer el contenido existente
                $html = file_get_contents($filepath);
                // Remover el cierre del body y html
                $html = str_replace("</body></html>", "", $html);
            }

            // Agregar nueva sección
            $html .= "<div class='section'>";
            $html .= "<h2 class='section-title'>{$type} - " . date('Y-m-d H:i:s') . "</h2>";

            if ($type === 'product_replacement') {
                $html .= "<table>
                    <tr>
                        <th>Producto Eliminado</th>
                        <th>Producto Reemplazo</th>
                        <th>Cantidad Anterior</th>
                        <th>Cantidad Nueva</th>
                        <th>Fecha</th>
                    </tr>";
                foreach ($data as $item) {
                    $productoEliminado = inventario::find($item['deleted_id']);
                    $productoReemplazo = inventario::find($item['replacement_id']);
                    
                    $html .= "<tr>
                        <td>
                            ID: {$item['deleted_id']}<br>
                            Descripción: {$productoEliminado->descripcion}<br>
                            Código: {$productoEliminado->codigo_barras}<br>
                            Precio: {$productoEliminado->precio}
                        </td>
                        <td>
                            ID: {$item['replacement_id']}<br>
                            Descripción: {$productoReemplazo->descripcion}<br>
                            Código: {$productoReemplazo->codigo_barras}<br>
                            Precio: {$productoReemplazo->precio}
                        </td>
                        <td>{$item['old_quantity']}</td>
                        <td>{$item['new_quantity']}</td>
                        <td>{$item['date']}</td>
                    </tr>";
                }
            } else if ($type === 'product_changes' || $type === 'inventory') {
                $html .= "<table>
                    <tr>
                        <th>ID Producto</th>
                        <th>Descripción</th>
                        <th>Código Barras</th>
                        <th>Código Proveedor</th>
                        <th>Campo Modificado</th>
                        <th>Valor Anterior</th>
                        <th>Valor Nuevo</th>
                        <th>Fecha</th>
                    </tr>";
                foreach ($data as $item) {
                    $html .= "<tr>
                        <td>{$item['product_id']}</td>
                        <td>" . ($item['descripcion'] ?? 'N/A') . "</td>
                        <td>" . ($item['codigo_barras'] ?? 'N/A') . "</td>
                        <td>" . ($item['codigo_proveedor'] ?? 'N/A') . "</td>
                        <td>{$item['field']}</td>
                        <td>{$item['old_value']}</td>
                        <td>{$item['new_value']}</td>
                        <td>" . ($item['date'] ?? date('Y-m-d H:i:s')) . "</td>
                    </tr>";
                }
            } else if ($type === 'product_movement') {
                $producto = inventario::find($data['id_producto']);
                $html .= "<table>
                    <tr>
                        <th>Producto</th>
                        <th>Cantidad Anterior</th>
                        <th>Cantidad Nueva</th>
                        <th>Tipo</th>
                        <th>Descripción</th>
                        <th>Fecha</th>
                    </tr>";
                $html .= "<tr>
                    <td>
                        ID: {$data['id_producto']}<br>
                        Descripción: {$producto->descripcion}<br>
                        Código: {$producto->codigo_barras}<br>
                        Precio: {$producto->precio}
                    </td>
                    <td>{$data['cantidad_anterior']}</td>
                    <td>{$data['cantidad_nueva']}</td>
                    <td>{$data['tipo']}</td>
                    <td>{$data['descripcion']}</td>
                    <td>" . date('Y-m-d H:i:s') . "</td>
                </tr>";
            } else if ($type === 'tasks') {
                $html .= "<table>
                    <tr>
                        <th>ID Tarea</th>
                        <th>Producto Original</th>
                        <th>Producto Reemplazo</th>
                        <th>Estado</th>
                        <th>Detalles</th>
                    </tr>";
                foreach ($data as $item) {
                    $html .= "<tr>
                        <td>{$item['id']}</td>
                        <td>{$item['original_product']}</td>
                        <td>{$item['replacement_product']}</td>
                        <td>{$item['status']}</td>
                        <td>{$item['details']}</td>
                    </tr>";
                }
            }

            $html .= "</table></div>";
            $html .= "</body></html>";
            
            if (file_put_contents($filepath, $html) === false) {
                \Log::error('No se pudo escribir el archivo de reporte');
                return false;
            }
            
            \Log::info('Reporte actualizado exitosamente: ' . $filepath);
            return $filepath;
        } catch (\Exception $e) {
            \Log::error('Error al generar reporte: ' . $e->getMessage());
            return false;
        }
    }

    public function getReports() {
        $reportsPath = storage_path('app/reports');
        if (!file_exists($reportsPath)) {
            return [];
        }

        $filepath = "{$reportsPath}/global_report.html";
        if (file_exists($filepath)) {
            return [[
                'name' => 'global_report.html',
                'path' => $filepath,
                'date' => date('Y-m-d H:i:s', filemtime($filepath))
            ]];
        }
        return [];
    }

    public function viewReport($filename = null) {
        $reportPath = storage_path('app/reports');
        $filepath = "{$reportPath}/global_report.html";
        
        // Crear directorio si no existe
        if (!file_exists($reportPath)) {
            mkdir($reportPath, 0755, true);
        }
        
        // Crear archivo inicial si no existe
        if (!file_exists($filepath)) {
            $html = "<!DOCTYPE html>
            <html>
            <head>
                <title>Global Report</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    table { border-collapse: collapse; width: 100%; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background-color: #f2f2f2; }
                    tr:nth-child(even) { background-color: #f9f9f9; }
                    .section { margin-bottom: 30px; }
                    .section-title { color: #333; margin-bottom: 10px; }
                </style>
            </head>
            <body>
                <h1>Global Report</h1>
                <p>No hay movimientos registrados aún.</p>
            </body>
            </html>";
            
            file_put_contents($filepath, $html);
        }
        
        if (file_exists($filepath)) {
            return response()->file($filepath);
        }
        
        return response()->json(['error' => 'No se pudo crear el reporte'], 500);
    }

    // ================ FUNCIONES PARA SISTEMA DE GARANTÍAS ================

    /**
     * Envía una nueva solicitud de garantía a arabitocentral
     */
    public function sendGarantiaSolicitudCentral($data) {
        try {
            $codigo_origen = $this->getOrigen();
            
            // Determinar si usar la nueva ruta para solicitudes con métodos de devolución
            $ruta = isset($data['incluye_metodos_pago']) && $data['incluye_metodos_pago'] 
                ? "/api/garantias/solicitudes-con-metodos" 
                : "/api/garantias";
            
            $payload = [
                "codigo_origen" => $codigo_origen,
            ];
            
            // Agregar datos según el tipo de solicitud
            if (isset($data['incluye_metodos_pago']) && $data['incluye_metodos_pago']) {
                // Nueva estructura para solicitudes con métodos - enviar datos directamente
                $payload = array_merge($payload, $data);
            } else {
                // Estructura antigua - enviar en campo solicitud
                $payload["solicitud"] = $data;
            }
            
            $response = Http::timeout(60)->post($this->path() . $ruta, $payload);

            if ($response->ok()) {
                return $response->json();
            } else {
                \Log::error('Error al enviar solicitud de garantía: ' . $response->body());
                return [
                    'success' => false,
                    'error' => 'Error de comunicación con arabitocentral: ' . $response->status()
                ];
            }
        } catch (\Exception $e) {
            \Log::error('Excepción al enviar solicitud de garantía: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error de conexión: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene las garantías aprobadas pendientes de ejecución
     */
    public function getGarantiasApprovedFromCentral() {
        try {
            $codigo_origen = $this->getOrigen();
            
            $response = Http::timeout(30)->get($this->path() . "/api/garantias/solicitudes", [
                "codigo_origen" => $codigo_origen,
                "estatus" => "APROBADA"
            ]);

            if ($response->ok()) {
                return $response->json();
            } else {
                \Log::error('Error al obtener garantías aprobadas: ' . $response->body());
                return [
                    'success' => false,
                    'error' => 'Error de comunicación con arabitocentral: ' . $response->status()
                ];
            }
        } catch (\Exception $e) {
            \Log::error('Excepción al obtener garantías aprobadas: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error de conexión: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene una garantía específica por ID
     */
    public function getGarantiaFromCentral($id) {
        try {
            $codigo_origen = $this->getOrigen();
            
            $response = Http::timeout(30)->get($this->path() . "/api/garantias/solicitudes/{$id}", [
                "codigo_origen" => $codigo_origen
            ]);

            if ($response->ok()) {
                return $response->json();
            } else {
                \Log::error('Error al obtener garantía por ID: ' . $response->body());
                return [
                    'success' => false,
                    'error' => 'Error de comunicación con arabitocentral: ' . $response->status()
                ];
            }
        } catch (\Exception $e) {
            \Log::error('Excepción al obtener garantía por ID: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error de conexión: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Marca una garantía como finalizada en arabitocentral
     */
    public function markGarantiaAsCompletedCentral($id, $detalles_ejecucion = null) {
        try {
            $codigo_origen = $this->getOrigen();
            
            $response = Http::timeout(30)->post($this->path() . "/api/garantias/solicitudes/{$id}/finalizar", [
                "codigo_origen" => $codigo_origen,
                "detalles_ejecucion" => $detalles_ejecucion
            ]);

            if ($response->ok()) {
                return $response->json();
            } else {
                \Log::error('Error al marcar garantía como completada: ' . $response->body());
                return [
                    'success' => false,
                    'error' => 'Error de comunicación con arabitocentral: ' . $response->status()
                ];
            }
        } catch (\Exception $e) {
            \Log::error('Excepción al marcar garantía como completada: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error de conexión: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene estadísticas de garantías para la sucursal
     */
    public function getGarantiaStatsFromCentral() {
        try {
            $codigo_origen = $this->getOrigen();
            
            $response = Http::timeout(30)->get($this->path() . "/api/garantias/dashboard/estadisticas", [
                "codigo_origen" => $codigo_origen
            ]);

            if ($response->ok()) {
                return $response->json();
            } else {
                \Log::error('Error al obtener estadísticas de garantías: ' . $response->body());
                return [
                    'success' => false,
                    'error' => 'Error de comunicación con arabitocentral: ' . $response->status()
                ];
            }
        } catch (\Exception $e) {
            \Log::error('Excepción al obtener estadísticas de garantías: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error de conexión: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Busca solicitudes de garantía por ID de pedido en arabito central
     */
    public function buscarSolicitudesGarantiaPorPedido($id_pedido) {
        try {
            $codigo_origen = $this->getOrigen();
            
            $response = Http::timeout(30)->get($this->path() . "/api/garantias/solicitudes", [
                "codigo_origen" => $codigo_origen,
                "id_pedido_insucursal" => $id_pedido
            ]);

            if ($response->ok()) {
                $data = $response->json();
                \Log::info("Solicitudes de garantía encontradas para pedido {$id_pedido}", [
                    'total' => count($data['data'] ?? []),
                    'solicitudes' => $data['data'] ?? []
                ]);
                return $data;
            } else {
                \Log::error('Error al buscar solicitudes de garantía por pedido: ' . $response->body());
                return [
                    'success' => false,
                    'error' => 'Error de comunicación con arabitocentral: ' . $response->status()
                ];
            }
        } catch (\Exception $e) {
            \Log::error('Excepción al buscar solicitudes de garantía por pedido: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error de conexión: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Verifica el estado de conectividad con arabitocentral
     */
    public function checkCentralConnection() {
        try {
            $response = Http::timeout(10)->get($this->path() . "/api/health");
            return $response->ok();
        } catch (\Exception $e) {
            \Log::error('Error de conectividad con arabitocentral: ' . $e->getMessage());
            return false;
        }
    }

    // ================ FUNCIONES PARA GESTIÓN DE RESPONSABLES ================

    /**
     * Buscar responsables existentes en arabitocentral
     */
    public function searchResponsablesCentral($data) {
        try {
            $codigo_origen = $this->getOrigen();
            
            $response = Http::timeout(30)->post($this->path() . "/api/responsables/search", [
                "codigo_origen" => $codigo_origen,
                "tipo" => $data['tipo'],
                "termino" => $data['termino'],
                "limit" => $data['limit'] ?? 10
            ]);

            if ($response->ok()) {
                return $response->json();
            } else {
                \Log::error('Error al buscar responsables en central: ' . $response->body());
                return [
                    'success' => false,
                    'data' => [],
                    'error' => 'Error de comunicación con arabitocentral: ' . $response->status()
                ];
            }
        } catch (\Exception $e) {
            \Log::error('Excepción al buscar responsables en central: ' . $e->getMessage());
            return [
                'success' => false,
                'data' => [],
                'error' => 'Error de conexión: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Guardar nuevo responsable en arabitocentral
     */
    public function saveResponsableCentral($data) {
        try {
            $codigo_origen = $this->getOrigen();
            
            $response = Http::timeout(30)->post($this->path() . "/api/responsables", [
                "codigo_origen" => $codigo_origen,
                "tipo" => $data['tipo'],
                "nombre" => $data['nombre'],
                "apellido" => $data['apellido'],
                "cedula" => $data['cedula'],
                "telefono" => $data['telefono'] ?? '',
                "correo" => $data['correo'] ?? '',
                "direccion" => $data['direccion'] ?? ''
            ]);
            if ($response->ok()) {
                return $response->json();
            } else {
                \Log::error('Error al guardar responsable en central: ' . $response->body());
                return [
                    'success' => false,
                    'data' => null,
                    'error' => 'Error de comunicación con arabitocentral: ' . $response->status()
                ];
            }
        } catch (\Exception $e) {
            \Log::error('Excepción al guardar responsable en central: ' . $e->getMessage());
            return [
                'success' => false,
                'data' => null,
                'error' => 'Error de conexión: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener responsable por ID desde arabitocentral
     */
    public function getResponsableCentral($id) {
        try {
            $codigo_origen = $this->getOrigen();
            
            $response = Http::timeout(30)->get($this->path() . "/api/responsables/{$id}", [
                "codigo_origen" => $codigo_origen
            ]);

            if ($response->ok()) {
                return $response->json();
            } else {
                \Log::error('Error al obtener responsable por ID desde central: ' . $response->body());
                return [
                    'success' => false,
                    'data' => null,
                    'error' => 'Error de comunicación con arabitocentral: ' . $response->status()
                ];
            }
        } catch (\Exception $e) {
            \Log::error('Excepción al obtener responsable por ID desde central: ' . $e->getMessage());
            return [
                'success' => false,
                'data' => null,
                'error' => 'Error de conexión: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener responsables por tipo desde arabitocentral
     */
    public function getResponsablesTipoCentral($tipo) {
        try {
            $codigo_origen = $this->getOrigen();
            
            $response = Http::timeout(30)->get($this->path() . "/api/responsables/tipo/{$tipo}", [
                "codigo_origen" => $codigo_origen
            ]);

            if ($response->ok()) {
                return $response->json();
            } else {
                \Log::error('Error al obtener responsables por tipo desde central: ' . $response->body());
                return [
                    'success' => false,
                    'data' => [],
                    'error' => 'Error de comunicación con arabitocentral: ' . $response->status()
                ];
            }
        } catch (\Exception $e) {
            \Log::error('Excepción al obtener responsables por tipo desde central: ' . $e->getMessage());
            return [
                'success' => false,
                'data' => [],
                'error' => 'Error de conexión: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Reversar garantía en arabitocentral cuando se anula un pedido
     */
    public function reversarGarantiaCentral($data) {
        try {
            $codigo_origen = $this->getOrigen();
            
            $response = Http::timeout(60)->post($this->path() . "/api/garantias/reversar", [
                "codigo_origen" => $codigo_origen,
                "solicitud_id" => $data['solicitud_id'],
                "motivo_reversion" => $data['motivo_reversion'],
                "detalles_reversion" => $data['detalles_reversion'],
                "fecha_reversion" => $data['fecha_reversion'],
                "usuario_reversion" => $data['usuario_reversion']
            ]);

            if ($response->ok()) {
                return $response->json();
            } else {
                \Log::error('Error al reversar garantía en central: ' . $response->body());
                return [
                    'success' => false,
                    'error' => 'Error de comunicación con arabitocentral: ' . $response->status()
                ];
            }
        } catch (\Exception $e) {
            \Log::error('Excepción al reversar garantía en central: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error de conexión: ' . $e->getMessage()
            ];
        }
    }

    // =================== MÉTODOS PARA INVENTARIO DE GARANTÍAS ===================

    /**
     * Obtener inventario de garantías desde central
     */
    public function getInventarioGarantiasCentral($data) {
        try {
            $response = Http::get($this->path() . "/api/inventario-garantias", [
                'sucursal_codigo' => $this->getOrigen(),
                'tipo_inventario' => $data['tipo_inventario'] ?? null,
                'producto_search' => $data['producto_search'] ?? null,
                'page' => $data['page'] ?? 1,
                'per_page' => $data['per_page'] ?? 50
            ]);
            
            if ($response->ok()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Error al obtener inventario de garantías',
                'error' => $response->body()
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error de conexión con central',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Buscar productos en inventario de garantías
     */
    public function searchInventarioGarantias($data) {
        try {
            $response = Http::post($this->path() . "/api/inventario-garantias/search", [
                'sucursal_codigo' => $this->getOrigen(),
                'search_term' => $data['search_term'],
                'search_type' => $data['search_type'] ?? 'codigo_barras', // codigo_barras, descripcion, codigo_proveedor
                'tipo_inventario' => $data['tipo_inventario'] ?? null
            ]);
            
            if ($response->ok()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Error al buscar productos de garantía',
                'error' => $response->body()
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error de conexión con central',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Transferir producto de garantía entre sucursales
     */
    public function transferirProductoGarantiaSucursal($data) {
        try {
            $response = Http::post($this->path() . "/api/inventario-garantias/transferir-sucursal", [
                'producto_id' => $data['producto_id'],
                'sucursal_origen' => $this->getOrigen(),
                'sucursal_destino' => $data['sucursal_destino'],
                'cantidad' => $data['cantidad'],
                'tipo_inventario' => $data['tipo_inventario'],
                'motivo' => $data['motivo'] ?? 'Transferencia entre sucursales',
                'solicitado_por' => $data['solicitado_por'] ?? null
            ]);
            
            if ($response->ok()) {
                return [
                    'success' => true,
                    'message' => 'Solicitud de transferencia enviada para aprobación',
                    'data' => $response->json()
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Error al solicitar transferencia',
                'error' => $response->body()
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error de conexión con central',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Transferir producto de garantía a venta normal (solo desde central)
     */
    public function transferirGarantiaVentaNormal($data) {
        try {
            $response = Http::post($this->path() . "/api/inventario-garantias/transferir-venta", [
                'producto_id' => $data['producto_id'],
                'sucursal_destino' => $data['sucursal_destino'],
                'cantidad' => $data['cantidad'],
                'tipo_inventario' => $data['tipo_inventario'],
                'autorizado_por' => $data['autorizado_por'] ?? null,
                'observaciones' => $data['observaciones'] ?? null
            ]);
            
            if ($response->ok()) {
                return [
                    'success' => true,
                    'message' => 'Producto transferido a inventario de venta',
                    'data' => $response->json()
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Error al transferir a venta',
                'error' => $response->body()
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error de conexión con central',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener tipos de inventario de garantía disponibles
     */
    public function getTiposInventarioGarantia() {
        try {
            $response = Http::get($this->path() . "/api/inventario-garantias/tipos");
            
            if ($response->ok()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Error al obtener tipos de inventario',
                'error' => $response->body()
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error de conexión con central',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener estadísticas de inventario de garantías
     */
    public function getEstadisticasInventarioGarantias($data) {
        try {
            $response = Http::get($this->path() . "/api/inventario-garantias/estadisticas", [
                'sucursal_codigo' => $this->getOrigen(),
                'fecha_desde' => $data['fecha_desde'] ?? null,
                'fecha_hasta' => $data['fecha_hasta'] ?? null
            ]);
            
            if ($response->ok()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Error al obtener estadísticas',
                'error' => $response->body()
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error de conexión con central',
                'error' => $e->getMessage()
            ];
        }
    }

    // =================== FIN MÉTODOS INVENTARIO DE GARANTÍAS ===================

    // =================== MÉTODOS SOLICITUDES DE DESCUENTOS ===================

    /**
     * Verificar si existe una solicitud de descuento para un pedido
     */
    public function verificarSolicitudDescuento($data) {
        try {
            $codigo_origen = $this->getOrigen();
            $response = Http::post($this->path() . "/api/solicitudes-descuentos/verificar", [
                "codigo_origen" => $codigo_origen,
                "id_sucursal" => $data['id_sucursal'],
                "id_pedido" => $data['id_pedido'],
                "tipo_descuento" => $data['tipo_descuento']
            ]);
            
            if ($response->ok()) {
                $resretur = $response->json();
                if ($resretur) {
                    return $resretur;
                } 
            }
            return $response;

        } catch (\Exception $e) {
            return Response::json(["msj" => "Error: " . $e->getMessage(), "estado" => false]);
        } 
    }

    /**
     * Crear una nueva solicitud de descuento
     */
    public function crearSolicitudDescuento($data) {
        try {
            $codigo_origen = $this->getOrigen();
            $response = Http::post($this->path() . "/api/solicitudes-descuentos", [
                "codigo_origen" => $codigo_origen,
                "id_sucursal" => $data['id_sucursal'],
                "id_pedido" => $data['id_pedido'],
                "fecha" => $data['fecha'],
                "monto_bruto" => $data['monto_bruto'],
                "monto_con_descuento" => $data['monto_con_descuento'],
                "monto_descuento" => $data['monto_descuento'],
                "porcentaje_descuento" => $data['porcentaje_descuento'],
                "id_cliente" => $data['id_cliente'],
                "data_cliente" => clientes::where("id",$data['id_cliente'])->first(),
                "usuario_ensucursal" => $data['usuario_ensucursal'],
                "metodos_pago" => $data['metodos_pago'],
                "ids_productos" => $data['ids_productos'],
                "tipo_descuento" => $data['tipo_descuento'],
                "observaciones" => $data['observaciones']
            ]);
            
            if ($response->ok()) {
                $resretur = $response->json();
                if ($resretur) {
                    return $resretur;
                } 
            }
            return $response;

        } catch (\Exception $e) {
            return Response::json(["msj" => "Error: " . $e->getMessage(), "estado" => false]);
        } 
    }

    // =================== FIN MÉTODOS SOLICITUDES DE DESCUENTOS ===================

    // =================== MÉTODOS SOLICITUDES DE REVERSO ===================

    /**
     * Obtener solicitudes de reverso aprobadas desde arabitocentral
     */
    public function getSolicitudesReversoAprobadas() {
        try {
            $codigoOrigen = $this->getOrigen();
            
            $response = Http::timeout(30)->get($this->path() . "/api/solicitudes-reverso", [
                "codigo_origen" => $codigoOrigen,
                "status" => "Aprobada"
            ]);
            
            
            if ($response->ok()) {
                $data = $response->json();
                if ($data['success']) {
                    return [
                        'success' => true,
                        'data' => $data['solicitudes'] ?? []
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => $data['message'] ?? 'Error al obtener solicitudes de reverso'
                    ];
                }
            } else {
                \Log::error('Error al obtener solicitudes de reverso aprobadas desde central', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Error de comunicación con arabitocentral: ' . $response->status()
                ];
            }
        } catch (\Exception $e) {
            \Log::error('Excepción al obtener solicitudes de reverso aprobadas desde central', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Error de conexión: ' . $e->getMessage()
            ];
        }
    }

    // =================== FIN MÉTODOS SOLICITUDES DE REVERSO ===================

    // =================== MÉTODOS SINCRONIZACIÓN DIRECTA ===================

    /**
     * Obtiene los últimos IDs sincronizados directamente desde arabitocentral
     */
    function getLastIdsDirect() 
    {
        try {
            $codigo_origen = $this->getOrigen();
            $response = Http::timeout(30)->get($this->path() . "/getLastDirect", [
                "codigo_origen" => $codigo_origen,
            ]);
            
            if ($response->ok()) {
                return $response->json();
            }
            return $response->json();
            
            return [
                'date_last_cierres' => '2000-01-01',
                'id_last_garantias' => 0,
                'id_last_efec' => 0,
                'id_last_estadisticas' => 0,
                'id_last_fallas' => 0,
                'id_last_movs' => 0,
            ];
        } catch (\Exception $e) {
            return [
                'date_last_cierres' => '2000-01-01',
                'id_last_garantias' => 0,
                'id_last_efec' => 0,
                'id_last_estadisticas' => 0,
                'id_last_fallas' => 0,
                'id_last_movs' => 0,
            ];
        }
    }

    /**
     * Recopila datos pendientes de sincronización con logs de tiempo
     */
    function collectPendingDataDirect($lastIds, &$tiempos = []) 
    {
        $date_last_cierres = $lastIds['date_last_cierres'] ?? '2000-01-01';
        $id_last_garantias = $lastIds['id_last_garantias'] ?? 0;
        $id_last_efec = $lastIds['id_last_efec'] ?? 0;
        $id_last_estadisticas = $lastIds['id_last_estadisticas'] ?? 0;
        $codigo_origen = $this->getOrigen();
        
        $inicio = microtime(true);
        \Log::info('=== INICIO RECOPILACIÓN DE DATOS ===');
        
        // 1. Contar items pedidos
        \Log::info('>>> Iniciando: Contar items pedidos...');
        $t1 = microtime(true);
        $numitemspedidos = items_pedidos::whereNotNull("id_producto")
            ->whereIn("id_pedido", pedidos::whereIn("id", pago_pedidos::where("tipo", "<>", 4)->select("id_pedido"))->select("id"))
            ->count();
        $tiempos['numitemspedidos'] = round(microtime(true) - $t1, 2) . 's';
        \Log::info('<<< Completado: Contar items pedidos - ' . $tiempos['numitemspedidos'] . ' - Total: ' . $numitemspedidos);
        
        // 2. Inventario
        \Log::info('>>> Iniciando: Inventario...');
        $t2 = microtime(true);
        $sendInventarioCt = $this->sendInventario(false, $date_last_cierres);
        $tiempos['sendInventarioCt'] = round(microtime(true) - $t2, 2) . 's';
        \Log::info('<<< Completado: Inventario - ' . $tiempos['sendInventarioCt']);
        
        // 3. Garantías
        \Log::info('>>> Iniciando: Garantías...');
        $t3 = microtime(true);
        $sendGarantias = $this->sendGarantias($id_last_garantias);
        $tiempos['sendGarantias'] = round(microtime(true) - $t3, 2) . 's';
        \Log::info('<<< Completado: Garantías - ' . $tiempos['sendGarantias']);
        
        // 4. Fallas
        \Log::info('>>> Iniciando: Fallas...');
        $t4 = microtime(true);
        $sendFallas = $this->sendFallas(0);
        $tiempos['sendFallas'] = round(microtime(true) - $t4, 2) . 's';
        \Log::info('<<< Completado: Fallas - ' . $tiempos['sendFallas']);
        
        // 5. Cierres
        \Log::info('>>> Iniciando: Cierres...');
        $t5 = microtime(true);
        $setCierreFromSucursalToCentral = $this->sendCierres($date_last_cierres);
        $tiempos['sendCierres'] = round(microtime(true) - $t5, 2) . 's';
        \Log::info('<<< Completado: Cierres - ' . $tiempos['sendCierres']);
        
        // 6. Efectivo
        \Log::info('>>> Iniciando: Efectivo...');
        $t6 = microtime(true);
        $setEfecFromSucursalToCentral = $this->sendEfec($id_last_efec);
        $tiempos['sendEfec'] = round(microtime(true) - $t6, 2) . 's';
        \Log::info('<<< Completado: Efectivo - ' . $tiempos['sendEfec']);
        
        // 7. Créditos
        \Log::info('>>> Iniciando: Créditos...');
        $t7 = microtime(true);
        $sendCreditos = $this->sendCreditos();
        $tiempos['sendCreditos'] = round(microtime(true) - $t7, 2) . 's';
        \Log::info('<<< Completado: Créditos - ' . $tiempos['sendCreditos']);
        
        // 8. Estadísticas de venta
        \Log::info('>>> Iniciando: Estadísticas de venta...');
        $t8 = microtime(true);
        $sendestadisticasVenta = $this->sendestadisticasVenta($id_last_estadisticas);
        $tiempos['sendestadisticasVenta'] = round(microtime(true) - $t8, 2) . 's';
        \Log::info('<<< Completado: Estadísticas de venta - ' . $tiempos['sendestadisticasVenta']);
        
        $tiempos['total_recopilacion'] = round(microtime(true) - $inicio, 2) . 's';
        
        \Log::info('=== FIN RECOPILACIÓN - RESUMEN DE TIEMPOS ===', $tiempos);
        
        return [
            "numitemspedidos" => $numitemspedidos,
            "sendInventarioCt" => $sendInventarioCt,
            "sendGarantias" => $sendGarantias,
            "sendFallas" => $sendFallas,
            "setCierreFromSucursalToCentral" => $setCierreFromSucursalToCentral,
            "setEfecFromSucursalToCentral" => $setEfecFromSucursalToCentral,
            "sendCreditos" => $sendCreditos,
            "sendestadisticasVenta" => $sendestadisticasVenta,
            "movsinventario" => [],
            "codigo_origen" => $codigo_origen,
        ];
    }

    /**
     * Envía datos con reintentos para intermitencia de red
     */
    function sendBatchWithRetry($endpoint, $data, $maxRetries = 3, $timeout = 120) 
    {
        $retryCount = 0;
        $lastError = null;
        
        while ($retryCount < $maxRetries) {
            try {
                $response = Http::timeout($timeout)->post($this->path() . $endpoint, $data);
                if ($response->ok()) {
                    return ['success' => true, 'data' => $response->json(), 'attempts' => $retryCount + 1];
                }
                $lastError = "HTTP " . $response->status() . ": " . $response->body();
            } catch (\Exception $e) {
                $lastError = $e->getMessage();
            }
            $retryCount++;
            if ($retryCount < $maxRetries) {
                sleep(pow(2, $retryCount - 1));
            }
        }
        return ['success' => false, 'error' => $lastError, 'attempts' => $retryCount];
    }

    /**
     * Sincronización directa unificada - Usa el nuevo SyncProgressController
     * Solo sincroniza registros desde 2025-12-01 en adelante
     * 
     * @param mixed $correo - Array con datos de correo o null para modo test
     * @param bool $returnTiming - Si true, retorna tiempos detallados (modo test)
     */
    function sendAllDirect($correo = null, $returnTiming = false) 
    {
        ini_set('memory_limit', '4095M');
        $tiemposTotal = [];
        $inicioTotal = microtime(true);
        
        \Log::info('========================================');
        \Log::info('=== INICIO SINCRONIZACIÓN (Nuevo Sistema) ===');
        \Log::info('Modo: ' . ($correo ? 'Producción con correo' : 'Test con tiempos'));
        \Log::info('Fecha de corte: 2025-12-01 (registros anteriores NO se sincronizan)');
        \Log::info('========================================');
        
        try {
            // 1. Ejecutar sincronización usando el nuevo controlador
            \Log::info('>>> PASO 1: Ejecutando sincronización con SyncProgressController...');
            $t1 = microtime(true);
            
            $syncController = app(SyncProgressController::class);
            $request = new \Illuminate\Http\Request();
            $request->merge([
                'tablas' => ['all'],
                'solo_nuevos' => true,
            ]);
            
            $syncResponse = $syncController->sincronizarTodo($request);
            $syncResult = json_decode($syncResponse->getContent(), true);
            
            $tiemposTotal['1_sincronizacion'] = round(microtime(true) - $t1, 2) . 's';
            \Log::info('<<< PASO 1 completado - ' . $tiemposTotal['1_sincronizacion']);
            
            if (!$syncResult['estado']) {
                $tiemposTotal['total'] = round(microtime(true) - $inicioTotal, 2) . 's';
                $errorMsg = "ERROR en sincronización: " . ($syncResult['mensaje'] ?? 'Error desconocido');
                
                \Log::error($errorMsg);
                
                if ($returnTiming) {
                    return ['error' => $errorMsg, 'tiempos' => $tiemposTotal];
                }
                return $errorMsg;
            }
            
            $tiemposTotal['total'] = round(microtime(true) - $inicioTotal, 2) . 's';
            
            \Log::info('=== SINCRONIZACIÓN COMPLETADA ===');
            \Log::info('Tiempo total: ' . $tiemposTotal['total']);
            \Log::info('Registros procesados: ' . ($syncResult['resultado']['registros_totales'] ?? 0));
            \Log::info('>>> Post-procesamiento programado en segundo plano...');
            
            // El post-procesamiento (correo, backup) se ejecutará en una petición separada
            // desde el frontend después de mostrar éxito al usuario
            
            // Preparar respuesta (usar 'estado' y 'msj' para compatibilidad con frontend)
            $response = [
                'estado' => true,
                'msj' => 'Sincronización completada exitosamente',
                'registros_totales' => $syncResult['resultado']['registros_totales'] ?? 0,
                'tiempo_sync' => $syncResult['resultado']['tiempo_total'] ?? '0s',
                'tablas' => $syncResult['resultado']['tablas'] ?? [],
            ];
            
            if ($returnTiming) {
                $response['tiempos_sucursal'] = $tiemposTotal;
            }
            
            return $response;
            
        } catch (\Exception $e) {
            $tiemposTotal['total'] = round(microtime(true) - $inicioTotal, 2) . 's';
            $errorMsg = $e->getMessage() . " - " . $e->getFile() . ":" . $e->getLine();
            \Log::error('=== ERROR EN SINCRONIZACIÓN ===');
            \Log::error($errorMsg);
            
            if ($returnTiming) {
                return ['error' => $errorMsg, 'tiempos' => $tiemposTotal];
            }
            return $errorMsg;
        }
    }

    // =================== FIN MÉTODOS SINCRONIZACIÓN DIRECTA ===================

    /**
     * Ejecutar post-procesamiento después de sincronización exitosa
     * Obtiene todos los datos del backend usando PedidosController
     */
    public function ejecutarPostSync(Request $request)
    {
        \Log::info('=== INICIO POST-SYNC ===');
        
        try {
            $fecha = $request->input('fecha', date('Y-m-d'));
            $totalizarcierre = $request->input('totalizarcierre', true);
            
            \Log::info('    Obteniendo datos del cierre para fecha: ' . $fecha);
            
            $pedidosController = new PedidosController();
            $fakeRequest = new Request();
            $fakeRequest->merge([
                'fecha' => $fecha,
                'type' => 'getData',
                'totalizarcierre' => $totalizarcierre,
            ]);
            
            $correoData = $pedidosController->verCierre($fakeRequest);
            
            if (is_array($correoData) && count($correoData) >= 4) {
                \Log::info('    Enviando correo de cierre...');
                Mail::to($this->sends())->send(new enviarCierre(
                    $correoData[0],
                    $correoData[1],
                    $correoData[2],
                    $correoData[3]
                ));
                \Log::info('    Correo enviado');
            } else {
                \Log::info('    No se pudieron obtener datos del cierre');
            }
            
            \Log::info('    Ejecutando backup...');
            \Artisan::call('database:backup');
            \Artisan::call('backup:run');
            \Log::info('    Backup completado');
            
            \Log::info('    Actualizando IVA...');
            \DB::statement("UPDATE `inventarios` SET iva=1");
            \DB::statement("UPDATE `inventarios` SET iva=0 WHERE descripcion LIKE 'MACHETE%'");
            \DB::statement("UPDATE `inventarios` SET iva=0 WHERE descripcion LIKE 'PEINILLA%'");
            \DB::statement("UPDATE `inventarios` SET iva=0 WHERE descripcion LIKE 'MOTOBOMBA%'");
            \DB::statement("UPDATE `inventarios` SET iva=0 WHERE descripcion LIKE 'ELECTROBOMBA%'");
            \DB::statement("UPDATE `inventarios` SET iva=0 WHERE descripcion LIKE 'DESMALEZADORA%'");
            \DB::statement("UPDATE `inventarios` SET iva=0 WHERE descripcion LIKE 'MOTOSIERRA%'");
            \DB::statement("UPDATE `inventarios` SET iva=0 WHERE descripcion LIKE 'CUCHILLA%'");
            \DB::statement("UPDATE `inventarios` SET iva=0 WHERE descripcion LIKE 'FUMIGADORA%'");
            \DB::statement("UPDATE `inventarios` SET iva=0 WHERE descripcion LIKE 'MANGUERA%'");
            
            \Log::info('=== POST-SYNC COMPLETADO ===');
            return response()->json(['estado' => true, 'msj' => 'Post-procesamiento completado']);
            
        } catch (\Exception $e) {
            \Log::error('Error en post-sync: ' . $e->getMessage());
            return response()->json(['estado' => false, 'msj' => 'Error: ' . $e->getMessage()]);
        }
    }

    /**
     * Enviar transacción al POS físico de débito
     */
    public function enviarTransaccionPOS(Request $request)
    {
        $payload = [
            'operacion' => $request->input('operacion', 'COMPRA'),
            'monto' => $request->input('monto'),
            'tipoCuenta' => $request->input('tipoCuenta'),
            'cedula' => $request->input('cedula'),
            'numeroOrden' => $request->input('numeroOrden'),
            'mensaje' => $request->input('mensaje', 'PowerBy:Ospino'),
        ];
        
        // Obtener IP del PINPAD del request o usar la del usuario logueado
        $ipPinpad = $request->input('ip_pinpad');
        if (!$ipPinpad) {
            // Fallback: obtener del usuario logueado
            $userId = session('id_usuario');
            if ($userId) {
                $usuario = \App\Models\usuarios::find($userId);
                $ipPinpad = $usuario->ip_pinpad ?? '192.168.0.191:9001';
            } else {
                $ipPinpad = '192.168.0.191:9001';
            }
        }
        
        $posUrl = 'http://' . $ipPinpad . '/transaction';
        
        try {
            $response = Http::timeout(120)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($posUrl, $payload);
            
            return response()->json([
                'success' => $response->successful(),
                'status' => $response->status(),
                'data' => $response->json() ?? $response->body(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error POS: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Registrar operación POS rechazada
     */
    public function registrarPosRechazado(Request $request)
    {
        try {
            \App\Models\PosOperacionRechazada::create([
                'message' => $request->input('message'),
                'reference' => $request->input('reference'),
                'ordernumber' => $request->input('ordernumber'),
                'sequence' => $request->input('sequence'),
                'approval' => $request->input('approval'),
                'lote' => $request->input('lote'),
                'responsecode' => $request->input('responsecode'),
                'datetime' => $request->input('datetime'),
                'amount' => $request->input('amount'),
                'commerce' => $request->input('commerce'),
                'cardtype' => $request->input('cardtype'),
                'authid' => $request->input('authid'),
                'cardNumber' => $request->input('cardNumber'),
                'cardholderid' => $request->input('cardholderid'),
                'idmerchant' => $request->input('idmerchant'),
                'terminal' => $request->input('terminal'),
                'bank' => $request->input('bank'),
                'bankmessage' => $request->input('bankmessage'),
                'tvr' => $request->input('tvr'),
                'arqc' => $request->input('arqc'),
                'tsi' => $request->input('tsi'),
                'na' => $request->input('na'),
                'aid' => $request->input('aid'),
                'json_response' => $request->input('json_response'),
                'id_pedido' => $request->input('id_pedido'),
                'id_usuario' => session('id_usuario'),
                'id_sucursal' => session('sucursal')
            ]);

            return response()->json([
                'estado' => true,
                'msj' => 'Operación rechazada registrada correctamente'
            ]);
        } catch (\Exception $e) {
            \Log::error('Error al registrar POS rechazado: ' . $e->getMessage());
            return response()->json([
                'estado' => false,
                'msj' => 'Error al registrar operación rechazada'
            ], 500);
        }
    }

}