<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Response;

class AuthenticateUser
{
    /**
     * Matriz única con todas las rutas permitidas para DICI (tipo_usuario = 7)
     * 
     * Esta matriz centraliza todas las rutas que puede acceder el usuario DICI
     * tanto en middleware 'login' como en middleware 'admin'.
     * 
     * Para agregar una nueva ruta para DICI, simplemente agregarla a esta matriz.
     */
    private const DICI_ALLOWED_ROUTES = [
        // Tareas y sincronización
        'resolverTareaLocal',
        'getTareasLocal',
        'runTareaCentral',
        'getTareasCentral',
        'checkPedidosCentral',
        'sincInventario',
        
        // Inventario y productos
        'guardarNuevoProductoLote',
        'getmovientoinventariounitario',
        'searchProductosInventario',
        'producto',
        'inventarios/buscar',
        
        // Pedidos y transferencias
        'reqpedidos',
        'reqMipedidos',
        'settransferenciaDici',
        
        // Sucursales
        'getSucursales',
        
        // Facturas
        'validateFactura',
        
        // Responsables
        'searchResponsables',
        'saveResponsable',
        'responsable',
        'responsables/tipo',
        
        // Garantías
        'garantias/crear',
        'garantias/crear-pedido',

        // Inventario Cíclico
        'inventario-ciclico/planillas',
        'inventario-ciclico/planillas/crear',
        'inventario-ciclico/planillas/{id}',
        'inventario-ciclico/planillas/{id}/productos',
        'inventario-ciclico/planillas/{id}/reporte',
        'inventario-ciclico/planillas/{id}/reporte-pdf',
        'inventario-ciclico/planillas/{id}/enviar-tareas',
        'inventario-ciclico/planillas/{planillaId}/productos/{detalleId}',
        'getUsuarios',

        "warehouses/generar-ubicaciones",
        "warehouses/disponibles",
        "warehouses/buscar-codigo",
        "warehouses/sugerir-ubicacion",
        "warehouses/reporte-ocupacion",
        "warehouses/buscar",
        "warehouses",
        "warehouses",
        "warehouses/{id}",
        "warehouses/{id}",
        "warehouses/{id}",
        "warehouse-inventory/buscar-productos",
        "warehouse-inventory/buscar-codigo",
        "warehouse-inventory/producto/{inventarioId}",
        "warehouse-inventory/producto/{inventarioId}/ubicaciones",
        "warehouse-inventory/ubicacion/{warehouseId}",
        "warehouse-inventory/por-ubicacion",
        "warehouse-inventory/{id}/ticket",
        "warehouse-inventory/historial",
        "warehouse-inventory/proximos-vencer",
        "warehouse-inventory",
        "warehouse-inventory/asignar",
        "warehouse-inventory/transferir",
        "warehouse-inventory/retirar",
        "warehouse-inventory/{id}/estado",
        "warehouse-inventory/stock-ubicacion",
        "warehouse-inventory/ticket-producto/{productoId}",
        "warehouse-inventory/imprimir-ticket-producto",
        
        // Módulo TCR (nuevo sistema con chequeador y pasillero)
        "warehouse-inventory/tcr",
        "warehouse-inventory/tcr/pasillero",
        "warehouse-inventory/tcr/get-pedidos-central",
        "warehouse-inventory/tcr/get-pasilleros",
        "warehouse-inventory/tcr/asignar-productos",
        "warehouse-inventory/tcr/mis-asignaciones",
        "warehouse-inventory/tcr/procesar-asignacion",
        "warehouse-inventory/tcr/buscar-ubicacion",
        "warehouse-inventory/tcr/asignaciones-por-pedido",
        "warehouse-inventory/tcr/confirmar-pedido",
        
        // Módulo TCD (Torre de Control de Despacho)
        "warehouse-inventory/tcd",
        "warehouse-inventory/tcd/pasillero",
        "warehouse-inventory/tcd/buscar-productos",
        "warehouse-inventory/tcd/crear-orden",
        "warehouse-inventory/tcd/get-pasilleros",
        "warehouse-inventory/tcd/asignar-productos",
        "warehouse-inventory/tcd/get-ordenes",
        "warehouse-inventory/tcd/get-asignaciones-por-orden",
        "warehouse-inventory/tcd/mis-asignaciones",
        "warehouse-inventory/tcd/procesar-asignacion",
        "warehouse-inventory/tcd/buscar-ubicacion",
        "warehouse-inventory/tcd/confirmar-orden",
        "warehouse-inventory/tcd/reversar-asignacion",
        "warehouse-inventory/tcd/escanear-ticket-despacho",
        "warehouse-inventory/tcd/get-sucursales-disponibles",
        "warehouse-inventory/tcd/transferir-orden-sucursal",
        "warehouse-inventory/tcd/nota-entrega",
        
        // Inventariar productos (tres pasos: producto, ubicación, cantidad)
        "inventario/inventariar",
        "inventario/buscar-producto-inventariar",
        "inventario/buscar-ubicacion-inventariar",
        "inventario/guardar-inventario-con-ubicacion",
        "inventario/reporte-planilla-pdf",
        
        // Cargar ubicaciones por rango
        "warehouses/cargar-por-rango",
        "warehouses/generar-por-rango",
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $accessType
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $accessType = null)
    {
        // Intentar validar sesión por token primero
        $sessionId = $request->header('X-Session-Token') ?? 
                    $request->cookie('session_token');
        
        $sessionData = null;
        if ($sessionId) {
            $sessionData = app(\App\Services\SessionManager::class)->validateSession($sessionId);
        }
        //dd($sessionData,$sessionId);
        
        // Fallback a sesión legacy si no hay token válido
        if (!$sessionData && session('tipo_usuario')) {
            $userType = session('tipo_usuario');
            $sessionData = [
                'legacy_data' => [
                    'tipo_usuario' => $userType,
                    'id_usuario' => session('id_usuario'),
                    'usuario' => session('usuario'),
                    'nombre' => session('nombre_usuario'),
                    'nivel' => session('nivel'),
                    'role' => session('role'),
                    'iscentral' => session('iscentral', false)
                ]
            ];
        }
        
        
        // Si no hay sesión válida, denegar acceso
        if (!$sessionData) {
            // Si es una petición AJAX, devolver JSON
            if ($request->expectsJson() || $request->is('api/*')) {
                return Response::json([
                    "msj" => "Error: Sin sesión activa. Debe volver a iniciar sesión",
                    "estado" => false
                ]);
            }
            
            // Si es una petición web, redirigir al login
            return redirect('/login')->with('error', 'Sesión expirada. Debe volver a iniciar sesión.');
        }
        
        $userType = $sessionData['legacy_data']['tipo_usuario'];
        
        // Verificar acceso según el tipo requerido
        if ($accessType && !$this->hasAccess($userType, $accessType, $request)) {
            return Response::json([
                "msj" => "Error: Sin permisos para acceder a {$accessType}",
                "estado" => false
            ]);
        }
        
        return $next($request);
    }
    
