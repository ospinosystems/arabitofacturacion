<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\sendCentral;

class GarantiaEjecucionLocalService
{
    /**
     * Ejecutar solicitud de garantía localmente en la sucursal
     */
    public function ejecutarSolicitudLocalmente($solicitudData, $idCaja = null)
    {
        try {
            DB::beginTransaction();
            
           /*  Log::info('Iniciando ejecución local de garantía', [
                'solicitud_id' => $solicitudData['id'],
                'caso_uso' => $solicitudData['caso_uso']
            ]);
             */
            // IMPORTANTE: Decodificar JSON strings a arrays
            if (isset($solicitudData['productos_data']) && is_string($solicitudData['productos_data'])) {
                $solicitudData['productos_data'] = json_decode($solicitudData['productos_data'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Error decodificando productos_data JSON: ' . json_last_error_msg());
                }
            }
            
            if (isset($solicitudData['garantia_data']) && is_string($solicitudData['garantia_data'])) {
                $solicitudData['garantia_data'] = json_decode($solicitudData['garantia_data'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Error decodificando garantia_data JSON: ' . json_last_error_msg());
                }
            }
            
            if (isset($solicitudData['metodos_devolucion']) && is_string($solicitudData['metodos_devolucion'])) {
                $solicitudData['metodos_devolucion'] = json_decode($solicitudData['metodos_devolucion'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Error decodificando metodos_devolucion JSON: ' . json_last_error_msg());
                }
            }
            
            // Combinar información de productos_data con productos_con_datos
            $solicitudData['productos_procesados'] = $this->combinarDatosProductos($solicitudData);
            
          /*   Log::info('Datos procesados para ejecución', [
                'productos_data_count' => count($solicitudData['productos_data'] ?? []),
                'productos_con_datos_count' => count($solicitudData['productos_con_datos'] ?? []),
                'productos_procesados_count' => count($solicitudData['productos_procesados'] ?? []),
                'metodos_devolucion_count' => count($solicitudData['metodos_devolucion'] ?? []),
                'caso_uso' => $solicitudData['caso_uso'],
                'productos_entrada_count' => count(array_filter($solicitudData['productos_procesados'] ?? [], function($p) { return ($p['tipo'] ?? '') === 'entrada'; })),
                'productos_salida_count' => count(array_filter($solicitudData['productos_procesados'] ?? [], function($p) { return ($p['tipo'] ?? '') === 'salida'; }))
            ]); */
            
            // 1. CREAR PEDIDO ÚNICO PARA TODA LA SOLICITUD - SIEMPRE PENDIENTE
            $pedidoUnificado = \App\Models\pedidos::create([
                'id_cliente' => 1, // Cliente genérico para garantías
                'id_vendedor' => $idCaja ?? session('id_usuario') ?? 1,
                'estado' => 0, // SIEMPRE PENDIENTE - no se procesan pagos
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            if (!$pedidoUnificado || !$pedidoUnificado->id) {
                throw new \Exception("Error creando pedido unificado para garantía");
            }
            
            /* Log::info('Pedido unificado creado', [
                'pedido_id' => $pedidoUnificado->id,
                'solicitud_id' => $solicitudData['id'],
                'caso_uso' => $solicitudData['caso_uso']
            ]); */
            
            $resultados = [];
            
            // 2. Procesar según el caso de uso - pasando el pedido_id

             /* \Log::info('solicitudData', $solicitudData); */
            switch ($solicitudData['caso_uso']) {
                case 1:
                    $resultados = $this->ejecutarCasoUso3($solicitudData, $pedidoUnificado->id); // Garantía simple MALOOOOOO
                    break;
                case 2:
                    $resultados = $this->ejecutarCasoUso3($solicitudData, $pedidoUnificado->id); // Garantía con diferencia MALOOOOOO
                    break;
                case 4:
                    $resultados = $this->ejecutarCasoUso3($solicitudData, $pedidoUnificado->id); // Cambio modelo MALOOOOOO
                    break;
                case 3:
                    $resultados = $this->ejecutarCasoUso3($solicitudData, $pedidoUnificado->id); // Devolución dinero
                    break;
                case 5:
                    $resultados = $this->ejecutarCasoUso3($solicitudData, $pedidoUnificado->id); // Caso mixto
                    break;
                default:
                    throw new \Exception("Caso de uso no válido: " . $solicitudData['caso_uso']);
            }
            
            // 3. Crear items en el pedido unificado para productos procesados (entradas y salidas)
            if (!empty($resultados)) {
                $resultadoItems = $this->crearItemsPedidoParaProductosGarantiaUnificado(
                    $pedidoUnificado->id,
                    $resultados, 
                    $solicitudData['id'], 
                    "CASO_USO_{$solicitudData['caso_uso']}",
                    $solicitudData
                );
                \Log::info('solicitudData', $solicitudData);
                
                if (!$resultadoItems['success']) {
                    throw new \Exception("Error creando items del pedido: " . implode(', ', $resultadoItems['errores'] ?? []));
                }
                
                // Agregar información del resultado de items
                $resultados['items_resultado'] = $resultadoItems;
                
                // Debug: verificar estructura de $resultadoItems
               /*  Log::debug('Estructura de resultadoItems para debug', [
                    'resultadoItems_keys' => array_keys($resultadoItems),
                    'items_creados_type' => gettype($resultadoItems['items_creados'] ?? null),
                    'items_creados_is_array' => is_array($resultadoItems['items_creados'] ?? null),
                    'total_items' => $resultadoItems['total_items'] ?? 'N/A'
                ]);
                
                Log::info('Items del pedido unificado creados', [
                    'pedido_id' => $pedidoUnificado->id,
                    'solicitud_id' => $solicitudData['id'],
                    'total_items_creados' => $resultadoItems['total_items'] ?? 0,
                    'items_entrada' => is_array($resultadoItems['items_creados'] ?? null) ? count(array_filter($resultadoItems['items_creados'], function($item) { 
                        return ($item['tipo_movimiento'] ?? '') === 'ENTRADA'; 
                    })) : 0,
                    'items_salida' => is_array($resultadoItems['items_creados'] ?? null) ? count(array_filter($resultadoItems['items_creados'], function($item) { 
                        return ($item['tipo_movimiento'] ?? '') === 'SALIDA'; 
                    })) : 0,
                    'items_garantias_entrantes' => is_array($resultadoItems['items_creados'] ?? null) ? count(array_filter($resultadoItems['items_creados'], function($item) { 
                        return ($item['tipo_movimiento'] ?? '') === 'GARANTIA_ENTRANTE'; 
                    })) : 0
                ]); */
            }
            
            // 4. Mantener pedido unificado como PENDIENTE - no se procesan pagos
            // $pedidoUnificado->update(['estado' => 1]); // COMENTADO - pedido siempre pendiente
            
            // Validación final: verificar que todas las operaciones fueron exitosas
            $this->validarResultadosCompletos($resultados, $solicitudData);
            
            DB::commit();

            //dd("logs");
            
           /*  Log::info('Ejecución local completada exitosamente', [
                'solicitud_id' => $solicitudData['id'],
                'resultados' => $resultados
            ]); */
            
            // Combinar todos los resultados en un formato estándar
            $inventario = [];
            $garantias_recibidas = [];
            $pagos = [];
            $movimientos = [];
            $pedidos_creados = [];
            
            // Procesar inventario de entrada y salida (solo productos buenos vendibles)
            if (isset($resultados['inventario_entrada'])) {
                foreach ($resultados['inventario_entrada'] as $entrada) {
                    $inventario[] = array_merge($entrada, ['tipo' => 'ENTRADA']);
                }
            }
            if (isset($resultados['inventario_salida'])) {
                foreach ($resultados['inventario_salida'] as $salida) {
                    $inventario[] = array_merge($salida, ['tipo' => 'SALIDA']);
                }
            }
            
            // Procesar garantías recibidas (productos dañados, solo registro)
            if (isset($resultados['garantias_recibidas'])) {
                $garantias_recibidas = $resultados['garantias_recibidas'];
            }
            
            // NO SE PROCESAN PAGOS - pedido queda pendiente
            $pagos = []; // Array vacío - no se crean pagos
            
            // El único pedido creado es el unificado
            $pedidos_creados = [$pedidoUnificado->id];
            
            return [
                'success' => true,
                'message' => 'Solicitud ejecutada localmente',
                'solicitud_id' => $solicitudData['id'],
                'caso_uso' => $solicitudData['caso_uso'],
                'inventario' => $inventario, // Solo productos buenos vendibles
                'garantias_recibidas' => $garantias_recibidas, // Productos dañados recibidos (solo registro)
                'pagos' => $pagos,
                'movimientos' => $movimientos,
                'pedidos_creados' => array_unique($pedidos_creados),
                'resultados_originales' => $resultados // Mantener estructura original para debug
            ];
            
        } catch (\Exception $e) {
            DB::rollback();
            
            Log::error('Error en ejecución local de garantía - ROLLBACK EJECUTADO', [
                'solicitud_id' => $solicitudData['id'] ?? 'N/A',
                'caso_uso' => $solicitudData['caso_uso'] ?? 'N/A',
                'error' => $e->getMessage(),
                'error_line' => $e->getLine(),
                'error_file' => basename($e->getFile()),
                'productos_procesados' => count($solicitudData['productos_procesados'] ?? []),
                'metodos_devolucion' => count($solicitudData['metodos_devolucion'] ?? []),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Error en ejecución local: ' . $e->getMessage(),
                'solicitud_id' => $solicitudData['id'] ?? null,
                'rollback_executed' => true,
                'error_details' => [
                    'line' => $e->getLine(),
                    'file' => basename($e->getFile()),
                    'tipo_error' => get_class($e)
                ]
            ];
        }
    }
    
    /**
     * Validar que todas las operaciones fueron exitosas antes del commit
     */
    private function validarResultadosCompletos($resultados, $solicitudData)
    {
        $errores = [];
        
        // Validar inventario entrada
        if (isset($resultados['inventario_entrada'])) {
            foreach ($resultados['inventario_entrada'] as $index => $entrada) {
                if (!$entrada['success']) {
                    $errores[] = "Error en entrada de inventario #{$index}: {$entrada['error']}";
                }
            }
        }
        
        // Validar inventario salida
        if (isset($resultados['inventario_salida'])) {
            foreach ($resultados['inventario_salida'] as $index => $salida) {
                if (!$salida['success']) {
                    $errores[] = "Error en salida de inventario #{$index}: {$salida['error']}";
                }
            }
        }
        
        // Validar pagos
        if (isset($resultados['pagos'])) {
            foreach ($resultados['pagos'] as $index => $pago) {
                if (!$pago['success']) {
                    $errores[] = "Error en pago #{$index}: {$pago['error']}";
                }
            }
        }
        
        // Validar pedido de productos
        if (isset($resultados['pedido_productos'])) {
            if (!$resultados['pedido_productos']['success']) {
                $errores[] = "Error en pedido de productos: " . ($resultados['pedido_productos']['error'] ?? 'Error desconocido');
            }
        }
        
        // Si hay errores, lanzar excepción para activar rollback
        if (!empty($errores)) {
            $mensajeError = "Errores en ejecución de garantía:\n" . implode("\n", $errores);
            
            Log::error('Validación final falló', [
                'solicitud_id' => $solicitudData['id'] ?? 'N/A',
                'caso_uso' => $solicitudData['caso_uso'] ?? 'N/A',
                'errores' => $errores,
                'total_errores' => count($errores)
            ]);
            
            throw new \Exception($mensajeError);
        }
        
        Log::info('Validación final exitosa', [
            'solicitud_id' => $solicitudData['id'] ?? 'N/A',
            'caso_uso' => $solicitudData['caso_uso'] ?? 'N/A',
            'operaciones_validadas' => [
                'entradas_inventario' => count($resultados['inventario_entrada'] ?? []),
                'salidas_inventario' => count($resultados['inventario_salida'] ?? []),
                'pagos_procesados' => count($resultados['pagos'] ?? []),
                'garantias_recibidas' => count($resultados['garantias_recibidas'] ?? []),
                'pedido_productos_creado' => isset($resultados['pedido_productos']) && $resultados['pedido_productos']['success'],
                    'items_productos_creados' => $resultados['pedido_productos']['items_resultado']['total_items'] ?? 0,
                    'total_productos_procesados' => count($resultados['inventario_entrada'] ?? []) + 
                                                   count($resultados['inventario_salida'] ?? []) + 
                                                   count($resultados['garantias_recibidas'] ?? [])
            ]
        ]);
    }

    /**
     * Combinar información de productos_data con productos_con_datos
     */
    private function combinarDatosProductos($solicitudData)
    {
        $productosData = $solicitudData['productos_data'] ?? [];
        $productosConDatos = $solicitudData['productos_con_datos'] ?? [];
        $productosCombi = [];
        
        foreach ($productosData as $prodData) {
            $idProducto = $prodData['id_producto'] ?? 0;
            
            // Buscar información detallada del producto
            $productoDetalle = null;
            foreach ($productosConDatos as $prodDetalle) {
                if (($prodDetalle['id_producto'] ?? 0) == $idProducto) {
                    $productoDetalle = $prodDetalle;
                    break;
                }
            }
            
            // Combinar información
            $productosCombi[] = array_merge($prodData, [
                'producto_detalle' => $productoDetalle,
                'descripcion' => $productoDetalle['producto']['descripcion'] ?? "Producto ID: $idProducto",
                'precio' => $productoDetalle['producto']['precio'] ?? 0,
                'stock_disponible' => $productoDetalle['producto']['cantidad_disponible'] ?? 0
            ]);
        }
        
        return $productosCombi;
    }
    
    /**
     * Caso de Uso 1: Garantía simple (intercambio directo)
     */
    /* private function ejecutarCasoUso1($solicitudData, $pedidoId)
    {
        $resultados = [];
        $productos = $solicitudData['productos_procesados'] ?? [];
        
        foreach ($productos as $producto) {
            $productoId = $producto['id_producto'] ?? 0;
            $cantidad = $producto['cantidad'] ?? 0;
            $tipo = $producto['tipo'] ?? '';
            $estado = $producto['estado'] ?? '';
            
            if ($tipo === 'entrada') {
                // IMPORTANTE: Las garantías entrantes (productos dañados) NO se cargan al inventario local
                    // Solo registrar para logging, NO afectar inventario local
                    $resultados['garantias_recibidas'][] = [
                    'producto_id' => $productoId,
                    'cantidad' => $cantidad,
                    'descripcion' => "Garantía simple - Producto dañado recibido: {$producto['descripcion']}",
                    'tipo' => 'GARANTIA_ENTRANTE',
                    'estado' => $estado
                    ];
                    
                    Log::info('Garantía entrante registrada (NO afecta inventario local)', [
                    'producto_id' => $productoId,
                    'cantidad' => $cantidad,
                    'descripcion' => $producto['descripcion'] ?? "ID: $productoId",
                        'caso_uso' => 1
                    ]);
        
            } elseif ($tipo === 'salida') {
        // Procesar salidas (productos que se entregan al cliente)
                $resultadoSalida = $this->procesarSalidaInventario(
                    $productoId,
                    $cantidad,
                        'SALIDA_GARANTIA_SIMPLE',
                    "Garantía simple - Salida: {$producto['descripcion']}"
                    );
                
                if (!$resultadoSalida['success']) {
                    throw new \Exception("Error en salida de inventario: {$resultadoSalida['error']}");
                }
                
                $resultados['inventario_salida'][] = $resultadoSalida;
            }
        }
        
        return $resultados;
    } */
    
    /**
     * Caso de Uso 2: Garantía con diferencia (cliente paga diferencia)
     * NO SE PROCESAN PAGOS - pedido queda pendiente
     */
   /*  private function ejecutarCasoUso2($solicitudData, $pedidoId)
    {
        $resultados = [];
        
        // Procesar inventario igual que caso 1
        $resultados = $this->ejecutarCasoUso1($solicitudData, $pedidoId);
        
        // NO SE PROCESAN PAGOS - pedido queda pendiente
        // Los métodos de devolución se procesarán posteriormente en otro módulo
        
        return $resultados;
    } */
    
    /**
     * Caso de Uso 3: Devolución de dinero
     */
    private function ejecutarCasoUso3($solicitudData, $pedidoId)
    {
        $resultados = [];
        $productos = $solicitudData['productos_procesados'] ?? [];

        $factura_venta_id = $solicitudData['factura_venta_id'] ?? 0;
        $id_pedido_insucursal = $solicitudData['id_pedido_insucursal'] ?? 0;
        
        foreach ($productos as $producto) {
            \Log::info('ejecutarCasoUso3 producto', $producto);
            $productoId = $producto['id_producto'] ?? 0;
            $cantidad = $producto['cantidad'] ?? 0;
            $tipo = $producto['tipo'] ?? '';
            $estado = $producto['estado'] ?? '';
            
            if ($tipo === 'entrada') {
                // DEVOLUCIONES: productos en buen estado que SÍ afectan inventario local

                $esDevolucion = ($estado === 'BUENO');
                if ($esDevolucion) {
                    $resultadoEntrada = $this->procesarEntradaInventario(
                        $productoId,
                        $cantidad,
                            'ENTRADA_DEVOLUCION_DINERO',
                        "Producto devuelto: {$producto['descripcion']} - ORIGINAL:{$factura_venta_id} - FACT.GENERADA:{$id_pedido_insucursal}"
                    );
                    
                    if (!$resultadoEntrada['success']) {
                        throw new \Exception("Error en entrada de inventario: {$resultadoEntrada['error']}");
                    }
                    
                    $resultados['inventario_entrada'][] = $resultadoEntrada;
                }else{
                    $resultados['garantias_recibidas'][] = [
                        'producto_id' => $productoId,
                        'cantidad' => $cantidad,
                        'descripcion' => "Caso mixto - Producto dañado recibido: {$producto['descripcion']}",
                        'tipo' => 'GARANTIA_ENTRANTE',
                        'estado' => $estado
                    ];
                }



               
                
            } elseif ($tipo === 'salida') {
                // En caso de devolución de dinero, normalmente no hay salidas de productos
                // pero si las hay, procesarlas
                $resultadoSalida = $this->procesarSalidaInventario(
                    $productoId,
                    $cantidad,
                    'SALIDA_DEVOLUCION_DINERO',
                    "Salida: {$producto['descripcion']} - ORIGINAL:{$factura_venta_id} - FACT.GENERADA:{$id_pedido_insucursal}"
                );
                
                if (!$resultadoSalida['success']) {
                    throw new \Exception("Error en salida de inventario: {$resultadoSalida['error']}");
                }
                
                $resultados['inventario_salida'][] = $resultadoSalida;
            }
        }
        
        // NO SE PROCESAN PAGOS - pedido queda pendiente
        // Los métodos de devolución se procesarán posteriormente en otro módulo
        
        return $resultados;
    }
    
    /**
     * Caso de Uso 4: Cambio de modelo
     */
   /*  private function ejecutarCasoUso4($solicitudData, $pedidoId)
    {
        // Similar al caso 2, pero con lógica específica para cambio de modelo
        return $this->ejecutarCasoUso2($solicitudData, $pedidoId);
    } */
    
    /**
     * Caso de Uso 5: Caso mixto
     */
   /*  private function ejecutarCasoUso5($solicitudData, $pedidoId)
    {
        $resultados = [];
        $productos = $solicitudData['productos_procesados'] ?? [];
        $tipoSolicitud = $solicitudData['tipo_solicitud'] ?? '';
        
        foreach ($productos as $producto) {
            $productoId = $producto['id_producto'] ?? 0;
            $cantidad = $producto['cantidad'] ?? 0;
            $tipo = $producto['tipo'] ?? '';
            $estado = $producto['estado'] ?? '';
            
            if ($tipo === 'entrada') {
                // CASO MIXTO: Determinar si es garantía (producto dañado) o devolución (producto bueno)
                $esDevolucion = ($estado === 'BUENO' && $tipoSolicitud === 'DEVOLUCION') || 
                               ($estado === 'BUENO' && strpos($tipoSolicitud, 'DEVOL') !== false);
                    
                    if ($esDevolucion) {
                        // DEVOLUCIÓN: Sí afecta inventario local
                    $resultadoEntrada = $this->procesarEntradaInventario(
                        $productoId,
                        $cantidad,
                            'ENTRADA_DEVOLUCION_MIXTA',
                        "Caso mixto - Producto bueno devuelto: {$producto['descripcion']}"
                    );
                    
                    if (!$resultadoEntrada['success']) {
                        throw new \Exception("Error en entrada de inventario (caso mixto): {$resultadoEntrada['error']}");
                    }
                    
                    $resultados['inventario_entrada'][] = $resultadoEntrada;
                    } else {
                        // GARANTÍA: No afecta inventario local, solo registro
                        $resultados['garantias_recibidas'][] = [
                        'producto_id' => $productoId,
                        'cantidad' => $cantidad,
                        'descripcion' => "Caso mixto - Producto dañado recibido: {$producto['descripcion']}",
                        'tipo' => 'GARANTIA_ENTRANTE',
                        'estado' => $estado
                        ];
                        
                        Log::info('Garantía entrante en caso mixto (NO afecta inventario local)', [
                        'producto_id' => $productoId,
                        'cantidad' => $cantidad,
                        'descripcion' => $producto['descripcion'] ?? "ID: $productoId",
                            'caso_uso' => 5
                        ]);
        }
        
            } elseif ($tipo === 'salida') {
        // Procesar salidas (productos que se entregan al cliente)
                $resultadoSalida = $this->procesarSalidaInventario(
                    $productoId,
                    $cantidad,
                        'SALIDA_CASO_MIXTO',
                    "Caso mixto - Salida: {$producto['descripcion']}"
                    );
                
                if (!$resultadoSalida['success']) {
                    throw new \Exception("Error en salida de inventario (caso mixto): {$resultadoSalida['error']}");
                }
                
                $resultados['inventario_salida'][] = $resultadoSalida;
            }
        }
        
        // NO SE PROCESAN PAGOS - pedido queda pendiente
        // Los métodos de devolución se procesarán posteriormente en otro módulo
        
        return $resultados;
    } */
    
    /**
     * Procesar entrada de inventario (productos que devuelve el cliente)
     */
    private function procesarEntradaInventario($productoId, $cantidad, $tipoMovimiento, $descripcion)
    {
        try {

            // 1. Obtener inventario actual
            $inventario = \App\Models\inventario::where('id', $productoId)->first();
            if (!$inventario) {
                throw new \Exception("Producto no encontrado con ID: {$productoId}");
            }
            
            $cantidadAnterior = $inventario->cantidad;
            $cantidadNueva = $cantidadAnterior + $cantidad;
            
            // 2. Crear movimiento de inventario
            $movimiento = \App\Models\movimientosInventariounitario::create([
                'id_producto' => $productoId,
                'cantidad' => $cantidad,
                'cantidadafter' => $cantidadNueva,
                'origen' => $descripcion,
                'id_usuario' => session('id_usuario') ?? 1,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // 3. Actualizar cantidad en inventario
            $inventario->cantidad = $cantidadNueva;
            $inventario->save();
            
            return [
                'success' => true,
                'movimiento_id' => $movimiento->id,
                'producto_id' => $productoId,
                'cantidad' => $cantidad,
                'cantidad_anterior' => $cantidadAnterior,
                'cantidad_nueva' => $cantidadNueva,
                'tipo' => 'ENTRADA'
            ];
            
        } catch (\Exception $e) {
            Log::error('Error procesando entrada de inventario', [
                'producto_id' => $productoId,
                'cantidad' => $cantidad,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'producto_id' => $productoId,
                'cantidad' => $cantidad,
                'tipo' => 'ENTRADA'
            ];
        }
    }
    
    /**
     * Procesar salida de inventario (productos que se entregan al cliente)
     */
    private function procesarSalidaInventario($productoId, $cantidad, $tipoMovimiento, $descripcion)
    {
        try {
            // 1. Verificar inventario disponible
            $inventario = \App\Models\inventario::where('id', $productoId)->first();
            if (!$inventario) {
                throw new \Exception("Producto no encontrado con ID: {$productoId}");
            }
            
            if ($inventario->cantidad < $cantidad) {
                $codigo = $inventario->codigo_barras ?? 'N/A';
                $descripcionProd = $inventario->descripcion ?? 'Sin descripción';
                throw new \Exception("Inventario insuficiente para producto ID: {$productoId} (Código: {$codigo}, Descripción: {$descripcionProd}). Disponible: {$inventario->cantidad}, Solicitado: {$cantidad}");
            }
            
            $cantidadAnterior = $inventario->cantidad;
            $cantidadNueva = $cantidadAnterior - $cantidad;
            
            // 2. Crear movimiento de inventario
            $movimiento = \App\Models\movimientosInventariounitario::create([
                'id_producto' => $productoId,
                'cantidad' => -$cantidad, // Negativo para salida
                'cantidadafter' => $cantidadNueva,
                'origen' => $descripcion,
                'id_usuario' => session('id_usuario') ?? 1,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // 3. Actualizar cantidad en inventario
            $inventario->cantidad = $cantidadNueva;
            $inventario->save();
            
            return [
                'success' => true,
                'movimiento_id' => $movimiento->id,
                'producto_id' => $productoId,
                'cantidad' => $cantidad,
                'cantidad_anterior' => $cantidadAnterior,
                'cantidad_nueva' => $cantidadNueva,
                'tipo' => 'SALIDA'
            ];
            
        } catch (\Exception $e) {
            Log::error('Error procesando salida de inventario', [
                'producto_id' => $productoId,
                'cantidad' => $cantidad,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'producto_id' => $productoId,
                'cantidad' => $cantidad,
                'tipo' => 'SALIDA'
            ];
        }
    }
    
    /**
     * Crear pago por diferencia
     * - Diferencia a favor de la empresa = pago positivo (cliente paga más)
     * - Diferencia a favor del cliente = pago negativo (se devuelve dinero)
     */
   /*  private function crearPagoDiferencia($metodo, $solicitudId, $descripcion, $pedidoId)
    {
        try {
            // 1. Validar datos del método
            if (!isset($metodo['tipo'])) {
                throw new \Exception("Tipo de pago no especificado");
            }
            
            if (!isset($metodo['diferencia_pago'])) {
                throw new \Exception("Monto de diferencia no especificado");
            }
            
            // 2. Validar transferencia si es necesario
            if ($this->convertirTipoPago($metodo['tipo']) === 1) { // Transferencia
                $validacionTransferencia = $this->validarTransferenciaConCentral($metodo);
                if (!$validacionTransferencia['success']) {
                    return [
                        'success' => false,
                        'error' => $validacionTransferencia['message'],
                        'tipo' => 'DIFERENCIA'
                    ];
                }
            }
            
            // 3. Usar pedido existente unificado
            $pedido = \App\Models\pedidos::find($pedidoId);
            if (!$pedido) {
                throw new \Exception("Pedido unificado no encontrado: {$pedidoId}");
            }
            
            // 4. Determinar el monto según el tipo de diferencia
            $monto = floatval($metodo['diferencia_pago']);
            
            // Si la diferencia es positiva = a favor de la empresa (cliente paga más)
            // Si la diferencia es negativa = a favor del cliente (se devuelve dinero)
            
            // 5. Crear pago usando el sistema correcto (pago_pedidos)
            $tipoPago = $this->convertirTipoPago($metodo['tipo']);
            
            $pago = \App\Models\pago_pedidos::updateOrCreate(
                [
                    'id_pedido' => $pedido->id,
                    'tipo' => $tipoPago
                ],
                [
                    'cuenta' => 1,
                    'monto' => $monto // Usar el monto con signo correcto
                ]
            );
            
            // 6. Crear items_pedidos para trazabilidad - NOTA: Para diferencias sin productos
            // Solo crear item genérico si no hay productos procesados
            $this->crearItemsPedidoParaGarantiaSinProductos($pedido->id, $solicitudId, $monto, 'DIFERENCIA');
            
            // 7. NOTA: NO actualizar estado del pedido aquí - se hace al final del proceso principal
            
            return [
                'success' => true,
                'pedido_id' => $pedido->id,
                'pago_id' => $pago->id,
                'monto' => $monto,
                'tipo_pago' => $tipoPago,
                'descripcion' => $descripcion,
                'tipo' => $monto > 0 ? 'DIFERENCIA_A_FAVOR_EMPRESA' : 'DIFERENCIA_A_FAVOR_CLIENTE'
            ];
            
        } catch (\Exception $e) {
            Log::error('Error creando pago diferencia', [
                'metodo' => $metodo,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'tipo' => 'DIFERENCIA'
            ];
        }
    } */
    
    /**
     * Crear pago por devolución (se devuelve dinero al cliente)
     * El monto siempre será negativo para devoluciones
     */
    /* private function crearPagoDevolucion($metodo, $solicitudId, $descripcion, $pedidoId)
    {
        try {
            // 1. Validar datos del método
            if (!isset($metodo['tipo'])) {
                throw new \Exception("Tipo de pago no especificado");
            }
            
            if (!isset($metodo['diferencia_pago']) && !isset($metodo['monto_original'])) {
                throw new \Exception("Monto de devolución no especificado");
            }
            
            // 2. Validar transferencia si es necesario
            if ($this->convertirTipoPago($metodo['tipo']) === 1) { // Transferencia
                $validacionTransferencia = $this->validarTransferenciaConCentral($metodo);
                if (!$validacionTransferencia['success']) {
                    return [
                        'success' => false,
                        'error' => $validacionTransferencia['message'],
                        'tipo' => 'DEVOLUCION'
                    ];
                }
            }
            
            // 3. Usar pedido existente unificado
            $pedido = \App\Models\pedidos::find($pedidoId);
            if (!$pedido) {
                throw new \Exception("Pedido unificado no encontrado: {$pedidoId}");
            }
            
            // 4. Determinar el monto (siempre negativo para devolución)
            $montoOriginal = floatval($metodo['diferencia_pago'] ?? $metodo['monto_original'] ?? 0);
            $monto = -abs($montoOriginal);
            
            // 5. Crear pago usando el sistema correcto (pago_pedidos)
            $tipoPago = $this->convertirTipoPago($metodo['tipo']);
            
            $pago = \App\Models\pago_pedidos::updateOrCreate(
                [
                    'id_pedido' => $pedido->id,
                    'tipo' => $tipoPago
                ],
                [
                    'cuenta' => 1,
                    'monto' => $monto // Negativo para devolución
                ]
            );
            
            // 6. Crear items_pedidos para trazabilidad - NOTA: Para devoluciones sin productos
            // Solo crear item genérico si no hay productos procesados  
            $this->crearItemsPedidoParaGarantiaSinProductos($pedido->id, $solicitudId, $monto, 'DEVOLUCION');
            
            // 7. NOTA: NO actualizar estado del pedido aquí - se hace al final del proceso principal
            
            return [
                'success' => true,
                'pedido_id' => $pedido->id,
                'pago_id' => $pago->id,
                'monto' => $monto,
                'tipo_pago' => $tipoPago,
                'descripcion' => $descripcion,
                'tipo' => 'DEVOLUCION'
            ];
            
        } catch (\Exception $e) {
            Log::error('Error creando pago devolución', [
                'metodo' => $metodo,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'tipo' => 'DEVOLUCION'
            ];
        }
    } */
    
    /**
     * Crear pago mixto
     * Determina el tipo de pago según el signo de la diferencia
     */
   /*  private function crearPagoMixto($metodo, $solicitudId, $descripcion, $pedidoId)
    {
        try {
            // 1. Validar datos del método
            if (!isset($metodo['tipo'])) {
                throw new \Exception("Tipo de pago no especificado");
            }
            
            // 2. Determinar si es devolución o diferencia según el signo de diferencia_pago
            $diferencia = floatval($metodo['diferencia_pago'] ?? $metodo['monto_original'] ?? 0);
            
            if ($diferencia > 0) {
                // Diferencia a favor de la empresa (cliente paga más)
                return $this->crearPagoDiferencia($metodo, $solicitudId, $descripcion, $pedidoId);
            } else {
                // Diferencia a favor del cliente (se devuelve dinero)
                return $this->crearPagoDevolucion($metodo, $solicitudId, $descripcion, $pedidoId);
            }
            
        } catch (\Exception $e) {
            Log::error('Error en pago mixto', [
                'metodo' => $metodo,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'tipo' => 'MIXTO'
            ];
        }
    } */
    
    /**
     * Validar transferencia con central usando createTranferenciaAprobacion
     * Reutiliza la función existente que ya funciona correctamente
     */
   /*  private function validarTransferenciaConCentral($metodo)
    {
        try {
            // 1. Validar datos mínimos requeridos
            if (!isset($metodo['monto_original']) && !isset($metodo['diferencia_pago'])) {
                throw new \Exception("Monto no especificado para validación de transferencia");
            }
            
            $sendCentral = new \App\Http\Controllers\sendCentral();
            
            // 2. Determinar monto a validar
            $montoAValidar = abs(floatval($metodo['monto_original'] ?? $metodo['diferencia_pago'] ?? 0));
            
            // 3. Preparar datos para validación con central según el patrón existente
            $dataTransfe = [
                'tipo' => 'DEVOLUCION_GARANTIA',
                'monto' => $montoAValidar, // Usar valor absoluto
                'moneda' => $metodo['moneda_original'] ?? 'USD',
                'monto_usd' => $metodo['monto_usd'] ?? $montoAValidar,
                'tasa_aplicada' => $metodo['tasa_aplicada'] ?? 1,
                'banco' => $metodo['banco'] ?? '',
                'referencia' => $metodo['referencia'] ?? '',
                'telefono' => $metodo['telefono'] ?? '',
                'motivo' => 'Devolución/Diferencia por garantía',
                'timestamp' => now()->toDateTimeString(),
                'es_banco_usd' => $metodo['es_banco_usd'] ?? false,
                // Agregar refs si están disponibles
                'refs' => $metodo['refs'] ?? [],
                'retenciones' => $metodo['retenciones'] ?? []
            ];

            $response = $sendCentral->createTranferenciaAprobacion($dataTransfe);

            if (is_array($response) && isset($response['estado']) && $response['estado'] === true) {
                return [
                    'success' => true,
                    'data' => [
                        'aprobacion_id' => $response['id'] ?? null,
                        'codigo_aprobacion' => $response['codigo'] ?? null,
                        'estado' => $response['estado_texto'] ?? 'APROBADA',
                        'monto_aprobado' => $response['monto'] ?? $montoAValidar,
                        'moneda_aprobada' => $response['moneda'] ?? $metodo['moneda_original'] ?? 'USD',
                        'mensaje' => $response['msj'] ?? 'APROBADO'
                    ]
                ];
            }

            return [
                'success' => false,
                'message' => 'Transferencia no aprobada por central: ' . ($response['msj'] ?? 'Error desconocido')
            ];

        } catch (\Exception $e) {
            Log::error('Error validando transferencia con central', [
                'metodo' => $metodo,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Error al validar transferencia: ' . $e->getMessage()
            ];
        }
    } */

    /**
     * Crear items_pedidos para trazabilidad de garantías con productos reales
     */
    private function crearItemsPedidoParaGarantia($pedidoId, $solicitudId, $productosResultados, $tipo, $productosOriginales = [], $solicitudData = [])
    {
       
        try {
            // 1. Validar datos requeridos
            if (!$pedidoId || !$solicitudId) {
                throw new \Exception("Pedido ID o Solicitud ID no especificados");
            }
            
            $itemsCreados = [];
            $errores = [];
            
            // Obtener items de la factura original para usar precios y descuentos originales
            $itemsFacturaOriginal = [];
            $idFacturaOriginal = $solicitudData['factura_venta_id'] ?? null;
            
            if ($idFacturaOriginal) {
                $itemsOriginales = \App\Models\items_pedidos::where('id_pedido', $idFacturaOriginal)->get();
                foreach ($itemsOriginales as $itemOrig) {
                    $itemsFacturaOriginal[$itemOrig->id_producto] = [
                        'precio' => abs($itemOrig->monto / max(abs($itemOrig->cantidad), 1)),
                        'precio_unitario' => $itemOrig->precio_unitario ?? abs($itemOrig->monto / max(abs($itemOrig->cantidad), 1)),
                        'descuento' => $itemOrig->descuento ?? 0,
                        'tasa' => $itemOrig->tasa ?? null
                    ];
                }
                Log::info('Items factura original cargados para precios', [
                    'factura_venta_id' => $idFacturaOriginal,
                    'total_items' => count($itemsFacturaOriginal),
                    'items' => $itemsFacturaOriginal
                ]);
            } else {
                Log::warning('No se encontró factura_venta_id en solicitudData, usando precios actuales');
            }
            
            // 2. Procesar resultados de inventario (entradas y salidas)
            $todosLosProductos = [];
            
            // Agregar productos de entrada (devoluciones)
            if (isset($productosResultados['inventario_entrada'])) {
                foreach ($productosResultados['inventario_entrada'] as $entrada) {
                    if ($entrada['success']) {
                        // Determinar condición basada en el contexto
                        $condicion = $this->determinarCondicionProducto($entrada['producto_id'], 'ENTRADA', $productosOriginales, $tipo);
                        
                        $todosLosProductos[] = [
                            'id_producto' => $entrada['producto_id'],
                            'cantidad' => $entrada['cantidad'],
                            'tipo_movimiento' => 'ENTRADA',
                            'condicion' => $condicion,
                            'monto_unitario' => 0 // Se calculará desde inventario
                        ];
                    }
                }
            }
            
            // Agregar productos de salida (entregas)
            if (isset($productosResultados['inventario_salida'])) {
                foreach ($productosResultados['inventario_salida'] as $salida) {
                    if ($salida['success']) {
                        // Determinar condición basada en el contexto
                        //$condicion = $this->determinarCondicionProducto($salida['producto_id'], 'SALIDA', $productosOriginales, $tipo);
                        $condicion = 0;
                        
                        $todosLosProductos[] = [
                            'id_producto' => $salida['producto_id'],
                            'cantidad' => $salida['cantidad'],
                            'tipo_movimiento' => 'SALIDA',
                            'condicion' => $condicion,
                            'monto_unitario' => 0 // Se calculará desde inventario
                        ];
                    }
                }
            }
            
            // NUEVO: Agregar productos de garantías recibidas (productos dañados)
            if (isset($productosResultados['garantias_recibidas'])) {
                foreach ($productosResultados['garantias_recibidas'] as $garantia) {
                    // Determinar condición basada en el contexto (productos dañados = condición 1)
                    $condicion = $this->determinarCondicionProducto($garantia['producto_id'], 'GARANTIA_ENTRANTE', $productosOriginales, $tipo);
                    
                    $todosLosProductos[] = [
                        'id_producto' => $garantia['producto_id'],
                        'cantidad' => $garantia['cantidad'],
                        'tipo_movimiento' => 'GARANTIA_ENTRANTE',
                        'condicion' => $condicion,
                        'monto_unitario' => 0 // Se calculará desde inventario
                    ];
                }
            }
            
            // Validar que $todosLosProductos sea un array
            if (!is_array($todosLosProductos)) {
                $todosLosProductos = [];
                Log::warning('$todosLosProductos no era un array, inicializando como array vacío', [
                    'pedido_id' => $pedidoId,
                    'solicitud_id' => $solicitudId,
                    'tipo' => $tipo
                ]);
            }
            
            // 3. Crear items para cada producto
            foreach ($todosLosProductos as $producto) {
                $idProducto = $producto['id_producto'];
                
                // Obtener información del producto desde inventario
                $inventarioProducto = \App\Models\inventario::find($idProducto);
                if (!$inventarioProducto) {
                    $errores[] = "Producto no encontrado con ID: $idProducto";
                    continue;
                }
                
                // Calcular monto unitario basado en precio del inventario
                
                
                // Determinar signo de cantidad según tipo de movimiento
                // SALIDA = cantidad positiva (productos que salen del inventario)
                // ENTRADA = cantidad negativa (productos que entran al inventario)
                // GARANTIA_ENTRANTE = cantidad negativa (productos dañados que entran, pero no afectan inventario)
                $cantidadConSigno = 0;
                if ($producto['tipo_movimiento'] === 'SALIDA') {
                    $cantidadConSigno = abs($producto['cantidad']); // Positiva para salidas
                } elseif ($producto['tipo_movimiento'] === 'ENTRADA') {
                    $cantidadConSigno = -abs($producto['cantidad']); // Negativa para entradas
                } elseif ($producto['tipo_movimiento'] === 'GARANTIA_ENTRANTE') {
                    $cantidadConSigno = -abs($producto['cantidad']); // Negativa para garantías entrantes (dañados)
                }

                // Usar precio y descuento de la factura original si está disponible, sino del inventario
                $descuento = 0;
                $precioUnitario = 0;
                $tasa = null;
                if (isset($itemsFacturaOriginal[$idProducto])) {
                    $montoUnitario = $itemsFacturaOriginal[$idProducto]['precio'];
                    $precioUnitario = $itemsFacturaOriginal[$idProducto]['precio_unitario'];
                    // Descuento solo aplica a items negativos (entradas/devoluciones)
                    $descuento = ($cantidadConSigno < 0) ? $itemsFacturaOriginal[$idProducto]['descuento'] : 0;
                    $tasa = $itemsFacturaOriginal[$idProducto]['tasa'];
                    Log::info('Usando precio y descuento de factura original', [
                        'producto_id' => $idProducto,
                        'precio_original' => $montoUnitario,
                        'precio_unitario' => $precioUnitario,
                        'descuento_aplicado' => $descuento,
                        'es_item_negativo' => $cantidadConSigno < 0,
                        'tasa' => $tasa
                    ]);
                } else {
                    // Producto no está en factura original, usar precio actual y tasa actual
                    $montoUnitario = $inventarioProducto->precio ?? 0;
                    $precioUnitario = $montoUnitario;
                    // Obtener tasa actual del sistema
                    $tasaActual = \App\Models\moneda::where("tipo", 1)->orderBy("id", "desc")->first();
                    $tasa = $tasaActual ? $tasaActual->valor : null;
                    Log::info('Producto no en factura original, usando precio y tasa actual', [
                        'producto_id' => $idProducto,
                        'precio_actual' => $montoUnitario,
                        'tasa_actual' => $tasa
                    ]);
                }
                $montoTotal = $montoUnitario * $cantidadConSigno;
                
                // Crear item pedido
                $item = \App\Models\items_pedidos::create([
                    'id_pedido' => $pedidoId,
                    'id_producto' => $idProducto,
                    'cantidad' => $cantidadConSigno,
                    'monto' => $montoTotal,
                    'precio_unitario' => $precioUnitario,
                    'descuento' => $descuento,
                    'tasa' => $tasa,
                    'abono' => 0,
                    'lote' => null,
                    'condicion' => $producto['condicion'],
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                // Validar que el item se creó correctamente
                if (!$item || !$item->id) {
                    $errores[] = "Error creando item para producto ID: $idProducto";
                    continue;
                }
                
                $itemsCreados[] = [
                    'item_id' => $item->id,
                    'producto_id' => $idProducto,
                    'cantidad' => $cantidadConSigno,
                    'monto_unitario' => $montoUnitario,
                    'precio_unitario' => $precioUnitario,
                    'monto_total' => $montoTotal,
                    'descuento' => $descuento,
                    'tasa' => $tasa,
                    'tipo_movimiento' => $producto['tipo_movimiento'],
                    'condicion' => $producto['condicion']
                ];
                
                Log::info('Item pedido creado para producto de garantía', [
                    'pedido_id' => $pedidoId,
                    'item_id' => $item->id,
                    'producto_id' => $idProducto,
                    'cantidad' => $cantidadConSigno,
                    'cantidad_original' => $producto['cantidad'],
                    'tipo_movimiento' => $producto['tipo_movimiento'],
                    'condicion' => $producto['condicion'] . ' (' . ($producto['condicion'] == 1 ? 'malo' : 'bueno') . ')',
                    'monto_unitario' => $montoUnitario,
                    'precio_unitario' => $precioUnitario,
                    'descuento' => $descuento,
                    'tasa' => $tasa,
                    'solicitud_id' => $solicitudId,
                    'tipo' => $tipo
                ]);
            }
            
            // 4. Validar que se crearon items
            if (empty($itemsCreados) && !empty($todosLosProductos)) {
                throw new \Exception("No se pudo crear ningún item para los productos de la garantía");
            }
            
            // 5. Si hay errores pero también items creados, log warning
            if (!empty($errores) && !empty($itemsCreados)) {
                Log::warning('Algunos items no se pudieron crear en garantía', [
                    'pedido_id' => $pedidoId,
                    'solicitud_id' => $solicitudId,
                    'errores' => $errores,
                    'items_creados' => count($itemsCreados)
                ]);
            }
            
            Log::info('Items pedido creados para garantía', [
                'pedido_id' => $pedidoId,
                'solicitud_id' => $solicitudId,
                'tipo' => $tipo,
                'total_items_creados' => count($itemsCreados),
                'total_productos_procesados' => count($todosLosProductos),
                'errores' => count($errores),
                'desglose_productos' => [
                    'entradas_inventario' => is_array($todosLosProductos) ? count(array_filter($todosLosProductos, function($p) { return $p['tipo_movimiento'] === 'ENTRADA'; })) : 0,
                    'salidas_inventario' => is_array($todosLosProductos) ? count(array_filter($todosLosProductos, function($p) { return $p['tipo_movimiento'] === 'SALIDA'; })) : 0,
                    'garantias_entrantes' => is_array($todosLosProductos) ? count(array_filter($todosLosProductos, function($p) { return $p['tipo_movimiento'] === 'GARANTIA_ENTRANTE'; })) : 0
                ]
            ]);
            
            return [
                'success' => true,
                'items_creados' => $itemsCreados,
                'total_items' => count($itemsCreados),
                'errores' => $errores
            ];
            
        } catch (\Exception $e) {
            Log::error('Error creando items pedido para garantía', [
                'pedido_id' => $pedidoId,
                'solicitud_id' => $solicitudId,
                'tipo' => $tipo,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Lanzar excepción para activar rollback si es crítico
            throw new \Exception("Error crítico creando items de pedido: " . $e->getMessage());
        }
    }
    
    /**
     * Crear pedido e items para productos de garantía (entradas y salidas)
     */
    /* private function crearItemsPedidoParaProductosGarantia($resultados, $solicitudId, $tipoGarantia, $solicitudData = [])
    {
        try {
            // Solo crear pedido si hay productos procesados
            $hayProductos = !empty($resultados['inventario_entrada']) || !empty($resultados['inventario_salida']);
            
            if (!$hayProductos) {
                Log::info('No hay productos para crear pedido', [
                    'solicitud_id' => $solicitudId,
                    'tipo' => $tipoGarantia
                ]);
                return null;
            }
            
            // 1. Crear pedido para productos
            $pedido = \App\Models\pedidos::create([
                'id_cliente' => 1, // Cliente genérico para garantías
                'id_vendedor' => session('id_usuario') ?? 1,
                'estado' => 0, // Pendiente
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            if (!$pedido || !$pedido->id) {
                throw new \Exception("Error creando pedido para productos de garantía");
            }
            
            // 2. Crear items usando el método principal
            $productosOriginales = $solicitudData['productos_procesados'] ?? [];
            
            $resultadoItems = $this->crearItemsPedidoParaGarantia(
                $pedido->id, 
                $solicitudId, 
                $resultados, 
                $tipoGarantia,
                $productosOriginales
            );
            
            if (!$resultadoItems['success']) {
                throw new \Exception("Error creando items del pedido: " . implode(', ', $resultadoItems['errores'] ?? []));
            }
            
            // 3. Actualizar estado del pedido a completado
            $pedido->update(['estado' => 1]);
            
            Log::info('Pedido de productos creado exitosamente', [
                'pedido_id' => $pedido->id,
                'solicitud_id' => $solicitudId,
                'tipo' => $tipoGarantia,
                'items_creados' => $resultadoItems['total_items']
            ]);
            
            return [
                'success' => true,
                'pedido_id' => $pedido->id,
                'items_resultado' => $resultadoItems
            ];
            
        } catch (\Exception $e) {
            Log::error('Error creando pedido para productos de garantía', [
                'solicitud_id' => $solicitudId,
                'tipo' => $tipoGarantia,
                'error' => $e->getMessage()
            ]);
            
            // Lanzar excepción para activar rollback
            throw new \Exception("Error crítico creando pedido de productos: " . $e->getMessage());
        }
    } */

    /**
     * Determinar la condición del producto basada en el contexto
     * 
     * @param int $productoId ID del producto
     * @param string $tipoMovimiento 'ENTRADA' o 'SALIDA'
     * @param array $productosOriginales Datos originales de productos con estado
     * @param string $tipoGarantia Tipo de garantía para contexto
     * @return int Condición: 1=producto malo, 2=producto bueno
     */
    private function determinarCondicionProducto($productoId, $tipoMovimiento, $productosOriginales, $tipoGarantia)
    {
        
        try {
            // Buscar información del producto en los datos originales
            $productoOriginal = null;
            foreach ($productosOriginales as $producto) {
                if (($producto['id_producto'] ?? 0) == $productoId) {
                    $productoOriginal = $producto;
                    break;
                }
            }
            
            // Si encontramos el producto original, usar su estado (solo para ENTRADA y GARANTIA_ENTRANTE)
            if ($productoOriginal) {
                $estado = strtoupper($productoOriginal['estado'] ?? 'BUENO');
                $tipo = strtoupper($productoOriginal['tipo'] ?? 'salida');
                
                // Determinar condición basada en estado y tipo (solo para entradas)
                $condicion = ($estado === 'MALO' || $estado === 'DAÑADO' || $estado === 'DEFECTUOSO') ? 1 : 2;
                
                Log::debug('Condición determinada por estado del producto', [
                    'producto_id' => $productoId,
                    'estado_original' => $estado,
                    'tipo_original' => $tipo,
                    'tipo_movimiento' => $tipoMovimiento,
                    'condicion_asignada' => $condicion . ' (' . ($condicion == 1 ? 'malo' : 'bueno') . ')'
                ]);
                
                return $condicion;
            }
            
            // Si no tenemos información específica, usar lógica por defecto
            $condicionDefecto = 2; // Por defecto bueno
            $razon = '';
            
            if ($tipoMovimiento === 'ENTRADA') {
                // Entradas: productos que devuelve el cliente
                if (strpos($tipoGarantia, 'DEVOLUCION') !== false) {
                    $condicionDefecto = 2; // Devoluciones normalmente son productos buenos
                    $razon = 'devolución - producto bueno';
                } else {
                    $condicionDefecto = 1; // Garantías normalmente son productos defectuosos
                    $razon = 'garantía - producto defectuoso';
                }
            } elseif ($tipoMovimiento === 'GARANTIA_ENTRANTE') {
                // Garantías entrantes: productos dañados que se reciben
                $condicionDefecto = 1; // Productos dañados = condición 1
                $razon = 'garantía entrante - producto dañado';
            } else if ($tipoMovimiento === 'SALIDA') {
                // Salidas: productos que entrega la tienda
                $condicionDefecto = 0; // Productos que entregamos deben estar en buen estado
                $razon = 'salida - producto bueno';
            }
            
            Log::debug('Condición determinada por lógica por defecto', [
                'producto_id' => $productoId,
                'tipo_movimiento' => $tipoMovimiento,
                'tipo_garantia' => $tipoGarantia,
                'condicion_asignada' => $condicionDefecto . ' (' . ($condicionDefecto == 1 ? 'malo' : 'bueno') . ')',
                'razon' => $razon
            ]);
            
            return $condicionDefecto;
            
        } catch (\Exception $e) {
            Log::warning('Error determinando condición de producto, usando valor por defecto', [
                'producto_id' => $productoId,
                'tipo_movimiento' => $tipoMovimiento,
                'error' => $e->getMessage()
            ]);
            
            // Por defecto, asumir producto bueno
            return 2;
        }
    }

    /**
     * Crear items_pedidos para pagos sin productos (solo diferencias monetarias)
     */
    private function crearItemsPedidoParaGarantiaSinProductos($pedidoId, $solicitudId, $monto, $tipo)
    {
        try {
            // 1. Validar datos requeridos
            if (!$pedidoId || !$solicitudId) {
                throw new \Exception("Pedido ID o Solicitud ID no especificados");
            }
            
            // 2. Verificar que el producto genérico existe (ID=1)
            $productoGenerico = \App\Models\inventario::find(1);
            if (!$productoGenerico) {
                Log::warning('Producto genérico ID=1 no encontrado, usando ID=1 de todas formas');
            }
            
            // 3. Crear un item simbólico para trazabilidad monetaria
            $item = \App\Models\items_pedidos::create([
                'id_pedido' => $pedidoId,
                'id_producto' => 1, // Producto genérico para diferencias monetarias
                'cantidad' => 1,
                'monto' => abs($monto), // Usar valor absoluto
                'descuento' => 0,
                'abono' => 0,
                'lote' => null,
                'condicion' => 2, // 2=bueno (para transacciones monetarias)
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // 4. Validar que el item se creó correctamente
            if (!$item || !$item->id) {
                throw new \Exception("Error creando item genérico para diferencia monetaria");
            }
            
            Log::info('Item genérico creado para diferencia monetaria', [
                'pedido_id' => $pedidoId,
                'item_id' => $item->id,
                'solicitud_id' => $solicitudId,
                'tipo' => $tipo,
                'monto' => $monto
            ]);
            
            return [
                'success' => true,
                'item_id' => $item->id,
                'tipo' => 'GENERICO_MONETARIO'
            ];
            
        } catch (\Exception $e) {
            Log::error('Error creando item genérico para garantía', [
                'pedido_id' => $pedidoId,
                'solicitud_id' => $solicitudId,
                'tipo' => $tipo,
                'monto' => $monto,
                'error' => $e->getMessage()
            ]);
            
            // Lanzar excepción para activar rollback
            throw new \Exception("Error crítico creando item genérico: " . $e->getMessage());
        }
    }
    
    /**
     * Convertir tipo de pago a formato del sistema
     * Tipos según PagoPedidosController:
     * 1: Transferencia
     * 2: Débito 
     * 3: Efectivo
     * 4: Crédito
     * 5: Biopago
     * 6: Vuelto
     */
    private function convertirTipoPago($tipo)
    {
        switch (strtoupper($tipo)) {
            case 'TRANSFERENCIA_BS':
            case 'TRANSFERENCIA_USD':
            case 'TRANSFERENCIA':
                return 1; // Transferencia
            case 'DEBITO':
            case 'TARJETA_DEBITO':
                return 2; // Débito
            case 'EFECTIVO_BS':
            case 'EFECTIVO_USD':
            case 'EFECTIVO':
                return 3; // Efectivo
            case 'CREDITO':
            case 'CREDITO_BS':
            case 'CREDITO_USD':
                return 4; // Crédito
            case 'PAGO_MOVIL':
            case 'BIOPAGO':
                return 5; // Biopago
            case 'VUELTO':
                return 6; // Vuelto
            default:
                return 3; // Por defecto efectivo
        }
    }

    /**
     * Crear items en el pedido unificado existente (versión para pedido único)
     */
    private function crearItemsPedidoParaProductosGarantiaUnificado($pedidoId, $resultados, $solicitudId, $tipoGarantia, $solicitudData = [])
    {
        \Log::info('crearItemsPedidoParaProductosGarantiaUnificado pedidoId', [$pedidoId]);
        \Log::info('crearItemsPedidoParaProductosGarantiaUnificado resultados', [$resultados]);
        \Log::info('crearItemsPedidoParaProductosGarantiaUnificado solicitudId', [$solicitudId]);
        \Log::info('crearItemsPedidoParaProductosGarantiaUnificado tipoGarantia', [$tipoGarantia]);
        \Log::info('crearItemsPedidoParaProductosGarantiaUnificado solicitudData', [$solicitudData]);
        try {
            // Solo procesar si hay productos (incluyendo garantías recibidas)
            $hayProductos = !empty($resultados['inventario_entrada']) || 
                           !empty($resultados['inventario_salida']) || 
                           !empty($resultados['garantias_recibidas']);
            
            if (!$hayProductos) {
                Log::info('No hay productos para crear items en pedido unificado', [
                    'pedido_id' => $pedidoId,
                    'solicitud_id' => $solicitudId,
                    'tipo' => $tipoGarantia
                ]);
                return [
                    'success' => true,
                    'items_creados' => 0,
                    'mensaje' => 'Sin productos para procesar'
                ];
            }
            
            // Usar el método principal para crear items en el pedido existente
            $productosOriginales = $solicitudData['productos_procesados'] ?? [];
            
            $resultadoItems = $this->crearItemsPedidoParaGarantia(
                $pedidoId, 
                $solicitudId, 
                $resultados, 
                $tipoGarantia,
                $productosOriginales,
                $solicitudData
            );
            /* \Log::info('productosOriginales', ['productosOriginales' => $productosOriginales]);
            \Log::info('resultados', ['resultados' => $resultados]); */
            
            if (!$resultadoItems['success']) {
                throw new \Exception("Error creando items del pedido unificado: " . implode(', ', $resultadoItems['errores'] ?? []));
            }
            
            /* Log::info('Items agregados al pedido unificado exitosamente', [
                'pedido_id' => $pedidoId,
                'solicitud_id' => $solicitudId,
                'tipo' => $tipoGarantia,
                'items_creados' => $resultadoItems['total_items']
            ]); */
            
            return [
                'success' => true,
                'items_resultado' => $resultadoItems,
                'items_creados' => $resultadoItems['total_items'],
                'pedido_id' => $pedidoId
            ];
            
        } catch (\Exception $e) {
            Log::error('Error creando items en pedido unificado', [
                'pedido_id' => $pedidoId,
                'solicitud_id' => $solicitudId,
                'tipo' => $tipoGarantia,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'errores' => [$e->getMessage()]
            ];
        }
    }
} 