<?php

namespace App\Http\Controllers;

set_time_limit(600000);
ini_set('memory_limit', '4095M');

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Http;
use Response;

use App\Models\inventario;
use App\Models\pedidos;
use App\Models\pago_pedidos;
use App\Models\items_pedidos;
use App\Models\cierres;
use App\Models\pagos_referencias;
use App\Models\cajas;
use App\Models\movimientosinventariounitario;
use App\Models\sucursal;
use App\Http\Controllers\PagoPedidosController;
use App\Http\Controllers\PedidosController;

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
        return (new sendCentral())->path();
    }
    
    private function getCodigoOrigen()
    {
        if (!$this->codigoOrigen) {
            $this->codigoOrigen = sucursal::first()->codigo;
        }
        return $this->codigoOrigen;
    }

    /** Cabeceras para peticiones a central (API key de sucursal). */
    private function centralRequestHeaders(): array
    {
        $headers = [];
        $apiKey = (new sendCentral())->getCentralApiKey();
        if ($apiKey !== null && $apiKey !== '') {
            $headers['X-Sucursal-Api-Key'] = $apiKey;
        }
        return $headers;
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
            // Caso especial: deudores (cálculo, no tabla)
            if (isset($config['es_calculo']) && $config['es_calculo']) {
                try {
                    $pagoPedidosController = new PagoPedidosController();
                    $pedidosController = new PedidosController();
                    $today = $pedidosController->today();
                    $deudores = $pagoPedidosController->getDeudoresFun('', 'saldo', 'ASC', $today, 100000);
                    $total = $deudores->filter(function($cliente) {
                        return isset($cliente->saldo) && floatval($cliente->saldo) < 0;
                    })->count();
                    
                    $status[$key] = [
                        'nombre' => $config['nombre'],
                        'tabla_destino' => $config['tabla_destino'],
                        'total' => $total,
                        'sincronizados' => $total, // Siempre sincronizado (se recalcula completo)
                        'pendientes' => 0,
                        'porcentaje' => 100,
                        'ultimo_sync' => null,
                        'orden' => $config['orden'],
                        'grupo' => $config['grupo'],
                    ];
                } catch (\Exception $e) {
                    $status[$key] = [
                        'nombre' => $config['nombre'],
                        'tabla_destino' => $config['tabla_destino'],
                        'total' => 0,
                        'sincronizados' => 0,
                        'pendientes' => 0,
                        'porcentaje' => 0,
                        'ultimo_sync' => null,
                        'orden' => $config['orden'],
                        'grupo' => $config['grupo'],
                    ];
                }
            } else {
                $model = $config['model'];
                $campoSync = $config['campo_sync'] ?? 'sincronizado';
                
                // Validar que el modelo existe y es válido
                if (is_null($model) || (!is_string($model) && !is_object($model)) || (is_string($model) && !class_exists($model))) {
                    continue;
                }
                
                // Validar que la tabla existe en la base de datos
                try {
                    $tableName = (new $model)->getTable();
                    if (!Schema::hasTable($tableName)) {
                        continue;
                    }
                } catch (\Exception $e) {
                    continue;
                }
                
                // Para CIERRES: solo contar cierres de tipo administrador (tipo_cierre = 1)
                if ($key === 'cierres') {
                    $total = $model::where('tipo_cierre', 1)->count();
                    $pendientes = $model::where('tipo_cierre', 1)->where($campoSync, 0)->count();
                } else {
                    $total = $model::count();
                    $pendientes = $model::where($campoSync, 0)->count();
                }
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
            
            // Validar que el modelo existe y es válido
            if (is_null($model) || (!is_string($model) && !is_object($model)) || (is_string($model) && !class_exists($model))) {
                continue;
            }
            
            // Validar que la tabla existe en la base de datos
            try {
                $tableName = (new $model)->getTable();
                if (!Schema::hasTable($tableName)) {
                    continue;
                }
            } catch (\Exception $e) {
                continue;
            }
            
            // Para CIERRES: solo contar cierres de tipo administrador (tipo_cierre = 1)
            if ($key === 'cierres') {
                $pendientes = $model::where('tipo_cierre', 1)->where($campoSync, 0)->count();
            } else {
                $pendientes = $model::where($campoSync, 0)->count();
            }
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
            
            // Validar que el modelo existe y es válido
            if (is_null($model) || (!is_string($model) && !is_object($model)) || (is_string($model) && !class_exists($model))) {
                continue;
            }
            
            // Validar que la tabla existe en la base de datos
            try {
                $tableName = (new $model)->getTable();
                if (!Schema::hasTable($tableName)) {
                    continue;
                }
            } catch (\Exception $e) {
                continue;
            }
            
            // Para CIERRES: solo contar cierres de tipo administrador (tipo_cierre = 1)
            if ($key === 'cierres') {
                $sincronizados = $model::where('tipo_cierre', 1)->where($campoSync, 1)->count();
            } else {
                $sincronizados = $model::where($campoSync, 1)->count();
            }
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
            
            // Validar que el modelo existe y es válido
            if (is_null($model)) {
                continue;
            }
            
            if (!is_string($model) && !is_object($model)) {
                continue;
            }
            
            if (is_string($model) && !class_exists($model)) {
                continue;
            }
            
            // Validar que la tabla existe en la base de datos
            try {
                $tableName = (new $model)->getTable();
                if (!Schema::hasTable($tableName)) {
                    continue;
                }
            } catch (\Exception $e) {
                continue;
            }
            
            try {
                // Para CIERRES: solo procesar cierres de tipo administrador (tipo_cierre = 1)
                $queryAntiguos = $model::where($campoSync, 0)->where($campoFecha, '<', $fechaCorte);
                if ($key === 'cierres') {
                    $queryAntiguos->where('tipo_cierre', 1);
                }
                
                // Contar registros antiguos no sincronizados
                $antiguosNoSync = $queryAntiguos->count();
                
                if ($antiguosNoSync > 0) {
                    // Marcar como sincronizados
                    $actualizados = $queryAntiguos->update([$campoSync => 1]);
                    
                    $resultados[$key] = [
                        'tabla' => $config['nombre'],
                        'marcados' => $actualizados,
                    ];
                } else {
                    $resultados[$key] = [
                        'tabla' => $config['nombre'],
                        'marcados' => 0,
                    ];
                }
            } catch (\Exception $e) {
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
            
            // Validar que el modelo existe y no es null
            if (is_null($model)) {
                continue;
            }
            
            // Validar que el modelo es una clase válida
            if (!is_string($model) && !is_object($model)) {
                continue;
            }
            
            // Validar que la clase existe
            if (is_string($model) && !class_exists($model)) {
                continue;
            }
            
            try {
                // Para CIERRES: solo contar cierres de tipo administrador (tipo_cierre = 1)
                $queryPendientes = $model::where($campoSync, 0)->where($campoFecha, '<', $fechaCorte);
                if ($key === 'cierres') {
                    $queryPendientes->where('tipo_cierre', 1);
                }
                $pendientesAntiguos += $queryPendientes->count();
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
                            'push', 'id_vinculacion', 'created_at','updated_at'],
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
                            'cuenta', 'id_pedido', 'pos_message', 'pos_lote', 'pos_responsecode',
                            'pos_amount', 'pos_terminal', 'pos_json_response', 'created_at', 'updated_at'],
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
                    'credito', 'creditoporcobrartotal', 'abonosdeldia',
                    // Efectivo adicional caja fuerte
                    'efecadiccajafbs', 'efecadiccajafcop', 'efecadiccajafdolar', 'efecadiccajafeuro',
                    // Biopago
                    'biopagoserial', 'biopagoserialmontobs',
                    // Puntos
                    'puntolote1', 'puntolote1montobs', 'puntolote2', 'puntolote2montobs',
                    // Vueltos
                    'vueltostotales',
                    // Descuadre
                    'descuadre',
                    // Campos digitales
                    'debito_digital', 'efectivo_digital', 'transferencia_digital', 'biopago_digital',
                    // Control
                    'push', 'created_at', 'updated_at'
                ],
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
            // 'cajas' => [
            //     'nombre' => 'Cajas',
            //     'model' => cajas::class,
            //     'tabla_destino' => 'cajas',
            //     'orden' => 8,
            //     'grupo' => 'tercero',
            //     'campo_sync' => 'push',
            //     'campo_fecha' => 'fecha',
            //     // TODOS los campos
            //     'campos' => ['id', 'concepto', 'categoria', 'montodolar', 'dolarbalance', 
            //                 'montobs', 'bsbalance', 'montopeso', 'pesobalance', 
            //                 'montoeuro', 'eurobalance', 'estatus', 'id_sucursal_destino',
            //                 'id_sucursal_emisora', 'idincentralrecepcion', 'sucursal_destino_aprobacion',
            //                 'id_beneficiario', 'id_departamento', 'fecha', 'tipo', 
            //                 'created_at', 'updated_at'],
            // ],
            // NOTA: La tabla 'cajas' fue eliminada en arabitofacturacion.
            // Los movimientos de caja ahora se crean en arabitocentral desde el efectivo_guardado del cierre.
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
            'deudores' => [
                'nombre' => 'Deudores (Créditos)',
                'model' => null, // No es un modelo, es un cálculo
                'tabla_destino' => 'creditos',
                'orden' => 10,
                'grupo' => 'ultimo',
                'campo_sync' => null, // No aplica
                'campo_fecha' => null, // No aplica
                'es_calculo' => true, // Marca especial para indicar que es un cálculo
                'metodo_obtener' => 'getDeudoresFun', // Método a llamar
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
                
                // Para deudores (cálculo), usar método especial
                if (isset($tablaConfig['es_calculo']) && $tablaConfig['es_calculo']) {
                    // Obtener deudores usando getDeudoresFun
                    $pagoPedidosController = new PagoPedidosController();
                    $today = (new PedidosController)->today();
                    $deudores = $pagoPedidosController->getDeudoresFun('', 'saldo', 'ASC', $today, 10000);
                    $count = $deudores->count();
                    Log::info(">>> Tabla [{$tabla}]: count = {$count} (cálculo)");
                    $totalRegistros += $count;
                } else {
                    $model = $tablaConfig['model'];
                    $campoSync = $tablaConfig['campo_sync'] ?? 'sincronizado';
                    $campoFecha = $tablaConfig['campo_fecha'] ?? 'created_at';
                    
                    // Validar que la tabla existe en la base de datos
                    try {
                        $tableName = (new $model)->getTable();
                        if (!Schema::hasTable($tableName)) {
                            continue;
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                    
                    $query = $model::query();
                    
                    // Para INVENTARIOS: contar TODO sin ningún filtro
                    if ($tabla === 'inventarios') {
                        Log::info(">>> INVENTARIOS: NO aplicando filtros. Total en BD: " . $model::count());
                    } else {
                        // Para otras tablas: aplicar filtros normales
                        if ($soloNuevos) {
                            $query->where($campoSync, 0);
                        }
                        $query->where($campoFecha, '>=', $fechaInicio);
                        
                        // Para CIERRES: solo contar cierres de tipo administrador (tipo_cierre = 1)
                        if ($tabla === 'cierres') {
                            $query->where('tipo_cierre', 1);
                        }
                    }
                    
                    $count = $query->count();
                    Log::info(">>> Tabla [{$tabla}]: count = {$count}");
                    $totalRegistros += $count;
                }
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
        $procesados = 0;
        $errores = [];
        
        // Caso especial: deudores (cálculo, no tabla)
        if (isset($config['es_calculo']) && $config['es_calculo']) {
            return $this->sincronizarDeudores($nombreTabla, $config, $progressCallback);
        }
        
        $model = $config['model'];
        $campoSync = $config['campo_sync'] ?? 'sincronizado';
        $campoFecha = $config['campo_fecha'] ?? 'created_at';
        
        // Construir query base
        // Validar que la tabla existe en la base de datos
        try {
            $tableName = (new $model)->getTable();
            if (!Schema::hasTable($tableName)) {
                throw new \Exception("La tabla '{$tableName}' no existe en la base de datos");
            }
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'no existe') !== false) {
                throw $e;
            }
            throw new \Exception("Error validando tabla: " . $e->getMessage());
        }
        
        $query = $model::query();
        
        Log::info(">>> sincronizarTabla() - nombreTabla: {$nombreTabla}, soloNuevos: " . ($soloNuevos ? 'true' : 'false'));
        
        // Para INVENTARIOS: enviar TODO sin ningún filtro
        if ($nombreTabla === 'inventarios') {
            Log::info(">>> INVENTARIOS DETECTADO - NO aplicando ningún filtro");
            Log::info(">>> Total inventario en BD: " . $model::count());
        } else {
            // Para otras tablas: aplicar filtros normales
            if ($soloNuevos) {
                $query->where($campoSync, 0);
                Log::info(">>> Aplicando filtro: {$campoSync} = 0");
            }
            $fechaInicio = $this->getFechaInicioSync();
            $query->where($campoFecha, '>=', $fechaInicio);
            Log::info("    [{$nombreTabla}] Filtrando desde: {$fechaInicio}");
            
            // Para CIERRES: solo sincronizar cierres de tipo administrador (tipo_cierre = 1)
            if ($nombreTabla === 'cierres') {
                $query->where('tipo_cierre', 1);
                Log::info("    [{$nombreTabla}] Filtrando solo cierres tipo administrador (tipo_cierre = 1)");
            }
        }
        
        $total = $query->count();
        Log::info(">>> Query count para {$nombreTabla}: {$total}");
        
        if ($total === 0) {
            return ['procesados' => 0, 'errores' => []];
        }
        
        Log::info("    [{$nombreTabla}] Total a procesar: {$total}");
        
        // Procesar en lotes
        Log::info(">>> Iniciando chunk para {$nombreTabla}. Query SQL: " . $query->toSql());
        $query->orderBy('id')->chunk(self::BATCH_SIZE, function($registros) use ($nombreTabla, $config, $campoSync, &$procesados, &$errores, $total, $progressCallback) {
            Log::info(">>> Procesando lote de {$nombreTabla}: " . count($registros) . " registros");
            // Si hay función de transformación, aplicarla
            $transformar = $config['transformar'] ?? null;
            if ($nombreTabla === 'pedidos') {
                $registros->load('vendedor');
            }
            
            $data = $registros->map(function($r, $index) use ($config, $transformar, $nombreTabla) {
                // Obtener todos los campos requeridos, asegurando que se incluyan incluso si son null o 0
                $registro = [];
                $atributos = array_merge(
                    $r->getAttributes(),  // Atributos en $fillable
                    $r->getOriginal()    // Valores originales de la BD
                );
                
                // Log para cierres y campos digitales
                $esCierre = ($nombreTabla === 'cierres');
                if ($esCierre && $index === 0) {
                    Log::info("SYNCPROGRESS - Procesando cierre ID: " . ($r->id ?? 'N/A'));
                    Log::info("SYNCPROGRESS - debito_digital en getAttributes: " . var_export($r->getAttributes()['debito_digital'] ?? 'NO EXISTE', true));
                    Log::info("SYNCPROGRESS - efectivo_digital en getAttributes: " . var_export($r->getAttributes()['efectivo_digital'] ?? 'NO EXISTE', true));
                    Log::info("SYNCPROGRESS - transferencia_digital en getAttributes: " . var_export($r->getAttributes()['transferencia_digital'] ?? 'NO EXISTE', true));
                    Log::info("SYNCPROGRESS - debito_digital en getOriginal: " . var_export($r->getOriginal()['debito_digital'] ?? 'NO EXISTE', true));
                    Log::info("SYNCPROGRESS - efectivo_digital en getOriginal: " . var_export($r->getOriginal()['efectivo_digital'] ?? 'NO EXISTE', true));
                    Log::info("SYNCPROGRESS - transferencia_digital en getOriginal: " . var_export($r->getOriginal()['transferencia_digital'] ?? 'NO EXISTE', true));
                    Log::info("SYNCPROGRESS - debito_digital desde getAttribute: " . var_export($r->getAttribute('debito_digital'), true));
                    Log::info("SYNCPROGRESS - efectivo_digital desde getAttribute: " . var_export($r->getAttribute('efectivo_digital'), true));
                    Log::info("SYNCPROGRESS - transferencia_digital desde getAttribute: " . var_export($r->getAttribute('transferencia_digital'), true));
                }
                
                // Log para pago_pedidos y monto_original
                $esPagoPedidos = ($nombreTabla === 'pago_pedidos');
                if ($esPagoPedidos && $index === 0) {
                    Log::info("SYNCPROGRESS - Procesando pago_pedidos ID: " . ($r->id ?? 'N/A'));
                    Log::info("SYNCPROGRESS - monto_original en getAttributes: " . var_export($r->getAttributes()['monto_original'] ?? 'NO EXISTE', true));
                    Log::info("SYNCPROGRESS - monto_original en getOriginal: " . var_export($r->getOriginal()['monto_original'] ?? 'NO EXISTE', true));
                    Log::info("SYNCPROGRESS - monto_original desde getAttribute: " . var_export($r->getAttribute('monto_original'), true));
                    Log::info("SYNCPROGRESS - monto en getAttributes: " . var_export($r->getAttributes()['monto'] ?? 'NO EXISTE', true));
                }
                
                foreach ($config['campos'] as $campo) {
                    // Intentar obtener desde atributos primero
                    if (array_key_exists($campo, $atributos)) {
                        $registro[$campo] = $atributos[$campo];
                    } else {
                        // Si no está en atributos, intentar obtenerlo directamente del modelo
                        $valor = $r->getAttribute($campo);
                        $registro[$campo] = $valor !== null ? $valor : null;
                    }
                }
                if ($nombreTabla === 'pedidos') {
                    $registro['nombre_vendedor'] = $r->vendedor ? ($r->vendedor->nombre ?? null) : null;
                }
                
                // Log para cierres después de procesar
                if ($esCierre && $index === 0) {
                    Log::info("SYNCPROGRESS - debito_digital en registro final: " . var_export($registro['debito_digital'] ?? 'NO EXISTE', true));
                    Log::info("SYNCPROGRESS - efectivo_digital en registro final: " . var_export($registro['efectivo_digital'] ?? 'NO EXISTE', true));
                    Log::info("SYNCPROGRESS - transferencia_digital en registro final: " . var_export($registro['transferencia_digital'] ?? 'NO EXISTE', true));
                }
                
                // Log para pago_pedidos después de procesar
                if ($esPagoPedidos && $index === 0) {
                    Log::info("SYNCPROGRESS - monto_original en registro final: " . var_export($registro['monto_original'] ?? 'NO EXISTE', true));
                    Log::info("SYNCPROGRESS - monto en registro final: " . var_export($registro['monto'] ?? 'NO EXISTE', true));
                }
                
                // Para CIERRES: extraer lotes de métodos de pago y anclarlos al cierre
                if ($esCierre) {
                    $lotes = [];
                    
                    // Cargar métodos de pago del cierre
                    $r->load('metodosPago');
                    $today = $r->fecha;
                    
                    Log::info("SYNCPROGRESS - Cierre ID: {$r->id}, Total métodos de pago: " . count($r->metodosPago));
                    
                    // Extraer lotes desde CierresMetodosPago con subtipo 'otros_puntos' y 'pinpad'
                    foreach ($r->metodosPago as $metodo_pago) {
                        $metadatos = is_string($metodo_pago->metadatos) 
                            ? json_decode($metodo_pago->metadatos, true) 
                            : $metodo_pago->metadatos;
                        
                        Log::info("SYNCPROGRESS - Método de pago ID: {$metodo_pago->id}, Subtipo: {$metodo_pago->subtipo}");
                        
                        if ($metodo_pago->subtipo == 'pinpad') {
                            // Procesar lotes pinpad
                            $lotes_pinpad = $metadatos['lotes'] ?? [];
                            Log::info("SYNCPROGRESS - Lotes PINPAD encontrados: " . count($lotes_pinpad));
                            foreach ($lotes_pinpad as $loteIndex => $lote) {
                                $loteData = [
                                    "idinsucursal" => "PINPAD-".$r->id."-".$loteIndex,
                                    "monto" => floatval($lote['monto_bs'] ?? $lote['monto'] ?? 0),
                                    "banco" => $lote['banco_nombre'] ?? $lote['banco'] ?? '',
                                    "loteserial" => $lote['terminal'] ?? $lote['lote'] ?? '',
                                    "fecha" => $today,
                                    "id_usuario" => $r->id_usuario,
                                    "categoria" => 1,
                                    "tipo" => "PINPAD",
                                    "debito_credito" => null,
                                    "origen" => 1,
                                ];
                                array_push($lotes, $loteData);
                                Log::info("SYNCPROGRESS - Lote PINPAD agregado: " . json_encode($loteData));
                            }
                        } else if ($metodo_pago->subtipo == 'otros_puntos') {
                            // Procesar otros puntos
                            $puntos = $metadatos['puntos'] ?? [];
                            Log::info("SYNCPROGRESS - Puntos encontrados: " . count($puntos));
                            foreach ($puntos as $puntoIndex => $punto) {
                                $puntoData = [
                                    "idinsucursal" => "PUNTO-".$r->id."-".$puntoIndex,
                                    "monto" => floatval($punto['monto_real'] ?? $punto['monto'] ?? 0),
                                    "banco" => $punto['banco'] ?? '',
                                    "loteserial" => $punto['descripcion'] ?? $punto['lote'] ?? '',
                                    "fecha" => $punto['fecha'] ?? $today,
                                    "id_usuario" => $punto['id_usuario'] ?? $r->id_usuario,
                                    "categoria" => 1,
                                    "tipo" => "PUNTO X",
                                    "debito_credito" => null,
                                    "origen" => 1,
                                ];
                                array_push($lotes, $puntoData);
                                Log::info("SYNCPROGRESS - Punto agregado: " . json_encode($puntoData));
                            }
                        }
                    }
                    
                    Log::info("SYNCPROGRESS - Total lotes generados para cierre {$r->id}: " . count($lotes));
                    
                    // Anclar lotes al registro del cierre
                    $registro['lotes'] = $lotes;
                }
                
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
                        ->withHeaders($this->centralRequestHeaders())
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
                            // EXCEPTO para inventarios - no actualizar el campo push
                            if ($nombreTabla !== 'inventarios') {
                                $ids = $registros->pluck('id')->toArray();
                                $updateData = [$campoSync => 1];
                                
                                // Si no es 'push', también actualizar sincronizado_at
                                if ($campoSync !== 'push') {
                                    $updateData['sincronizado_at'] = now();
                                }
                                
                                $config['model']::whereIn('id', $ids)->update($updateData);
                            }
                            
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
     * Sincroniza deudores (cálculo de deuda de trabajadores)
     * Obtiene la lista completa usando getDeudoresFun y la envía a Central
     */
    private function sincronizarDeudores($nombreTabla, $config, $progressCallback = null)
    {
        $procesados = 0;
        $errores = [];
        
        Log::info(">>> sincronizarDeudores() - Iniciando sincronización de deudores");
        
        try {
            // Obtener lista completa de deudores usando getDeudoresFun
            $pagoPedidosController = new PagoPedidosController();
            $pedidosController = new PedidosController();
            $today = $pedidosController->today();
            
            // Obtener todos los deudores (sin límite o con límite alto)
            $deudores = $pagoPedidosController->getDeudoresFun('', 'saldo', 'ASC', $today, 100000);
            
            $total = $deudores->count();
            Log::info(">>> Total deudores a sincronizar: {$total}");
            
            if ($total === 0) {
                return ['procesados' => 0, 'errores' => []];
            }
            
            // Transformar datos al formato esperado por el modelo créditos en Central
            // Solo incluir clientes con saldo negativo (deudores)
            $data = $deudores->filter(function($cliente) {
                // El saldo viene calculado: abono - credito
                // Si saldo < 0, es deudor
                return isset($cliente->saldo) && floatval($cliente->saldo) < 0;
            })->map(function($cliente) {
                return [
                    // Información del cliente (completa)
                    'cliente' => [
                        'id_insucursal' => $cliente->id, // ID en la sucursal
                        'identificacion' => $cliente->identificacion ?? null,
                        'nombre' => $cliente->nombre ?? null,
                        'correo' => $cliente->correo ?? null,
                        'direccion' => $cliente->direccion ?? null,
                        'telefono' => $cliente->telefono ?? null,
                        'estado' => $cliente->estado ?? null,
                        'ciudad' => $cliente->ciudad ?? null,
                    ],
                    // Información del crédito
                    'saldo' => $cliente->saldo, // Valor absoluto del saldo (deuda)
                ];
            })->values()->toArray();
            
            $totalDeudores = count($data);
            Log::info(">>> Total deudores con saldo negativo: {$totalDeudores}");
            
            if ($totalDeudores === 0) {
                return ['procesados' => 0, 'errores' => []];
            }
            
            // Procesar en lotes
            $chunks = array_chunk($data, self::BATCH_SIZE);
            
            foreach ($chunks as $chunk) {
                $maxReintentos = 3;
                $exito = false;
                $ultimoError = null;
                
                for ($intento = 1; $intento <= $maxReintentos && !$exito; $intento++) {
                    try {
                        // Guardar checkpoint antes de enviar
                        Cache::put('sync_checkpoint', [
                            'tabla' => $nombreTabla,
                            'lote_actual' => $procesados,
                            'total' => $totalDeudores,
                            'timestamp' => now()->toISOString(),
                        ], 3600);
                        
                        $response = Http::timeout(120)
                            ->withHeaders($this->centralRequestHeaders())
                            ->retry(2, 1000)
                            ->post($this->getCentralUrl() . "/api/sync/batch", [
                                'codigo_origen' => $this->getCodigoOrigen(),
                                'tabla' => $nombreTabla,
                                'tabla_destino' => $config['tabla_destino'],
                                'data' => base64_encode(gzcompress(json_encode($chunk))),
                                'batch_size' => count($chunk),
                            ]);
                        
                        if ($response->ok()) {
                            $resultado = $response->json();
                            
                            if ($resultado['estado'] ?? false) {
                                $procesados += count($chunk);
                                $exito = true;
                                Log::info("    [{$nombreTabla}] Lote enviado: " . count($chunk) . " registros");
                            } else {
                                $ultimoError = $resultado['mensaje'] ?? 'Error desconocido';
                            }
                        } else {
                            $ultimoError = 'HTTP ' . $response->status();
                        }
                    } catch (\Exception $e) {
                        $ultimoError = $e->getMessage();
                        
                        if ($intento < $maxReintentos) {
                            $waitSeconds = pow(2, $intento);
                            Log::warning("    [{$nombreTabla}] Reintento {$intento}/{$maxReintentos} en {$waitSeconds}s: {$ultimoError}");
                            sleep($waitSeconds);
                        }
                    }
                }
                
                if (!$exito && $ultimoError) {
                    $errores[] = "Falló después de {$maxReintentos} intentos: {$ultimoError}";
                    Log::error("    [{$nombreTabla}] Error persistente: {$ultimoError}");
                }
                
                // Callback de progreso
                if ($progressCallback) {
                    $progressCallback($procesados, $totalDeudores);
                }
                
                // Pausa para no saturar
                usleep(50000); // 50ms
            }
            
            Log::info("<<< {$nombreTabla} completado: {$procesados} deudores sincronizados");
            
        } catch (\Exception $e) {
            Log::error("Error sincronizando deudores: " . $e->getMessage());
            $errores[] = $e->getMessage();
        }
        
        return [
            'procesados' => $procesados,
            'eliminados' => 0,
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
            $response = Http::timeout(120)
                ->withHeaders($this->centralRequestHeaders())
                ->post($this->getCentralUrl() . "/api/sync/limpiar-obsoletos", [
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
        $campoFecha = $tablaConfig['campo_fecha'] ?? 'created_at';
        $model = $tablaConfig['model'];
        $query = $model::query();
        
        if ($desde && $hasta) {
            // Si son fechas
            if (strtotime($desde)) {
                $query->whereBetween($campoFecha, [$desde, $hasta]);
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
    
    /**
     * Desmarcar registros como sincronizados (marcar como no sincronizados)
     * Útil para re-sincronizar registros de un día específico
     * Si no se proporciona 'tabla', procesa todas las tablas
     */
    public function desmarcarSincronizados(Request $request)
    {
        $tabla = $request->input('tabla'); // Opcional: si no se proporciona, procesa todas
        $fecha = $request->input('fecha'); // Fecha específica (formato: Y-m-d)
        $desde = $request->input('desde'); // fecha o ID (opcional, para rango)
        $hasta = $request->input('hasta'); // fecha o ID (opcional, para rango)
        
        if (!$fecha && !$desde && !$hasta) {
            return Response::json([
                'estado' => false,
                'mensaje' => 'Debe proporcionar una fecha o un rango (desde/hasta)',
            ], 400);
        }
        
        $config = $this->getTablesConfig();
        $resultados = [];
        $totalActualizados = 0;
        
        // Si se proporciona tabla, procesar solo esa. Si no, procesar todas
        $tablasAProcesar = $tabla ? [$tabla => $config[$tabla]] : $config;
        
        foreach ($tablasAProcesar as $key => $tablaConfig) {
            // Validar que el modelo existe y es válido
            if (is_null($tablaConfig['model']) || 
                (!is_string($tablaConfig['model']) && !is_object($tablaConfig['model'])) || 
                (is_string($tablaConfig['model']) && !class_exists($tablaConfig['model']))) {
                continue;
            }
            
            // Validar que la tabla existe en la base de datos
            try {
                $tableName = (new $tablaConfig['model'])->getTable();
                if (!Schema::hasTable($tableName)) {
                    continue;
                }
            } catch (\Exception $e) {
                continue;
            }
            
            try {
                $campoSync = $tablaConfig['campo_sync'] ?? 'sincronizado';
                $campoFecha = $tablaConfig['campo_fecha'] ?? 'created_at';
                $model = $tablaConfig['model'];
                $query = $model::query();
                
                // Si se proporciona una fecha específica, usar esa
                if ($fecha) {
                    // Para cierres, usar campo 'fecha', para otros usar 'created_at' o el campo configurado
                    if ($key === 'cierres') {
                        $query->where('fecha', $fecha);
                        // Para cierres, también filtrar solo tipo administrador
                        $query->where('tipo_cierre', 1);
                    } else {
                        $query->whereDate($campoFecha, $fecha);
                    }
                } elseif ($desde && $hasta) {
                    // Si son fechas
                    if (strtotime($desde)) {
                        if ($key === 'cierres') {
                            $query->whereBetween('fecha', [$desde, $hasta])
                                  ->where('tipo_cierre', 1);
                        } else {
                            $query->whereBetween($campoFecha, [$desde, $hasta]);
                        }
                    } else {
                        // Si son IDs
                        $query->whereBetween('id', [$desde, $hasta]);
                    }
                }
                
                $updateData = [$campoSync => 0];
                if ($campoSync !== 'push') {
                    $updateData['sincronizado_at'] = null;
                }
                
                $actualizados = $query->update($updateData);
                $totalActualizados += $actualizados;
                
                $resultados[$key] = [
                    'tabla' => $tablaConfig['nombre'],
                    'registros_actualizados' => $actualizados,
                ];
                
                Log::info("Desmarcados {$actualizados} registros de {$key} para fecha " . ($fecha ?? "rango {$desde} - {$hasta}"));
                
            } catch (\Exception $e) {
                $resultados[$key] = [
                    'tabla' => $tablaConfig['nombre'],
                    'error' => $e->getMessage(),
                ];
                Log::error("Error desmarcando {$key}: " . $e->getMessage());
            }
        }
        
        $mensaje = $tabla 
            ? "Se desmarcaron {$totalActualizados} registros de {$tabla}"
            : "Se desmarcaron {$totalActualizados} registros en " . count($resultados) . " tablas";
        
        return Response::json([
            'estado' => true,
            'mensaje' => $mensaje,
            'total_actualizados' => $totalActualizados,
            'fecha' => $fecha ?? "rango {$desde} - {$hasta}",
            'detalle' => $resultados,
        ]);
    }
}