    /**
     * Verificar si el usuario tiene acceso según su tipo y el acceso requerido
     */
    private function hasAccess(int $userType, string $accessType, Request $request): bool
    {
        return match($accessType) {
            'login' => $this->hasLoginAccess($userType, $request),
            'admin' => $this->hasAdminAccess($userType, $request),
            'caja' => $this->hasCajaAccess($userType),
            'vendedor' => $this->hasVendedorAccess($userType),
            'api' => $this->hasApiAccess($userType, $request),
            default => false
        };
    }
    
    /**
     * Acceso para middleware 'login' - Tipos 1, 4, 6, 7, 8 + tipo 7 con rutas específicas
     * 1 = GERENTE
     * 4 = CAJERO_VENDEDOR
     * 5 = SUPERVISOR DE CAJA (solo inventario y tickets)
     * 6 = SUPERADMIN
     * 7 = DICI (chequeador)
     * 8 = PASILLERO
     */
    private function hasLoginAccess(int $userType, Request $request): bool
    {
        // Tipos básicos que siempre tienen acceso
        if (in_array($userType, [1, 4, 6])) {
            return true;
        }
        
        // Tipo 5 (SUPERVISOR DE CAJA) solo tiene acceso a inventario y tickets
        if ($userType == 5) {
            $routeUri = $request->route() ? $request->route()->uri : $request->path();
            $allowedRoutes = [
                'getinventario', // Consultar inventario
                'imprimirTicked', // Aprobar impresiones de tickets
                'resetPrintingState', // Resetear estado de impresión
            ];
            
            return in_array($routeUri, $allowedRoutes);
        }
        
        // Tipo 7 (DICI/Chequeador) solo tiene acceso a rutas específicas
        if ($userType == 7) {
            $routeUri = $request->route() ? $request->route()->uri : $request->path();
            $path = $request->path();
            
            // Verificar coincidencia exacta
            if (in_array($routeUri, self::DICI_ALLOWED_ROUTES)) {
                return true;
            }
            
            // Verificar rutas con parámetros usando patrones
            foreach (self::DICI_ALLOWED_ROUTES as $allowedRoute) {
                // Si la ruta tiene parámetros {param}, crear un patrón regex
                if (strpos($allowedRoute, '{') !== false) {
                    $pattern = '#^' . preg_replace('/\{[^}]+\}/', '[^/]+', $allowedRoute) . '$#';
                    if (preg_match($pattern, $path)) {
                        return true;
                    }
                }
            }
            
            return false;
        }
        
        // Tipo 8 (Pasillero) solo tiene acceso a rutas del pasillero
        if ($userType == 8) {
            $routeUri = $request->route() ? $request->route()->uri : $request->path();
            $routeName = $request->route() ? $request->route()->getName() : null;
            $path = $request->path();
            
            $pasilleroRoutes = [
                // Ruta principal de warehouse-inventory
                'warehouse-inventory',
                // Rutas TCR Pasillero
                'warehouse-inventory/tcr/pasillero',
                'warehouse-inventory/tcr/mis-asignaciones',
                'warehouse-inventory/tcr/procesar-asignacion',
                'warehouse-inventory/tcr/buscar-ubicacion',
                // Rutas de novedades TCR
                'warehouse-inventory/tcr/buscar-o-registrar-novedad',
                'warehouse-inventory/tcr/agregar-cantidad-novedad',
                'warehouse-inventory/tcr/actualizar-cantidad-llego-novedad',
                'warehouse-inventory/tcr/get-novedades',
                // Rutas TCD Pasillero
                'warehouse-inventory/tcd/pasillero',
                'warehouse-inventory/tcd/mis-asignaciones',
                'warehouse-inventory/tcd/procesar-asignacion',
                'warehouse-inventory/tcd/buscar-ubicacion',
                // Ruta para imprimir ticket de producto
                'warehouse-inventory/imprimir-ticket-producto',
                // Ruta para buscar producto (para obtener ubicaciones)
                'inventario/buscar-producto-inventariar',
            ];
            
            $pasilleroRouteNames = [
                'warehouse-inventory.index',
                'warehouse-inventory.tcr.pasillero',
                'warehouse-inventory.tcr.mis-asignaciones',
                'warehouse-inventory.tcr.procesar-asignacion',
                'warehouse-inventory.tcr.buscar-ubicacion',
                'warehouse-inventory.tcr.buscar-o-registrar-novedad',
                'warehouse-inventory.tcr.agregar-cantidad-novedad',
                'warehouse-inventory.tcr.actualizar-cantidad-llego-novedad',
                'warehouse-inventory.tcr.get-novedades',
                'warehouse-inventory.tcd.pasillero',
                'warehouse-inventory.tcd.mis-asignaciones',
                'warehouse-inventory.tcd.procesar-asignacion',
                'warehouse-inventory.tcd.buscar-ubicacion',
                'warehouse-inventory.imprimir-ticket-producto',
                'inventario.buscar-producto-inventariar',
            ];
            
            // Verificar por URI, nombre de ruta o path
            return in_array($routeUri, $pasilleroRoutes) 
                || ($routeName && in_array($routeName, $pasilleroRouteNames))
                || in_array($path, $pasilleroRoutes)
                || str_starts_with($path, 'warehouse-inventory/tcr/pasillero')
                || str_starts_with($path, 'warehouse-inventory/tcd/pasillero')
                || str_starts_with($path, 'warehouse-inventory/tcr/mis-asignaciones')
                || str_starts_with($path, 'warehouse-inventory/tcr/procesar-asignacion')
                || str_starts_with($path, 'warehouse-inventory/tcr/buscar-ubicacion')
                || str_starts_with($path, 'warehouse-inventory/tcr/buscar-o-registrar-novedad')
                || str_starts_with($path, 'warehouse-inventory/tcr/agregar-cantidad-novedad')
                || str_starts_with($path, 'warehouse-inventory/tcr/actualizar-cantidad-llego-novedad')
                || str_starts_with($path, 'warehouse-inventory/tcr/get-novedades')
                || str_starts_with($path, 'warehouse-inventory/tcd/mis-asignaciones')
                || str_starts_with($path, 'warehouse-inventory/tcd/procesar-asignacion')
                || str_starts_with($path, 'warehouse-inventory/tcd/buscar-ubicacion')
                || str_starts_with($path, 'warehouse-inventory/imprimir-ticket-producto');
        }
        
        return false;
    }
    
