<?php

namespace App\Http\Controllers;

set_time_limit(600000);
ini_set('memory_limit', '4095M');

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Http;
use Response;

use App\Models\inventario;
use App\Models\pedidos;
use App\Models\pago_pedidos;
use App\Models\items_pedidos;
use App\Models\cierres;
use App\Models\cierres_puntos;
use App\Models\pagos_referencias;
use App\Models\cajas;
use App\Models\movimientosinventariounitario;
use App\Models\sucursal;

/**
 * Controlador de Sincronización con Progreso en Tiempo Real
 * 
 * Proporciona sincronización por lotes con:
 * - Barra de progreso visual
 * - Tiempo estimado de finalización
 * - Estado en tiempo real de cada tabla
 * - Control granular de qué se sincroniza
 */
class SyncProgressController extends Controller
{
    const BATCH_SIZE = 500;
    
    private $centralUrl;
    private $codigoOrigen;
    
    public function __construct()
    {
        $this->centralUrl = $this->getCentralUrl();
    }
    
    private function getCentralUrl()
    {
        return "http://127.0.0.1:8001";
        // return "https://phplaravel-1009655-3565285.cloudwaysapps.com";
    }
    
    private function getCodigoOrigen()
    {
        if (!$this->codigoOrigen) {
            $this->codigoOrigen = sucursal::first()->codigo;
        }
        return $this->codigoOrigen;
    }
    
    /**
     * Vista principal de sincronización
     */
    public function index()
    {
        return view('sync.dashboard');
    }
    
    /**
     * Obtiene el estado actual de sincronización de todas las tablas
     */
    public function getStatus()
    {
        $tables = $this->getTablesConfig();
        $status = [];
        
        foreach ($tables as $key => $config) {
            $model = $config['model'];
            $campoSync = $config['campo_sync'] ?? 'sincronizado';
            
            $total = $model::count();
            $pendientes = $model::where($campoSync, 0)->count();
            $sincronizados = $total - $pendientes;
            
            $status[$key] = [
                'nombre' => $config['nombre'],
                'tabla_destino' => $config['tabla_destino'],
                'total' => $total,
                'sincronizados' => $sincronizados,
                'pendientes' => $pendientes,
                'porcentaje' => $total > 0 ? round(($sincronizados / $total) * 100, 1) : 100,
                'ultimo_sync' => $this->getUltimoSync($model, $campoSync),
                'orden' => $config['orden'],
                'grupo' => $config['grupo'],
            ];
        }
        
        // Ordenar por orden definido
        uasort($status, fn($a, $b) => $a['orden'] <=> $b['orden']);
        
        return Response::json([
            'estado' => true,
            'sucursal' => $this->getCodigoOrigen(),
            'tablas' => $status,
            'resumen' => $this->getResumenGeneral($status),
        ]);
    }
    
    /**
     * Obtiene el checkpoint de la última sincronización (para reanudar)
     */
    public function getCheckpoint()
    {
        $checkpoint = Cache::get('sync_checkpoint');
        $tables = $this->getTablesConfig();
        
        // Calcular pendientes totales
        $pendientesTotales = 0;
        $detalleTablas = [];
        
        foreach ($tables as $key => $config) {
            $model = $config['model'];
            $campoSync = $config['campo_sync'] ?? 'sincronizado';
            $pendientes = $model::where($campoSync, 0)->count();
            $pendientesTotales += $pendientes;
            
            if ($pendientes > 0) {
                $detalleTablas[$key] = [
                    'nombre' => $config['nombre'],
                    'pendientes' => $pendientes,
                ];
            }
        }
        
        return Response::json([
            'estado' => true,
            'puede_reanudar' => $pendientesTotales > 0,
            'pendientes_totales' => $pendientesTotales,
            'tablas_pendientes' => $detalleTablas,
            'ultimo_checkpoint' => $checkpoint,
            'mensaje' => $pendientesTotales > 0 
                ? "Hay {$pendientesTotales} registros pendientes. Puede continuar desde donde quedó."
                : "No hay registros pendientes. La sincronización está al día.",
        ]);
    }
    
    /**
     * Limpiar checkpoint (cuando se completa la sincronización)
     */
    public function clearCheckpoint()
    {
        Cache::forget('sync_checkpoint');
        return Response::json(['estado' => true, 'mensaje' => 'Checkpoint limpiado']);
    }
    
