<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\pedidos;
use App\Models\items_pedidos;
use App\Models\pago_pedidos;
use App\Models\inventario;
use App\Models\movimientosInventariounitario;
use App\Http\Controllers\sendCentral;

class GarantiaReversoController extends Controller
{
    protected $sendCentral;

    public function __construct()
    {
        $this->sendCentral = new sendCentral();
    }

    /**
     * Buscar una solicitud de garantía por número en arabitocentral
     */
    public function buscarSolicitud(Request $request)
    {
        try {
            $numero = $request->input('numero');
            
            if (empty($numero)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debe especificar un número de solicitud'
                ], 400);
            }

            // Obtener el código de origen de la sucursal
            
            // Buscar en arabitocentral mediante API
            $response = $this->sendCentral->buscarSolicitudGarantiaCentral($numero);
            
            if (!$response['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $response['message'] ?? 'No se encontró la solicitud de garantía'
                ], 404);
            }

            $solicitud = $response['data'];

            // Verificar que la solicitud esté en un estado válido para reverso
            if (!in_array($solicitud['estatus'], ['APROBADA', 'FINALIZADA'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden revertir solicitudes aprobadas o finalizadas'
                ], 400);
            }

            // Verificar que no haya sido revertida anteriormente
            if ($solicitud['estatus'] === 'REVERSADA') {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta solicitud ya ha sido revertida anteriormente'
                ], 400);
            }

            return response()->json([
                'success' => true,
                'solicitud' => $solicitud
            ]);

        } catch (\Exception $e) {
            Log::error('Error buscando solicitud de garantía', [
                'numero' => $request->input('numero'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Solicitar reverso de una garantía
     */
    public function solicitarReverso(Request $request)
    {
        try {
            $request->validate([
                'solicitud_id' => 'required|integer',
                'motivo' => 'required|string|max:500',
                'detalles' => 'nullable|string|max:1000',
                'datos_originales' => 'required|array'
            ]);

            $solicitudId = $request->input('solicitud_id');
            $motivo = $request->input('motivo');
            $detalles = $request->input('detalles');
            $datosOriginales = $request->input('datos_originales');

            // Enviar solicitud de reverso a central
            $resultado = $this->enviarSolicitudReversoCentral($solicitudId, $motivo, $detalles, $datosOriginales);

            if (!$resultado['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $resultado['message']
                ], 400);
            }


            return response()->json([
                'success' => true,
                'message' => 'Solicitud de reverso enviada exitosamente',
                'solicitud_central_id' => $resultado['solicitud_id'] ?? null
            ]);

        } catch (\Exception $e) {
            

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Enviar solicitud de reverso a central
     */
    private function enviarSolicitudReversoCentral($solicitudId, $motivo, $detalles, $datosOriginales)
    {
        try {
            $data = [
                'solicitud_garantia_id' => $solicitudId,
                'motivo_solicitud' => $motivo,
                'detalles' => $detalles,
                'datos_originales' => $datosOriginales,
                'sucursal_id' => $this->getSucursalId(),
                'fecha_solicitud' => now()->toISOString()
            ];

            // Enviar a través de sendCentral
            $response = $this->sendCentral->enviarSolicitudReverso($data);

            if ($response && isset($response['estado']) && $response['estado'] === true) {
                return [
                    'success' => true,
                    'solicitud_id' => $response['id'] ?? null,
                    'message' => $response['msj'] ?? 'Solicitud enviada exitosamente'
                ];
            }

            return [
                'success' => false,
                'message' => $response['msj'] ?? 'Error desconocido al enviar solicitud a central'
            ];

        } catch (\Exception $e) {
            Log::error('Error enviando solicitud de reverso a central', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Error de conexión con central: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Ejecutar reverso localmente (llamado desde central)
     */
    

    /**
     * Ejecutar reverso completo para solicitudes finalizadas
     */
    private function ejecutarReversoCompleto($solicitudId)
    {
        $solicitudId = $solicitudId["solicitud_garantia"];
        try {
            $resultado = [
                'success' => true,
                'message' => 'Reverso completo ejecutado exitosamente',
                'operaciones' => []
            ];
            $id_pedido = $solicitudId["id_pedido_insucursal"]; 
            

            // 1. Eliminar pagos relacionados
            $pagosEliminados = $this->eliminarPagosGarantia($id_pedido);
            $resultado['operaciones'][] = $pagosEliminados;

            // 2. Eliminar items de pedidos
            $itemsEliminados = $this->eliminarItemsPedidos($id_pedido);
            $resultado['operaciones'][] = $itemsEliminados;

            // 3. Eliminar pedidos
            $pedidosEliminados = $this->eliminarPedidos($id_pedido);
            $resultado['operaciones'][] = $pedidosEliminados;

            // 4. Restaurar inventario
            $productos_con_datos = json_decode($solicitudId["productos_data"], true);
            $inventarioRestaurado = $this->restaurarInventario($productos_con_datos,$solicitudId["id"]);
            $resultado['operaciones'][] = $inventarioRestaurado;

            // Verificar que todas las operaciones fueron exitosas
            foreach ($resultado['operaciones'] as $operacion) {
                if (!$operacion['success']) {
                    $resultado['success'] = false;
                    $resultado['message'] = 'Error en operación: ' . $operacion['message'];
                    break;
                }
            }

            return $resultado;

        } catch (\Exception $e) {
            Log::error('Error en ejecutarReversoCompleto', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Error ejecutando reverso completo: ' . $e->getMessage()." ".$e->getLine()
            ];
        }
    }

    /**
     * Eliminar pagos relacionados con la garantía
     */
    private function eliminarPagosGarantia($solicitudId)
    {
        try {
            // Buscar pedidos relacionados con la solicitud de garantía
            $pedidos = pedidos::where('id', $solicitudId)->get();
            
            $pagosEliminados = 0;
            foreach ($pedidos as $pedido) {
                $pagos = pago_pedidos::where('id_pedido', $pedido->id)->delete();
                $pagosEliminados += $pagos;
            }

            return [
                'success' => true,
                'tipo' => 'ELIMINACION_PAGOS',
                'cantidad' => $pagosEliminados,
                'message' => "Se eliminaron {$pagosEliminados} pagos"
            ];

        } catch (\Exception $e) {
            Log::error('Error eliminando pagos de garantía', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Error eliminando pagos: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Eliminar items de pedidos relacionados con la garantía
     */
    private function eliminarItemsPedidos($solicitudId)
    {
        try {
            // Buscar pedidos relacionados con la solicitud de garantía
            $pedidos = pedidos::where('id', $solicitudId)->get();
            
            $itemsEliminados = 0;
            foreach ($pedidos as $pedido) {
                $items = items_pedidos::where('id_pedido', $pedido->id)->delete();
                $itemsEliminados += $items;
            }

            return [
                'success' => true,
                'tipo' => 'ELIMINACION_ITEMS',
                'cantidad' => $itemsEliminados,
                'message' => "Se eliminaron {$itemsEliminados} items de pedidos"
            ];

        } catch (\Exception $e) {
            Log::error('Error eliminando items de pedidos', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Error eliminando items: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Eliminar pedidos relacionados con la garantía
     */
    private function eliminarPedidos($solicitudId)
    {
        try {
            $pedidosEliminados = pedidos::where('id', $solicitudId)->delete();

            return [
                'success' => true,
                'tipo' => 'ELIMINACION_PEDIDOS',
                'cantidad' => $pedidosEliminados,
                'message' => "Se eliminaron {$pedidosEliminados} pedidos"
            ];

        } catch (\Exception $e) {
            Log::error('Error eliminando pedidos', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Error eliminando pedidos: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Restaurar inventario afectado por la garantía
     */
    private function restaurarInventario($productos_con_datos,$solicitudId)
    {
        try {
            // Obtener los datos de la solicitud desde central para saber qué productos afectar

            $operaciones = [];

            foreach ($productos_con_datos as $producto) {
                $productoId = $producto['id_producto'] ?? 0;
                $cantidad = $producto['cantidad'] ?? 0;
                $tipo = $producto['tipo'] ?? '';
                $estado = $producto['estado'] ?? '';

                if ($tipo === 'entrada' && $estado === 'BUENO') {
                    // Revertir entrada: reducir cantidad del inventario
                    $inventario = inventario::find($productoId);
                    if ($inventario) {
                        $cantidadAnterior = $inventario->cantidad;
                        $inventario->cantidad = max(0, $inventario->cantidad - $cantidad);
                        $inventario->save();

                        // Registrar movimiento
                        movimientosInventariounitario::create([
                            'id_producto' => $productoId,
                            'cantidad' => -$cantidad,
                            'cantidadafter' => $inventario->cantidad,
                            'origen' => "Reversión de garantía - Eliminación de entrada: {$solicitudId}",
                            'id_usuario' => session("id_usuario") ?? 1,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);

                        $operaciones[] = [
                            'tipo' => 'REVERSION_ENTRADA',
                            'producto_id' => $productoId,
                            'cantidad' => $cantidad,
                            'cantidad_anterior' => $cantidadAnterior,
                            'cantidad_nueva' => $inventario->cantidad
                        ];
                    }
                } elseif ($tipo === 'salida') {
                    // Revertir salida: aumentar cantidad del inventario
                    $inventario = inventario::find($productoId);
                    if ($inventario) {
                        $cantidadAnterior = $inventario->cantidad;
                        $inventario->cantidad += $cantidad;
                        $inventario->save();

                        // Registrar movimiento
                        movimientosInventariounitario::create([
                            'id_producto' => $productoId,
                            'cantidad' => $cantidad,
                            'cantidadafter' => $inventario->cantidad,
                            'origen' => "Reversión de garantía - Restauración de salida: {$solicitudId}",
                            'id_usuario' => session("id_usuario") ?? 1,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);

                        $operaciones[] = [
                            'tipo' => 'REVERSION_SALIDA',
                            'producto_id' => $productoId,
                            'cantidad' => $cantidad,
                            'cantidad_anterior' => $cantidadAnterior,
                            'cantidad_nueva' => $inventario->cantidad
                        ];
                    }
                }
            }

            return [
                'success' => true,
                'tipo' => 'RESTAURACION_INVENTARIO',
                'operaciones' => $operaciones,
                'cantidad' => count($operaciones),
                'message' => "Se restauraron " . count($operaciones) . " productos en inventario"
            ];

        } catch (\Exception $e) {
            Log::error('Error restaurando inventario', [
                'solicitud_id' => $productos_con_datos,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Error restaurando inventario: ' . $e->getMessage()." ".$e->getLine()
            ];
        }
    }

    /**
     * Obtener el código de origen de la sucursal
     */
    private function getOrigen()
    {
        try {
            $sucursal = DB::table('sucursal')->first();
            return $sucursal->codigo ?? 'DEFAULT';
        } catch (\Exception $e) {
            Log::error('Error obteniendo código de sucursal', ['error' => $e->getMessage()]);
            return 'DEFAULT';
        }
    }

    /**
     * Obtener el ID de la sucursal
     */
    private function getSucursalId()
    {
        try {
            $sucursal = DB::table('sucursal')->first();
            return $sucursal->id ?? 1;
        } catch (\Exception $e) {
            Log::error('Error obteniendo ID de sucursal', ['error' => $e->getMessage()]);
            return 1;
        }
    }

    /**
     * Listar solicitudes de reverso aprobadas desde arabitocentral
     * Esta función se usa para mostrar las solicitudes aprobadas en la interfaz
     */
    public function listarSolicitudesReversoAprobadas(Request $request)
    {
        try {
            // Obtener el código de origen automáticamente desde sendCentral

            // Obtener solicitudes de reverso aprobadas desde arabitocentral
            $response = $this->sendCentral->getSolicitudesReversoAprobadas();
            
            if (!$response['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $response['message'] ?? 'Error al obtener solicitudes de reverso'
                ], 500);
            }

            $solicitudes = $response['data'];
            return ($response);

            // Filtrar solo las solicitudes que pertenecen a esta sucursal
            $solicitudesFiltradas = collect($solicitudes)->filter(function ($solicitud) use ($codigoOrigen) {
                // Verificar que la solicitud pertenece a esta sucursal
                return isset($solicitud['sucursal']) && 
                       $solicitud['sucursal']['codigo'] === $codigoOrigen;
            })->values();

            return response()->json([
                'success' => true,
                'solicitudes' => $solicitudesFiltradas,
                'total' => $solicitudesFiltradas->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error listando solicitudes de reverso aprobadas', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Ejecutar reverso completo de una solicitud aprobada
     * Esta función se llama desde la interfaz cuando se hace click en el botón de ejecutar
     */
    public function ejecutarReversoCompletoDesdeInterfaz(Request $request)
    {
        try {
            $request->validate([
                'solicitud_reverso_id' => 'required|integer'
            ]);

            $solicitudReversoId = $request->input('solicitud_reverso_id');
            
            // Obtener el código de origen automáticamente desde sendCentral
            $codigoOrigen = $this->sendCentral->getOrigen();

            // Obtener información de la solicitud de reverso desde arabitocentral
            $response = $this->sendCentral->getSolicitudesReversoAprobadas();
            
            if (!$response['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al obtener información de la solicitud de reverso'
                ], 500);
            }

            // Buscar la solicitud específica
            $solicitudReverso = collect($response['data'])->firstWhere('id', $solicitudReversoId);
            
            if (!$solicitudReverso) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró la solicitud de reverso especificada'
                ], 404);
            }

            // Verificar que esté aprobada
            if ($solicitudReverso['status'] !== 'Aprobada') {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden ejecutar solicitudes de reverso aprobadas'
                ], 400);
            }

            // Obtener el ID de la solicitud de garantía original
            $solicitudGarantiaId = $solicitudReverso['solicitud_garantia_id'];
            
            // Obtener información de la solicitud de garantía original
            $solicitudGarantia = $this->sendCentral->buscarSolicitudGarantiaCentral($solicitudGarantiaId);
            
            if (!$solicitudGarantia['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al obtener información de la solicitud de garantía original'
                ], 500);
            }

            $estatusOriginal = $solicitudGarantia['data']['estatus'];

            DB::beginTransaction();

            try {
                // Ejecutar el reverso localmente
                if($estatusOriginal=="FINALIZADA"){

                    $resultado = $this->ejecutarReversoCompleto($solicitudReverso);
                    if (!$resultado['success']) {
                        throw new \Exception($resultado['message']);
                    }
                }else{
                    $resultado = "No es FINALIZADA";
                }
                

                // Notificar a arabitocentral que el reverso se ejecutó (usa requestToCentral con API key para que central actualice estatus a Ejecutada)
                $notificacion = $this->sendCentral->notificarReversoEjecutadoCentral($solicitudReversoId, [
                    'codigo_origen' => $codigoOrigen,
                ]);

                if (!$notificacion['success']) {
                    Log::warning('No se pudo notificar a central sobre la ejecución del reverso', [
                        'solicitud_reverso_id' => $solicitudReversoId,
                        'error' => $notificacion['message']
                    ]);
                    DB::rollback();
                    return response()->json([
                        'success' => false,
                        'message' => 'Reverso aplicado en sucursal pero no se pudo actualizar central. La solicitud seguirá apareciendo como aprobada hasta que central se actualice. Error: ' . $notificacion['message']
                    ], 503);
                }

                DB::commit();

                Log::info('Reverso completo ejecutado exitosamente', [
                    'solicitud_reverso_id' => $solicitudReversoId,
                    'solicitud_garantia_id' => $solicitudGarantiaId,
                    'estatus_original' => $estatusOriginal,
                    'resultado' => $resultado
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Reverso ejecutado exitosamente',
                    'resultado' => $resultado
                ]);

            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Error ejecutando reverso completo', [
                'request' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error ejecutando reverso: ' . $e->getMessage()
            ], 500);
        }
    }

} 