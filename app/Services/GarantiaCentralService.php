<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use App\Models\garantia;
use App\Models\inventario;
use App\Models\pedidos;
use App\Models\items_pedidos;
use App\Models\movimientosInventariounitario;
use App\Http\Controllers\sendCentral;

class GarantiaCentralService
{
    private $sendCentral;

    public function __construct()
    {
        $this->sendCentral = new sendCentral();
    }

    /**
     * Enviar una nueva solicitud de garantía a arabitocentral 
     * NOTA: Este método ahora usa el endpoint unificado
     */
    public function enviarSolicitudGarantia($datos)
    {
        try {
            // Preparar los datos para enviar a central usando el endpoint unificado
            $multipartData = [
                [
                    'name' => 'factura_venta_id',
                    'contents' => $datos['factura_venta_id']
                ],
                [
                    'name' => 'tipo_solicitud',
                    'contents' => $datos['tipo_solicitud']
                ],
                [
                    'name' => 'motivo_devolucion',
                    'contents' => $datos['motivo_devolucion'] ?? ''
                ],
                [
                    'name' => 'dias_transcurridos_compra',
                    'contents' => $datos['dias_transcurridos_compra'] ?? 0
                ],
                [
                    'name' => 'trajo_factura',
                    'contents' => $datos['trajo_factura'] ? 'true' : 'false'
                ],
                [
                    'name' => 'motivo_no_factura',
                    'contents' => $datos['motivo_no_factura'] ?? ''
                ],
                [
                    'name' => 'detalles_adicionales',
                    'contents' => $datos['detalles_adicionales'] ?? ''
                ],
                [
                    'name' => 'monto_devolucion_dinero',
                    'contents' => $datos['monto_devolucion_dinero'] ?? 0
                ],
                [
                    'name' => 'codigo_origen',
                    'contents' => $this->getSucursalId()
                ],
                [
                    'name' => 'cliente',
                    'contents' => json_encode($datos['cliente'])
                ],
                [
                    'name' => 'cajero',
                    'contents' => json_encode($datos['cajero'])
                ],
                [
                    'name' => 'supervisor',
                    'contents' => json_encode($datos['supervisor'])
                ],
                [
                    'name' => 'dici',
                    'contents' => json_encode($datos['dici'] ?? null)
                ]
            ];

            // Agregar foto de factura si existe
            if (isset($datos['foto_factura']) && $datos['foto_factura']) {
                $multipartData[] = [
                    'name' => 'foto_factura',
                    'contents' => fopen($datos['foto_factura']->getPathname(), 'r'),
                    'filename' => $datos['foto_factura']->getClientOriginalName()
                ];
            }

            // Agregar fotos de productos si existen
            if (isset($datos['fotos_productos']) && is_array($datos['fotos_productos'])) {
                foreach ($datos['fotos_productos'] as $index => $foto) {
                    $multipartData[] = [
                        'name' => "fotos_productos[{$index}]",
                        'contents' => fopen($foto->getPathname(), 'r'),
                        'filename' => $foto->getClientOriginalName()
                    ];
                }
            }

            // Enviar usando HTTP multipart al endpoint unificado
            $response = \Http::timeout(60)->asMultipart()->post(
                $this->sendCentral->path() . "/api/garantias/registrar",
                $multipartData
            );



            if ($response->ok()) {
                $responseData = $response->json();
                
                if ($responseData['success'] ?? false) {
                    return [
                        'success' => true,
                        'solicitud_id' => $responseData['data']['solicitud_id'] ?? null,
                        'estatus' => $responseData['data']['estatus'] ?? 'PENDIENTE'
                    ];
                }
            }

            return [
                'success' => false,
                'message' => 'Error al comunicarse con el servidor central',
                'error' => $response->json()['error'] ?? 'Error desconocido'
            ];

        } catch (\Exception $e) {
            Log::error('Excepción al enviar solicitud a central', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Error de conexión con el servidor central',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Enviar solicitud de garantía CON métodos de devolución a arabitocentral
     * NOTA: Este método ahora usa el endpoint unificado
     */
    public function enviarSolicitudGarantiaConMetodos($datos)
    {
        try {
            // Preparar los datos para enviar a central usando HTTP multipart para manejar archivos
            $multipartData = [
                [
                    'name' => 'caso_uso',
                    'contents' => $datos['caso_uso']
                ],
                [
                    'name' => 'productos',
                    'contents' => json_encode($datos['productos'])
                ],
                [
                    'name' => 'garantia_data',
                    'contents' => json_encode($datos['garantia_data'])
                ],
                [
                    'name' => 'metodos_devolucion',
                    'contents' => json_encode($datos['metodos_devolucion'])
                ],
                [
                    'name' => 'transferencias_validadas',
                    'contents' => json_encode($datos['transferencias_validadas'])
                ],
                [
                    'name' => 'factura_venta_id',
                    'contents' => $datos['factura_venta_id']
                ],
                [
                    'name' => 'monto_total_devolucion',
                    'contents' => $datos['monto_total_devolucion']
                ],
                [
                    'name' => 'fecha_solicitud',
                    'contents' => now()->toDateTimeString()
                ],
                [
                    'name' => 'codigo_origen',
                    'contents' => $this->getSucursalId()
                ],
                [
                    'name' => 'usuario_id',
                    'contents' => session("id_usuario") ?? 1
                ],
                [
                    'name' => 'tipo_solicitud',
                    'contents' => $datos['caso_uso'] <= 2 ? 'GARANTIA' : 'DEVOLUCION'
                ],
                [
                    'name' => 'requiere_aprobacion',
                    'contents' => 'true'
                ],
                [
                    'name' => 'incluye_metodos_pago',
                    'contents' => 'true'
                ],
                [
                    'name' => 'monto_productos_devueltos',
                    'contents' => $datos['monto_productos_devueltos'] ?? 0
                ],
                [
                    'name' => 'monto_productos_entregados', 
                    'contents' => $datos['monto_productos_entregados'] ?? 0
                ],
                [
                    'name' => 'diferencia_neta',
                    'contents' => $datos['diferencia_neta'] ?? 0
                ],
                [
                    'name' => 'hay_diferencia_positiva',
                    'contents' => ($datos['hay_diferencia_positiva'] ?? false) ? 'true' : 'false'
                ],
                [
                    'name' => 'hay_diferencia_negativa',
                    'contents' => ($datos['hay_diferencia_negativa'] ?? false) ? 'true' : 'false'
                ],
                [
                    'name' => 'modo_traslado_interno',
                    'contents' => ($datos['modo_traslado_interno'] ?? false) ? 'true' : 'false'
                ]
            ];

            // Agregar foto de factura si existe
            if (isset($datos['foto_factura']) && $datos['foto_factura']) {
                $multipartData[] = [
                    'name' => 'foto_factura',
                    'contents' => fopen($datos['foto_factura']->getPathname(), 'r'),
                    'filename' => $datos['foto_factura']->getClientOriginalName()
                ];
            }

            // Agregar fotos de productos si existen
            if (isset($datos['fotos_productos']) && is_array($datos['fotos_productos'])) {
                foreach ($datos['fotos_productos'] as $index => $foto) {
                    $multipartData[] = [
                        'name' => "fotos_productos[{$index}]",
                        'contents' => fopen($foto->getPathname(), 'r'),
                        'filename' => $foto->getClientOriginalName()
                    ];
                }
            }

            // Agregar descripciones de fotos si existen
            if (isset($datos['fotos_descripciones']) && is_array($datos['fotos_descripciones'])) {
                foreach ($datos['fotos_descripciones'] as $index => $descripcion) {
                    $multipartData[] = [
                        'name' => "fotos_descripciones[{$index}]",
                        'contents' => $descripcion
                    ];
                }
            }

            // Enviar usando HTTP multipart al endpoint unificado
            $response = \Http::timeout(60)->asMultipart()->post(
                $this->sendCentral->path() . "/api/garantias/registrar",
                $multipartData
            );



            if ($response->ok()) {
                $responseData = $response->json();
                return [
                    'success' => true,
                    'solicitud_id' => $responseData['data']['solicitud_id'] ?? null,
                    'estatus' => $responseData['data']['estatus'] ?? 'PENDIENTE',
                    'mensaje' => 'Solicitud enviada exitosamente a central con métodos de devolución y fotos'
                ];
            }

            $errorBody = $response->body();
            Log::error('Error al enviar solicitud con métodos a central', [
                'status' => $response->status(),
                'body' => $errorBody
            ]);

            return [
                'success' => false,
                'message' => 'Error al comunicarse con el servidor central para enviar solicitud con métodos',
                'error' => $errorBody
            ];

        } catch (\Exception $e) {
            Log::error('Excepción al enviar solicitud con métodos a central', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Error de conexión con el servidor central',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Reversar garantía en arabitocentral cuando se anula un pedido
     */
    public function reversarGarantia($solicitudCentralId, $detallesReversion = null)
    {
        try {
            // Preparar datos de reversión
            $payload = [
                'solicitud_id' => $solicitudCentralId,
                'motivo_reversion' => 'Anulación de pedido asociado',
                'detalles_reversion' => $detallesReversion,
                'fecha_reversion' => now()->toDateTimeString(),
                'usuario_reversion' => session("id_usuario") ?? 1,
                'codigo_origen' => $this->getSucursalId(),
            ];

            // Usar sendCentral para enviar la reversión
            $response = $this->sendCentral->reversarGarantiaCentral($payload);

            if ($response['success'] ?? false) {
                return [
                    'success' => true,
                    'solicitud_id' => $solicitudCentralId,
                    'estatus' => 'REVERSADA',
                    'mensaje' => 'Garantía reversada exitosamente en central'
                ];
            }

            return [
                'success' => false,
                'message' => 'Error al reversar garantía en central',
                'error' => $response['error'] ?? 'Error desconocido'
            ];

        } catch (\Exception $e) {
            Log::error('Error al reversar garantía en central', [
                'solicitud_id' => $solicitudCentralId,
                'error' => $e->getMessage(),
                'detalles' => $detallesReversion
            ]);

            return [
                'success' => false,
                'message' => 'Error de conexión con el servidor central',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Consultar solicitudes pendientes de ejecución desde arabitocentral
     */
    public function consultarSolicitudesPendientes($fecha = null)
    {
        try {
            // Usar sendCentral para obtener garantías aprobadas
            $response = $this->sendCentral->getGarantiasApprovedFromCentral();

            if ($response['success'] ?? false) {
                $solicitudes = $response['data'] ?? [];
                
                // Filtrar por fecha si se especifica
                if ($fecha) {
                    $solicitudes = array_filter($solicitudes, function($solicitud) use ($fecha) {
                        return isset($solicitud['fecha_aprobacion']) && 
                               date('Y-m-d', strtotime($solicitud['fecha_aprobacion'])) >= $fecha;
                    });
                }

                return [
                    'success' => true,
                    'solicitudes' => $solicitudes
                ];
            }

            return [
                'success' => false,
                'message' => 'Error al consultar solicitudes pendientes',
                'error' => $response['error'] ?? 'Error desconocido'
            ];

        } catch (\Exception $e) {
            Log::error('Error al consultar solicitudes pendientes', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Error de conexión con el servidor central',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Consultar el estado de una solicitud específica
     */
    public function consultarEstadoSolicitud($solicitudId)
    {
        try {
            // Usar sendCentral para obtener garantía específica
            $response = $this->sendCentral->getGarantiaFromCentral($solicitudId);

            if ($response['success'] ?? false) {
                return [
                    'success' => true,
                    'solicitud' => $response['data'] ?? null
                ];
            }

            return [
                'success' => false,
                'message' => 'Solicitud no encontrada',
                'error' => $response['error'] ?? 'Error desconocido'
            ];

        } catch (\Exception $e) {
            Log::error('Error al consultar estado de solicitud', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Error de conexión con el servidor central',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Marcar una solicitud como finalizada en arabitocentral
     */
    public function finalizarSolicitud($solicitudId, $detallesEjecucion = null)
    {
        try {
            // Usar sendCentral para marcar garantía como completada
            $response = $this->sendCentral->markGarantiaAsCompletedCentral($solicitudId, $detallesEjecucion);

            if ($response['success'] ?? false) {
                return [
                    'success' => true,
                    'solicitud_id' => $solicitudId,
                    'estatus' => 'FINALIZADA'
                ];
            }

            return [
                'success' => false,
                'message' => 'Error al finalizar solicitud',
                'error' => $response['error'] ?? 'Error desconocido'
            ];

        } catch (\Exception $e) {
            Log::error('Error al finalizar solicitud', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Error de conexión con el servidor central',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener estadísticas de garantías para la sucursal
     */
    public function obtenerEstadisticas()
    {
        try {
            $response = $this->sendCentral->getGarantiaStatsFromCentral();

            if ($response['success'] ?? false) {
                return [
                    'success' => true,
                    'estadisticas' => $response['data'] ?? []
                ];
            }

            return [
                'success' => false,
                'message' => 'Error al obtener estadísticas',
                'error' => $response['error'] ?? 'Error desconocido'
            ];

        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas de garantías', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Error de conexión con el servidor central',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verificar conectividad con arabitocentral
     */
    public function verificarConectividad()
    {
        try {
            return $this->sendCentral->checkCentralConnection();
        } catch (\Exception $e) {
            Log::error('Error al verificar conectividad', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Ejecutar una garantía aprobada localmente
     */
    public function ejecutarGarantia($garantiaLocal)
    {
        try {
            \DB::beginTransaction();

            switch ($garantiaLocal->caso_uso) {
                case 1:
                    $resultado = $this->ejecutarCaso1($garantiaLocal);
                    break;
                case 2:
                    $resultado = $this->ejecutarCaso2($garantiaLocal);
                    break;
                case 3:
                    $resultado = $this->ejecutarCaso3($garantiaLocal);
                    break;
                case 4:
                    $resultado = $this->ejecutarCaso4($garantiaLocal);
                    break;
                case 5:
                    $resultado = $this->ejecutarCaso5($garantiaLocal);
                    break;
                default:
                    throw new \Exception("Caso de uso no válido: {$garantiaLocal->caso_uso}");
            }

            if ($resultado['success']) {
                // Marcar como ejecutada localmente
                $garantiaLocal->marcarComoEjecutada();

                // Finalizar en arabitocentral
                $finalizacionResult = $this->finalizarSolicitud($garantiaLocal->solicitud_central_id);
                
                if (!$finalizacionResult['success']) {
                    Log::warning('Error al finalizar solicitud en central', [
                        'solicitud_id' => $garantiaLocal->solicitud_central_id,
                        'error' => $finalizacionResult['message']
                    ]);
                }

                \DB::commit();
                
                return [
                    'success' => true,
                    'message' => 'Garantía ejecutada exitosamente',
                    'movimientos' => $resultado['movimientos'] ?? []
                ];
            }

            \DB::rollback();
            return $resultado;

        } catch (\Exception $e) {
            \DB::rollback();
            
            Log::error('Error al ejecutar garantía', [
                'garantia_id' => $garantiaLocal->id,
                'caso_uso' => $garantiaLocal->caso_uso,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Error al ejecutar la garantía',
                'error' => $e->getMessage()
            ];
        }
    }

    // Métodos privados para ejecutar cada caso de uso

    private function ejecutarCaso1($garantia)
    {
        // Caso 1: Entra garantía de cliente y sale producto bueno
        
        // Disminuir inventario del producto bueno que sale
        $producto = inventario::find($garantia->id_producto);
        if (!$producto) {
            throw new \Exception('Producto no encontrado');
        }

        if ($producto->cantidad < $garantia->cantidad_salida) {
            throw new \Exception('No hay suficiente inventario disponible');
        }

        $producto->cantidad -= $garantia->cantidad_salida;
        $producto->save();

        // Registrar movimiento de inventario
        movimientosInventariounitario::create([
            'id_inventario' => $producto->id,
            'cantidad' => -$garantia->cantidad_salida,
            'motivo' => "Garantía - Salida producto bueno (Caso 1) - Garantía #{$garantia->id}",
            'fecha' => now(),
            'tipo' => 'GARANTIA_SALIDA'
        ]);

        return [
            'success' => true,
            'movimientos' => [
                'inventario_actualizado' => $producto->id,
                'cantidad_salida' => $garantia->cantidad_salida
            ]
        ];
    }

    private function ejecutarCaso2($garantia)
    {
        // Caso 2: Entra garantía de cliente y sale dinero
        
        // Crear pedido para salida de dinero
        $pedido = pedidos::create([
            'monto' => $garantia->monto_devolucion_dinero,
            'descripcion' => "Devolución de dinero por garantía #{$garantia->id}",
            'fecha' => now(),
            'fecha_factura' => now(),
            'tipo' => 'DEVOLUCION_GARANTIA'
        ]);

        // Crear item del pedido
        items_pedidos::create([
            'id_pedido' => $pedido->id,
            'descripcion' => "Devolución garantía - " . $garantia->motivo,
            'cantidad' => 1,
            'monto' => $garantia->monto_devolucion_dinero
        ]);

        // Actualizar la garantía con el ID del pedido
        $garantia->id_pedido = $pedido->id;
        $garantia->save();

        return [
            'success' => true,
            'movimientos' => [
                'pedido_creado' => $pedido->id,
                'monto_devolucion' => $garantia->monto_devolucion_dinero
            ]
        ];
    }

    private function ejecutarCaso3($garantia)
    {
        // Caso 3: Entra producto bueno de cliente y sale otro producto bueno (Devolución)
        
        // Aumentar inventario del producto que entra
        $productoEntra = inventario::find($garantia->id_producto);
        if (!$productoEntra) {
            throw new \Exception('Producto que entra no encontrado');
        }

        $productoEntra->cantidad += $garantia->cantidad;
        $productoEntra->save();

        // Registrar movimiento de entrada
        movimientosInventariounitario::create([
            'id_inventario' => $productoEntra->id,
            'cantidad' => $garantia->cantidad,
            'motivo' => "Devolución - Entrada producto bueno (Caso 3) - Garantía #{$garantia->id}",
            'fecha' => now(),
            'tipo' => 'DEVOLUCION_ENTRADA'
        ]);

        // Si hay producto de salida diferente, procesarlo
        if ($garantia->cantidad_salida > 0) {
            $productoSale = inventario::find($garantia->id_producto); // Asumir mismo producto por ahora
            
            if ($productoSale->cantidad < $garantia->cantidad_salida) {
                throw new \Exception('No hay suficiente inventario para el producto de salida');
            }

            $productoSale->cantidad -= $garantia->cantidad_salida;
            $productoSale->save();

            // Registrar movimiento de salida
            movimientosInventariounitario::create([
                'id_inventario' => $productoSale->id,
                'cantidad' => -$garantia->cantidad_salida,
                'motivo' => "Devolución - Salida producto bueno (Caso 3) - Garantía #{$garantia->id}",
                'fecha' => now(),
                'tipo' => 'DEVOLUCION_SALIDA'
            ]);
        }

        return [
            'success' => true,
            'movimientos' => [
                'producto_entrada' => $productoEntra->id,
                'cantidad_entrada' => $garantia->cantidad,
                'cantidad_salida' => $garantia->cantidad_salida
            ]
        ];
    }

    private function ejecutarCaso4($garantia)
    {
        // Caso 4: Entra producto bueno de cliente y sale dinero (Devolución)
        
        // Aumentar inventario del producto que entra
        $producto = inventario::find($garantia->id_producto);
        if (!$producto) {
            throw new \Exception('Producto no encontrado');
        }

        $producto->cantidad += $garantia->cantidad;
        $producto->save();

        // Registrar movimiento de inventario
        movimientosInventariounitario::create([
            'id_inventario' => $producto->id,
            'cantidad' => $garantia->cantidad,
            'motivo' => "Devolución - Entrada producto bueno (Caso 4) - Garantía #{$garantia->id}",
            'fecha' => now(),
            'tipo' => 'DEVOLUCION_ENTRADA'
        ]);

        // Crear pedido para salida de dinero
        $pedido = pedidos::create([
            'monto' => $garantia->monto_devolucion_dinero,
            'descripcion' => "Devolución de dinero por producto #{$garantia->id}",
            'fecha' => now(),
            'fecha_factura' => now(),
            'tipo' => 'DEVOLUCION_DINERO'
        ]);

        // Crear item del pedido
        items_pedidos::create([
            'id_pedido' => $pedido->id,
            'descripcion' => "Devolución producto - " . $garantia->motivo_devolucion,
            'cantidad' => 1,
            'monto' => $garantia->monto_devolucion_dinero
        ]);

        // Actualizar la garantía con el ID del pedido
        $garantia->id_pedido = $pedido->id;
        $garantia->save();

        return [
            'success' => true,
            'movimientos' => [
                'inventario_actualizado' => $producto->id,
                'cantidad_entrada' => $garantia->cantidad,
                'pedido_creado' => $pedido->id,
                'monto_devolucion' => $garantia->monto_devolucion_dinero
            ]
        ];
    }

    private function ejecutarCaso5($garantia)
    {
        // Caso 5: Producto dañado internamente (sin cliente)
        
        // Disminuir inventario del producto dañado
        $producto = inventario::find($garantia->id_producto);
        if (!$producto) {
            throw new \Exception('Producto no encontrado');
        }

        if ($producto->cantidad < $garantia->cantidad) {
            throw new \Exception('No hay suficiente inventario disponible');
        }

        $producto->cantidad -= $garantia->cantidad;
        $producto->save();

        // Registrar movimiento de inventario
        movimientosInventariounitario::create([
            'id_inventario' => $producto->id,
            'cantidad' => -$garantia->cantidad,
            'motivo' => "Producto dañado internamente (Caso 5) - Garantía #{$garantia->id}",
            'fecha' => now(),
            'tipo' => 'PRODUCTO_DAÑADO'
        ]);

        return [
            'success' => true,
            'movimientos' => [
                'inventario_actualizado' => $producto->id,
                'cantidad_dañada' => $garantia->cantidad
            ]
        ];
    }

    /**
     * Obtener código origen de sucursal actual
     */
    private function getSucursalId()
    {
        // Usar el código origen de la sucursal, no el ID
        return $this->sendCentral->getOrigen();
    }
} 