    /**
     * Verifica si es la primera sincronización (nunca se ha sincronizado)
     */
    public function esPrimeraSincronizacion()
    {
        $tables = $this->getTablesConfig();
        $totalSincronizados = 0;
        
        foreach ($tables as $key => $config) {
            $model = $config['model'];
            $campoSync = $config['campo_sync'] ?? 'sincronizado';
            $sincronizados = $model::where($campoSync, 1)->count();
            $totalSincronizados += $sincronizados;
        }
        
        // Es primera sincronización si no hay registros sincronizados
        $esPrimera = $totalSincronizados === 0;
        
        // Verificar si ya se ejecutó la primera sincronización automática
        $primeraEjecutada = Cache::get('primera_sync_ejecutada', false);
        
        return Response::json([
            'estado' => true,
            'es_primera' => $esPrimera && !$primeraEjecutada,
            'total_sincronizados' => $totalSincronizados,
            'primera_ejecutada' => $primeraEjecutada,
        ]);
    }
    
    /**
     * Marcar primera sincronización como ejecutada
     */
    public function marcarPrimeraEjecutada()
    {
        Cache::forever('primera_sync_ejecutada', true);
        return Response::json(['estado' => true, 'mensaje' => 'Primera sincronización marcada']);
    }
    
    /**
     * Fecha de corte para sincronización
     * Los registros anteriores a esta fecha NO se sincronizan
     */
    private function getFechaCorte()
    {
        return '2025-12-01';
    }
    