    /**
     * Acceso para middleware 'admin' - Tipos 1, 6 + tipo 7 con rutas específicas
     * 1 = GERENTE
     * 6 = SUPERADMIN
     * 7 = DICI (solo rutas específicas)
     */
    private function hasAdminAccess(int $userType, Request $request): bool
    {
        // GERENTE y SuperAdmin siempre tienen acceso
        if (in_array($userType, [1, 6])) {
            return true;
        }
        
        // Tipo 7 (DICI) solo tiene acceso a rutas específicas
        if ($userType == 7) {
            $routeUri = $request->route() ? $request->route()->uri : $request->path();
            $path = $request->path();
            
            // Verificar coincidencia exacta
            if (in_array($routeUri, self::DICI_ALLOWED_ROUTES)) {
                return true;
            }
            
            // Verificar rutas con parámetros usando patrones
            foreach (self::DICI_ALLOWED_ROUTES as $allowedRoute) {
                // Si la ruta tiene parámetros {param}, crear un patrón regex
                if (strpos($allowedRoute, '{') !== false) {
                    $pattern = '#^' . preg_replace('/\{[^}]+\}/', '[^/]+', $allowedRoute) . '$#';
                    if (preg_match($pattern, $path)) {
                        return true;
                    }
                }
            }
            
            return false;
        }
        
        return false;
    }
    
    /**
     * Acceso para middleware 'caja' - Tipos 1, 4, 6
     * 1 = GERENTE
     * 4 = CAJERO_VENDEDOR
     * 6 = SUPERADMIN
     */
    private function hasCajaAccess(int $userType): bool
    {
        return in_array($userType, [1, 4, 6]);
    }
    
    /**
     * Acceso para middleware 'vendedor' - Tipos 1, 4, 6
     * 1 = GERENTE
     * 4 = CAJERO_VENDEDOR
     * 6 = SUPERADMIN
     */
    private function hasVendedorAccess(int $userType): bool
    {
        return in_array($userType, [1, 4, 6]);
    }
    
    /**
     * Acceso para rutas API - Solo DICI (tipo 7)
     * 7 = DICI
     */
    private function hasApiAccess(int $userType, Request $request): bool
    {
        if ($userType == 6) {
            return true;
        }
        // Solo DICI puede acceder a las rutas API
        return $userType == 7;
    }
} 