    /**
     * Marcar registros antiguos como sincronizados (para que no se envíen)
     * Esto debe ejecutarse UNA VEZ al iniciar el sistema de sincronización
     */
    public function marcarAntiguosComoSincronizados()
    {
        $fechaCorte = $this->getFechaCorte();
        $tables = $this->getTablesConfig();
        $resultados = [];
        
        Log::info("=== MARCANDO REGISTROS ANTIGUOS (antes de {$fechaCorte}) ===");
        
        foreach ($tables as $key => $config) {
            $model = $config['model'];
            $campoSync = $config['campo_sync'] ?? 'sincronizado';
            $campoFecha = $config['campo_fecha'] ?? 'created_at';
            
            try {
                // Contar registros antiguos no sincronizados
                $antiguosNoSync = $model::where($campoSync, 0)
                    ->where($campoFecha, '<', $fechaCorte)
                    ->count();
                
                if ($antiguosNoSync > 0) {
                    // Marcar como sincronizados
                    $actualizados = $model::where($campoSync, 0)
                        ->where($campoFecha, '<', $fechaCorte)
                        ->update([$campoSync => 1]);
                    
                    $resultados[$key] = [
                        'tabla' => $config['nombre'],
                        'marcados' => $actualizados,
                    ];
                    
                    Log::info("  [{$key}] Marcados {$actualizados} registros antiguos");
                } else {
                    $resultados[$key] = [
                        'tabla' => $config['nombre'],
                        'marcados' => 0,
                    ];
                }
            } catch (\Exception $e) {
                Log::error("  [{$key}] Error: " . $e->getMessage());
                $resultados[$key] = [
                    'tabla' => $config['nombre'],
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        // Marcar que ya se ejecutó
        Cache::forever('antiguos_marcados', true);
        
        $totalMarcados = collect($resultados)->sum('marcados');
        
        return Response::json([
            'estado' => true,
            'mensaje' => "Se marcaron {$totalMarcados} registros antiguos como sincronizados",
            'fecha_corte' => $fechaCorte,
            'detalle' => $resultados,
        ]);
    }
    
    /**
     * Verificar si ya se marcaron los registros antiguos
     */
    public function verificarAntiguosMarcados()
    {
        $marcados = Cache::get('antiguos_marcados', false);
        $fechaCorte = $this->getFechaCorte();
        
        // Contar cuántos registros antiguos sin sincronizar quedan
        $tables = $this->getTablesConfig();
        $pendientesAntiguos = 0;
        
        foreach ($tables as $key => $config) {
            $model = $config['model'];
            $campoSync = $config['campo_sync'] ?? 'sincronizado';
            $campoFecha = $config['campo_fecha'] ?? 'created_at';
            
            try {
                $pendientesAntiguos += $model::where($campoSync, 0)
                    ->where($campoFecha, '<', $fechaCorte)
                    ->count();
            } catch (\Exception $e) {
                // Ignorar errores
            }
        }
        
        return Response::json([
            'estado' => true,
            'antiguos_marcados' => $marcados,
            'fecha_corte' => $fechaCorte,
            'pendientes_antiguos' => $pendientesAntiguos,
            'requiere_marcar' => $pendientesAntiguos > 0,
        ]);
    }
    
    /**
     * Configuración de tablas a sincronizar
     * TODOS los campos se envían excepto inventarios (solo campos que cambian)
     */
    private function getTablesConfig()
    {
        return [
            'inventarios' => [
                'nombre' => 'Inventarios',
                'model' => inventario::class,
                'tabla_destino' => 'inventario_sucursals',
                'orden' => 1,
                'grupo' => 'primero',
                'campo_sync' => 'push', // Usa 'push' en lugar de 'sincronizado'
                'campo_fecha' => 'updated_at', // Para filtro de 3 días
                'limpiar_obsoletos' => true, // Eliminar productos que ya no existen
                // Solo campos que cambian frecuentemente
                'campos' => ['id', 'codigo_barras', 'codigo_proveedor', 'id_proveedor', 'id_categoria', 
                            'id_marca', 'unidad', 'descripcion', 'iva', 'porcentaje_ganancia',
                            'precio_base', 'precio', 'cantidad', 'stockmin', 'stockmax', 
                            'push', 'id_vinculacion', 'updated_at'],
            ],
            'pedidos' => [
                'nombre' => 'Pedidos',
                'model' => pedidos::class,
                'tabla_destino' => 'sucursal_pedidos',
                'orden' => 2,
                'grupo' => 'segundo',
                'campo_sync' => 'push',
                'campo_fecha' => 'created_at',
                // TODOS los campos del pedido
                'campos' => ['id', 'estado', 'export', 'fecha_inicio', 'fecha_vence', 'formato_pago',
                            'ticked', 'fiscal', 'retencion', 'isdevolucionOriginalid', 'fecha_factura',
                            'id_cliente', 'id_vendedor', 'created_at', 'updated_at'],
            ],
            'pago_pedidos' => [
                'nombre' => 'Pagos de Pedidos',
                'model' => pago_pedidos::class,
                'tabla_destino' => 'sucursal_pedido_pagos',
                'orden' => 3,
                'grupo' => 'segundo',
                'campo_sync' => 'push',
                'campo_fecha' => 'created_at',
                // TODOS los campos del pago
                'campos' => ['id', 'tipo', 'monto', 'monto_original', 'moneda', 'referencia',
                            'cuenta', 'id_pedido', 'created_at', 'updated_at'],
            ],
            'items_pedidos' => [
                'nombre' => 'Items de Pedidos (Estadísticas)',
                'model' => items_pedidos::class,
                'tabla_destino' => 'inventario_sucursal_estadisticas',
                'orden' => 4,
                'grupo' => 'segundo',
                'campo_sync' => 'push',
                'campo_fecha' => 'created_at',
                // TODOS los campos del item
                'campos' => ['id', 'lote', 'id_producto', 'id_pedido', 'abono', 'cantidad', 
                            'descuento', 'monto', 'tasa', 'tasa_cop', 'precio_unitario',
                            'entregado', 'condicion', 'created_at', 'updated_at'],
            ],
            'cierres' => [
                'nombre' => 'Cierres',
                'model' => cierres::class,
                'tabla_destino' => 'cierres',
                'orden' => 5,
                'grupo' => 'tercero',
                'campo_sync' => 'push',
                'campo_fecha' => 'fecha',
                // TODOS los campos del cierre - COMPLETO
                'campos' => [
                    'id', 'fecha', 'tasa', 'tasacop', 'nota', 'id_usuario',
                    // Métodos de pago
                    'debito', 'efectivo', 'transferencia', 'caja_biopago',
                    // Dejar en caja
                    'dejar_dolar', 'dejar_peso', 'dejar_bss',
                    // Efectivo guardado
                    'efectivo_guardado', 'efectivo_guardado_cop', 'efectivo_guardado_bs',
                    // Efectivo actual
                    'efectivo_actual', 'efectivo_actual_cop', 'efectivo_actual_bs',
                    'puntodeventa_actual_bs',
                    // Ventas
                    'numventas', 'precio', 'precio_base', 'ganancia', 'porcentaje', 'desc_total',
                    // Inventario
                    'inventariobase', 'inventarioventa',
                    // Fiscales
                    'numreportez', 'ventaexcento', 'ventagravadas', 'ivaventa', 
                    'totalventa', 'ultimafactura',
                    // Créditos
                    'credito', 'creditoporcobrartotal', 'vueltostotales', 'abonosdeldia',
                    // Efectivo adicional caja fuerte
                    'efecadiccajafbs', 'efecadiccajafcop', 'efecadiccajafdolar', 'efecadiccajafeuro',
                    // Puntos de venta
                    'puntolote1', 'puntolote2', 'puntolote1montobs', 'puntolote2montobs',
                    'puntolote1banco', 'puntolote2banco',
                    'biopagoserial', 'biopagoserialmontobs',
                    // Descuadre
                    'descuadre',
                    // Control
                    'push', 'created_at', 'updated_at'
                ],
            ],
            'cierres_puntos' => [
                'nombre' => 'Cierres Puntos',
                'model' => cierres_puntos::class,
                'tabla_destino' => 'puntosybiopagos',
                'orden' => 6,
                'grupo' => 'tercero',
                'campo_sync' => 'push',
                'campo_fecha' => 'fecha',
                'tipo_origen' => 'PUNTO', // Prefijo para idinsucursal
                // Campos que se leen del modelo
                'campos' => ['id', 'fecha', 'categoria', 'descripcion', 'banco', 'monto', 
                            'id_usuario', 'created_at', 'updated_at'],
                // Transformación para formato puntosybiopagos
                'transformar' => function($registro, $index) {
                    return [
                        'idinsucursal' => 'PUNTO-' . $registro['id'],
                        'monto' => $registro['monto'],
                        'banco' => $registro['banco'],
                        'loteserial' => $registro['descripcion'], // descripcion = número de lote
                        'fecha' => $registro['fecha'],
                        'id_usuario' => $registro['id_usuario'],
                        'debito_credito' => $registro['categoria'], // DEBITO o CREDITO
                        'tipo' => 'PUNTO ' . $index,
                        'origen' => 1, // 1 = viene de sucursal
                        'created_at' => $registro['created_at'],
                    ];
                },
            ],
            'pagos_referencias' => [
                'nombre' => 'Pagos Referencias',
                'model' => pagos_referencias::class,
                'tabla_destino' => 'puntosybiopagos',
                'orden' => 7,
                'grupo' => 'tercero',
                'campo_sync' => 'push',
                'campo_fecha' => 'created_at',
                'tipo_origen' => 'TRANS', // Prefijo para idinsucursal
                // Campos que se leen del modelo
                'campos' => ['id', 'categoria', 'tipo', 'descripcion', 'banco', 'monto', 
                            'fecha_pago', 'banco_origen', 'monto_real', 'cuenta_origen',
                            'response', 'estatus', 'cedula', 'telefono',
                            'id_pedido', 'created_at', 'updated_at'],
                // Transformación para formato puntosybiopagos
                'transformar' => function($registro, $index) {
                    // Convertir tipo numérico a texto (igual que retpago())
                    $tipoTexto = match($registro['tipo']) {
                        '1', 1 => 'Transferencia',
                        '2', 2 => 'Debito',
                        '3', 3 => 'Efectivo',
                        '4', 4 => 'Credito',
                        '5', 5 => 'BioPago',
                        '6', 6 => 'vuelto',
                        default => $registro['tipo'],
                    };
                    
                    return [
                        'idinsucursal' => 'TRANS-' . $registro['id'],
                        'monto' => $registro['monto'],
                        'banco' => $registro['banco'],
                        'loteserial' => $registro['descripcion'], // descripcion = referencia
                        'fecha' => substr($registro['created_at'], 0, 10), // Solo fecha YYYY-MM-DD
                        'id_usuario' => $registro['id'], // En el original usa id del registro
                        'tipo' => $tipoTexto,
                        'origen' => 1,
                        'id_pedido' => $registro['id_pedido'],
                        // Campos adicionales del pago
                        'fecha_pago' => $registro['fecha_pago'],
                        'banco_origen' => $registro['banco_origen'],
                        'monto_real' => $registro['monto_real'],
                        'cuenta_origen' => $registro['cuenta_origen'],
                        'response' => $registro['response'],
                        'estatus' => $registro['estatus'],
                        'cedula' => $registro['cedula'] ?? null,
                        'telefono' => $registro['telefono'] ?? null,
                        'created_at' => $registro['created_at'],
                    ];
                },
            ],
            'cajas' => [
                'nombre' => 'Cajas',
                'model' => cajas::class,
                'tabla_destino' => 'cajas',
                'orden' => 8,
                'grupo' => 'tercero',
                'campo_sync' => 'push',
                'campo_fecha' => 'fecha',
                // TODOS los campos
                'campos' => ['id', 'concepto', 'categoria', 'montodolar', 'dolarbalance', 
                            'montobs', 'bsbalance', 'montopeso', 'pesobalance', 
                            'montoeuro', 'eurobalance', 'estatus', 'id_sucursal_destino',
                            'id_sucursal_emisora', 'idincentralrecepcion', 'sucursal_destino_aprobacion',
                            'id_beneficiario', 'id_departamento', 'fecha', 'tipo', 
                            'created_at', 'updated_at'],
            ],
            'movimientos_inventariounitarios' => [
                'nombre' => 'Movimientos Inventario',
                'model' => movimientosinventariounitario::class,
                'tabla_destino' => 'movsinventarios',
                'orden' => 9,
                'grupo' => 'ultimo',
                'campo_sync' => 'push',
                'campo_fecha' => 'created_at',
                // TODOS los campos
                'campos' => ['id', 'id_producto', 'id_pedido', 'id_usuario', 'cantidad', 
                            'cantidadafter', 'origen', 'created_at', 'updated_at'],
            ],
        ];
    }
    
    /**
     * Obtiene la fecha de inicio para sincronización
     * Se usa la fecha de corte fija (2025-12-01) para nunca enviar data antigua
     */
    private function getFechaInicioSync()
    {
        return $this->getFechaCorte() . ' 00:00:00';
    }
    
    private function getUltimoSync($model, $campoSync = 'sincronizado')
    {
        $campoAt = $campoSync === 'push' ? 'updated_at' : 'sincronizado_at';
        
        $ultimo = $model::where($campoSync, 1)
            ->orderBy($campoAt, 'desc')
            ->first();
        return $ultimo ? $ultimo->$campoAt : null;
    }
    
    private function getResumenGeneral($status)
    {
        $totalRegistros = array_sum(array_column($status, 'total'));
        $totalPendientes = array_sum(array_column($status, 'pendientes'));
        $totalSincronizados = $totalRegistros - $totalPendientes;
        
        return [
            'total_registros' => $totalRegistros,
            'total_sincronizados' => $totalSincronizados,
            'total_pendientes' => $totalPendientes,
            'porcentaje_global' => $totalRegistros > 0 
                ? round(($totalSincronizados / $totalRegistros) * 100, 1) 
                : 100,
        ];
    }
    
    /**
     * Endpoint SSE para progreso en tiempo real
     */
    public function streamProgress(Request $request)
    {
        return response()->stream(function () use ($request) {
            $tablas = $request->input('tablas', ['all']);
            $soloNuevos = $request->input('solo_nuevos', true);
            
            $this->sendSSE('inicio', [
                'mensaje' => 'Iniciando sincronización...',
                'timestamp' => now()->toDateTimeString(),
            ]);
            
            try {
                $resultado = $this->ejecutarSincronizacion($tablas, $soloNuevos);
                
                $this->sendSSE('completado', [
                    'mensaje' => 'Sincronización completada',
                    'resultado' => $resultado,
                    'timestamp' => now()->toDateTimeString(),
                ]);
            } catch (\Exception $e) {
                $this->sendSSE('error', [
                    'mensaje' => $e->getMessage(),
                    'timestamp' => now()->toDateTimeString(),
                ]);
            }
            
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
    
    private function sendSSE($event, $data)
    {
        echo "event: {$event}\n";
        echo "data: " . json_encode($data) . "\n\n";
        ob_flush();
        flush();
    }
    
    /**
     * Sincronización con progreso
     * SIEMPRE aplica filtro de fecha de corte (2025-12-01)
     * Nunca se sincronizan registros anteriores a esa fecha
     */
    public function sincronizarTodo(Request $request)
    {
        $tablas = $request->input('tablas', ['all']);
        $soloNuevos = $request->input('solo_nuevos', true);
        // IGNORAR el parámetro primera_sync - SIEMPRE aplicamos filtro de fecha
        
        $fechaCorte = $this->getFechaCorte();
        
        Log::info('=== INICIO SINCRONIZACIÓN CON PROGRESO ===');
        Log::info('Tablas seleccionadas: ' . json_encode($tablas));
        Log::info("Fecha de corte: {$fechaCorte} (registros anteriores NO se sincronizan)");
        
        try {
            $resultado = $this->ejecutarSincronizacion($tablas, $soloNuevos);
            
            return Response::json([
                'estado' => true,
                'mensaje' => 'Sincronización completada',
                'resultado' => $resultado,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en sincronización: ' . $e->getMessage());
            return Response::json([
                'estado' => false,
                'mensaje' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Ejecuta la sincronización de las tablas seleccionadas
     * SIEMPRE aplica filtro de fecha de corte (2025-12-01)
     * 
     * @param array $tablasSeleccionadas Tablas a sincronizar
     * @param bool $soloNuevos Solo registros no sincronizados
     */
    private function ejecutarSincronizacion($tablasSeleccionadas, $soloNuevos = true)
    {
        $config = $this->getTablesConfig();
        $resultados = [];
        $tiempoInicio = microtime(true);
        
        // Determinar qué tablas sincronizar
        $tablasASincronizar = $tablasSeleccionadas[0] === 'all' 
            ? array_keys($config) 
            : $tablasSeleccionadas;
        
        // SIEMPRE aplicar filtro de fecha de corte
        $fechaInicio = $this->getFechaInicioSync();
        
        // Calcular totales para tiempo estimado
        $totalRegistros = 0;
        
        foreach ($tablasASincronizar as $tabla) {
            if (isset($config[$tabla])) {
                $tablaConfig = $config[$tabla];
                $model = $tablaConfig['model'];
                $campoSync = $tablaConfig['campo_sync'] ?? 'sincronizado';
                $campoFecha = $tablaConfig['campo_fecha'] ?? 'created_at';
                
                $query = $model::query();
                
                if ($soloNuevos) {
                    $query->where($campoSync, 0);
                }
                
                // SIEMPRE aplicar filtro de fecha de corte
                $query->where($campoFecha, '>=', $fechaInicio);
                
                $totalRegistros += $query->count();
            }
        }
        
        $registrosProcesados = 0;
        
        Log::info("Total registros a procesar: {$totalRegistros}");
        Log::info("Filtro de fecha aplicado desde: {$fechaInicio}");
        
        foreach ($tablasASincronizar as $tabla) {
            if (!isset($config[$tabla])) continue;
            
            $tablaConfig = $config[$tabla];
            $tiempoTabla = microtime(true);
            
            Log::info(">>> Procesando tabla: {$tabla}");
            
            try {
                $resultado = $this->sincronizarTabla(
                    $tabla, 
                    $tablaConfig, 
                    $soloNuevos, 
                    function($procesados, $totalTabla) use (&$registrosProcesados, $totalRegistros, $tiempoInicio) {
                        $registrosProcesados += $procesados;
                        
                        // Calcular tiempo estimado
                        $tiempoTranscurrido = microtime(true) - $tiempoInicio;
                        $velocidad = $registrosProcesados > 0 ? $registrosProcesados / $tiempoTranscurrido : 1;
                        $pendientes = $totalRegistros - $registrosProcesados;
                        $tiempoEstimado = $velocidad > 0 ? $pendientes / $velocidad : 0;
                        
                        return [
                            'procesados_global' => $registrosProcesados,
                            'total_global' => $totalRegistros,
                            'porcentaje_global' => $totalRegistros > 0 ? round(($registrosProcesados / $totalRegistros) * 100, 1) : 100,
                            'tiempo_transcurrido' => round($tiempoTranscurrido, 1),
                            'tiempo_estimado_restante' => round($tiempoEstimado, 1),
                        ];
                    }
                );
                
                $resultados[$tabla] = [
                    'estado' => 'completado',
                    'registros' => $resultado['procesados'],
                    'tiempo' => round(microtime(true) - $tiempoTabla, 2) . 's',
                ];
                
                Log::info("<<< {$tabla} completado: {$resultado['procesados']} registros");
                
            } catch (\Exception $e) {
                $resultados[$tabla] = [
                    'estado' => 'error',
                    'mensaje' => $e->getMessage(),
                    'tiempo' => round(microtime(true) - $tiempoTabla, 2) . 's',
                ];
                Log::error("<<< {$tabla} error: " . $e->getMessage());
            }
        }
        
        return [
            'tablas' => $resultados,
            'tiempo_total' => round(microtime(true) - $tiempoInicio, 2) . 's',
            'registros_totales' => $registrosProcesados,
        ];
    }
    
    /**
     * Sincroniza una tabla específica
     * 
     * @param string $nombreTabla Nombre de la tabla
     * @param array $config Configuración de la tabla
     * @param bool $soloNuevos Solo sincronizar registros no sincronizados
     * @param callable|null $progressCallback Callback de progreso
     */
    private function sincronizarTabla($nombreTabla, $config, $soloNuevos, $progressCallback = null)
    {
        $model = $config['model'];
        $campoSync = $config['campo_sync'] ?? 'sincronizado';
        $campoFecha = $config['campo_fecha'] ?? 'created_at';
        $procesados = 0;
        $errores = [];
        
        // Construir query base
        $query = $model::query();
        
        // Filtrar por campo de sincronización si solo queremos nuevos
        if ($soloNuevos) {
            $query->where($campoSync, 0);
        }
        
        // SIEMPRE aplicar filtro de fecha de corte (2025-12-01)
        // Nunca se sincronizan registros anteriores a esta fecha
        $fechaInicio = $this->getFechaInicioSync();
        $query->where($campoFecha, '>=', $fechaInicio);
        Log::info("    [{$nombreTabla}] Filtrando desde: {$fechaInicio}");
        
        $total = $query->count();
        
        if ($total === 0) {
            return ['procesados' => 0, 'errores' => []];
        }
        
        Log::info("    [{$nombreTabla}] Total a procesar: {$total}");
        
        // Procesar en lotes
        $query->orderBy('id')->chunk(self::BATCH_SIZE, function($registros) use ($nombreTabla, $config, $campoSync, &$procesados, &$errores, $total, $progressCallback) {
            // Si hay función de transformación, aplicarla
            $transformar = $config['transformar'] ?? null;
            
            $data = $registros->map(function($r, $index) use ($config, $transformar) {
                $registro = $r->only($config['campos']);
                
                // Aplicar transformación si existe (para puntosybiopagos)
                if ($transformar && is_callable($transformar)) {
                    return $transformar($registro, $index);
                }
                
                return $registro;
            })->toArray();
            
            // Enviar lote a Central con reintentos
            $maxReintentos = 3;
            $exito = false;
            $ultimoError = null;
            
            for ($intento = 1; $intento <= $maxReintentos && !$exito; $intento++) {
                try {
                    // Guardar checkpoint antes de enviar
                    Cache::put('sync_checkpoint', [
                        'tabla' => $nombreTabla,
                        'lote_actual' => $procesados,
                        'total' => $total,
                        'timestamp' => now()->toISOString(),
                    ], 3600); // 1 hora
                    
                    $response = Http::timeout(120)
                        ->retry(2, 1000) // 2 reintentos internos con 1 segundo entre cada uno
                        ->post($this->getCentralUrl() . "/api/sync/batch", [
                            'codigo_origen' => $this->getCodigoOrigen(),
                            'tabla' => $nombreTabla,
                            'tabla_destino' => $config['tabla_destino'],
                            'data' => base64_encode(gzcompress(json_encode($data))),
                            'batch_size' => count($data),
                        ]);
                    
                    if ($response->ok()) {
                        $resultado = $response->json();
                        
                        if ($resultado['estado'] ?? false) {
                            // Marcar como sincronizados usando el campo correcto
                            $ids = $registros->pluck('id')->toArray();
                            $updateData = [$campoSync => 1];
                            
                            // Si no es 'push', también actualizar sincronizado_at
                            if ($campoSync !== 'push') {
                                $updateData['sincronizado_at'] = now();
                            }
                            
                            $config['model']::whereIn('id', $ids)->update($updateData);
                            
                            $procesados += count($data);
                            $exito = true;
                        } else {
                            $ultimoError = $resultado['mensaje'] ?? 'Error desconocido';
                        }
                    } else {
                        $ultimoError = 'HTTP ' . $response->status();
                    }
                } catch (\Exception $e) {
                    $ultimoError = $e->getMessage();
                    
                    // Si no es el último intento, esperar con backoff exponencial
                    if ($intento < $maxReintentos) {
                        $waitSeconds = pow(2, $intento); // 2, 4 segundos
                        Log::warning("    [{$nombreTabla}] Reintento {$intento}/{$maxReintentos} en {$waitSeconds}s: {$ultimoError}");
                        sleep($waitSeconds);
                    }
                }
            }
            
            if (!$exito && $ultimoError) {
                $errores[] = "Falló después de {$maxReintentos} intentos: {$ultimoError}";
                Log::error("    [{$nombreTabla}] Error persistente: {$ultimoError}");
                // NO lanzar excepción, continuar con el siguiente lote
                // Los registros de este lote quedarán sin marcar y se reintentarán en la próxima ejecución
            }
            
            // Callback de progreso
            if ($progressCallback) {
                $progressCallback(count($data), $total);
            }
            
            // Pausa para no saturar
            usleep(50000); // 50ms
        });
        
        // Limpiar productos obsoletos en Central (solo para inventarios)
        $eliminados = 0;
        if (($config['limpiar_obsoletos'] ?? false) && $procesados > 0) {
            $eliminados = $this->limpiarObsoletos($nombreTabla, $config);
        }
        
        return [
            'procesados' => $procesados,
            'eliminados' => $eliminados,
            'errores' => $errores,
        ];
    }
    
    /**
     * Envía la lista de IDs existentes a Central para eliminar los obsoletos
     */
    private function limpiarObsoletos($nombreTabla, $config)
    {
        try {
            $model = $config['model'];
            
            // Obtener TODOS los IDs que existen actualmente en Facturación
            $idsExistentes = $model::pluck('id')->toArray();
            
            if (empty($idsExistentes)) {
                Log::info("    [{$nombreTabla}] No hay productos para verificar obsoletos");
                return 0;
            }
            
            Log::info("    [{$nombreTabla}] Enviando lista de " . count($idsExistentes) . " IDs para limpiar obsoletos");
            
            // Enviar a Central para que elimine los que no estén en esta lista
            $response = Http::timeout(120)->post($this->getCentralUrl() . "/api/sync/limpiar-obsoletos", [
                'codigo_origen' => $this->getCodigoOrigen(),
                'tabla_destino' => $config['tabla_destino'],
                'ids_existentes' => base64_encode(gzcompress(json_encode($idsExistentes))),
            ]);
            
            if ($response->ok()) {
                $resultado = $response->json();
                $eliminados = $resultado['eliminados'] ?? 0;
                
                if ($eliminados > 0) {
                    Log::info("    [{$nombreTabla}] Eliminados {$eliminados} productos obsoletos en Central");
                }
                
                return $eliminados;
            }
            
            return 0;
        } catch (\Exception $e) {
            Log::error("    [{$nombreTabla}] Error limpiando obsoletos: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Sincronizar una tabla específica (endpoint individual)
     */
    public function sincronizarTablaIndividual(Request $request)
    {
        $tabla = $request->input('tabla');
        $soloNuevos = $request->input('solo_nuevos', true);
        
        $config = $this->getTablesConfig();
        
        if (!isset($config[$tabla])) {
            return Response::json([
                'estado' => false,
                'mensaje' => 'Tabla no válida',
            ], 400);
        }
        
        try {
            $tiempoInicio = microtime(true);
            $resultado = $this->sincronizarTabla($tabla, $config[$tabla], $soloNuevos);
            
            return Response::json([
                'estado' => true,
                'tabla' => $tabla,
                'registros_procesados' => $resultado['procesados'],
                'errores' => $resultado['errores'],
                'tiempo' => round(microtime(true) - $tiempoInicio, 2) . 's',
            ]);
        } catch (\Exception $e) {
            return Response::json([
                'estado' => false,
                'mensaje' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Resetear estado de sincronización (para re-sincronizar todo)
     */
    public function resetearSincronizacion(Request $request)
    {
        $tabla = $request->input('tabla');
        $config = $this->getTablesConfig();
        
        if ($tabla && isset($config[$tabla])) {
            $tablaConfig = $config[$tabla];
            $campoSync = $tablaConfig['campo_sync'] ?? 'sincronizado';
            
            $updateData = [$campoSync => 0];
            if ($campoSync !== 'push') {
                $updateData['sincronizado_at'] = null;
            }
            
            $tablaConfig['model']::query()->update($updateData);
            
            return Response::json([
                'estado' => true,
                'mensaje' => "Sincronización reseteada para {$tabla}",
            ]);
        }
        
        // Resetear todas
        foreach ($config as $key => $tablaConfig) {
            $campoSync = $tablaConfig['campo_sync'] ?? 'sincronizado';
            
            $updateData = [$campoSync => 0];
            if ($campoSync !== 'push') {
                $updateData['sincronizado_at'] = null;
            }
            
            $tablaConfig['model']::query()->update($updateData);
        }
        
        return Response::json([
            'estado' => true,
            'mensaje' => 'Sincronización reseteada para todas las tablas',
        ]);
    }
    
    /**
     * Marcar registros como sincronizados manualmente
     */
    public function marcarSincronizados(Request $request)
    {
        $tabla = $request->input('tabla');
        $desde = $request->input('desde'); // fecha o ID
        $hasta = $request->input('hasta');
        
        $config = $this->getTablesConfig();
        
        if (!isset($config[$tabla])) {
            return Response::json(['estado' => false, 'mensaje' => 'Tabla no válida'], 400);
        }
        
        $tablaConfig = $config[$tabla];
        $campoSync = $tablaConfig['campo_sync'] ?? 'sincronizado';
        $model = $tablaConfig['model'];
        $query = $model::query();
        
        if ($desde && $hasta) {
            // Si son fechas
            if (strtotime($desde)) {
                $query->whereBetween('created_at', [$desde, $hasta]);
            } else {
                // Si son IDs
                $query->whereBetween('id', [$desde, $hasta]);
            }
        }
        
        $updateData = [$campoSync => 1];
        if ($campoSync !== 'push') {
            $updateData['sincronizado_at'] = now();
        }
        
        $actualizados = $query->update($updateData);
        
        return Response::json([
            'estado' => true,
            'registros_actualizados' => $actualizados,
        ]);
    }
}
