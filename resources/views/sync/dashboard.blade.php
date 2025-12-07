<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sincronización a Central - Arabito</title>
    <link rel="icon" type="image/png" href="{{ asset('images/icon.ico') }}">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .progress-bar {
            transition: width 0.3s ease-in-out;
        }
        .status-badge {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        .sync-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .grupo-primero { border-left: 4px solid #3b82f6; }
        .grupo-segundo { border-left: 4px solid #10b981; }
        .grupo-tercero { border-left: 4px solid #f59e0b; }
        .grupo-ultimo { border-left: 4px solid #ef4444; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Sincronización a Central</h1>
                    <p class="text-gray-600 mt-1">Sucursal: <span id="sucursal-codigo" class="font-semibold text-blue-600">Cargando...</span></p>
                </div>
                <div class="text-right">
                    <div id="estado-conexion" class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-gray-200 text-gray-600">
                        <span class="w-2 h-2 rounded-full mr-2 bg-gray-400"></span>
                        Verificando...
                    </div>
                </div>
            </div>
        </div>

        <!-- Resumen Global -->
        <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg shadow-md p-6 mb-6 text-white">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="text-center">
                    <p class="text-blue-100 text-sm">Total Registros</p>
                    <p id="total-registros" class="text-3xl font-bold">-</p>
                </div>
                <div class="text-center">
                    <p class="text-blue-100 text-sm">Sincronizados</p>
                    <p id="total-sincronizados" class="text-3xl font-bold text-green-300">-</p>
                </div>
                <div class="text-center">
                    <p class="text-blue-100 text-sm">Pendientes</p>
                    <p id="total-pendientes" class="text-3xl font-bold text-yellow-300">-</p>
                </div>
                <div class="text-center">
                    <p class="text-blue-100 text-sm">Progreso Global</p>
                    <p id="porcentaje-global" class="text-3xl font-bold">-</p>
                </div>
            </div>
            <div class="mt-4">
                <div class="w-full bg-blue-300 rounded-full h-4">
                    <div id="progress-global" class="progress-bar bg-white rounded-full h-4" style="width: 0%"></div>
                </div>
            </div>
        </div>

        <!-- Controles -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex flex-wrap gap-4 items-center justify-between">
                <div class="flex gap-4 flex-wrap">
                    <button id="btn-sync-pendientes" class="bg-green-500 hover:bg-green-600 text-white px-6 py-3 rounded-lg font-semibold flex items-center gap-2 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Sincronizar Pendientes
                    </button>
                    <button id="btn-actualizar" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold flex items-center gap-2 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Actualizar Estado
                    </button>
                </div>
                <div class="text-sm text-gray-600">
                    <span class="font-medium">Fecha de corte:</span> 2025-12-01 (solo se sincronizan registros desde esta fecha)
                </div>
            </div>
            
            <!-- Alerta informativa -->
            <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-800">
                <strong>Nota:</strong> Solo se sincronizan registros desde el 2025-12-01 en adelante. 
                Los registros anteriores nunca serán enviados a Central.
            </div>
            
            <!-- Aviso de Reanudación (si hay pendientes) -->
            <div id="aviso-reanudar" class="hidden mt-4 p-4 bg-green-50 border border-green-300 rounded-lg">
                <div class="flex items-center gap-3">
                    <svg class="w-6 h-6 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <p class="font-semibold text-green-800">¿Se interrumpió la sincronización?</p>
                        <p id="mensaje-reanudar" class="text-sm text-green-700">Puede continuar desde donde quedó. Los registros ya sincronizados no se volverán a enviar.</p>
                    </div>
                </div>
            </div>
            
            <!-- Estado de Sincronización Activa -->
            <div id="sync-activa" class="hidden mt-4 p-4 bg-blue-50 rounded-lg border border-blue-200">
                <div class="flex items-center justify-between mb-2">
                    <span class="font-semibold text-blue-700">Sincronizando...</span>
                    <span id="sync-tabla-actual" class="text-blue-600">-</span>
                </div>
                <div class="w-full bg-blue-200 rounded-full h-3 mb-2">
                    <div id="sync-progress" class="progress-bar bg-blue-500 rounded-full h-3" style="width: 0%"></div>
                </div>
                <div class="flex justify-between text-sm text-blue-600">
                    <span id="sync-procesados">0 / 0</span>
                    <span id="sync-tiempo">Tiempo estimado: calculando...</span>
                </div>
            </div>
        </div>

        <!-- Leyenda de Grupos -->
        <div class="bg-white rounded-lg shadow-md p-4 mb-6">
            <p class="text-sm text-gray-600 mb-2 font-semibold">Orden de sincronización:</p>
            <div class="flex flex-wrap gap-4 text-sm">
                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 bg-blue-500 rounded"></div>
                    <span>1° Inventarios</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 bg-green-500 rounded"></div>
                    <span>2° Pedidos y Estadísticas</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 bg-yellow-500 rounded"></div>
                    <span>3° Cierres y Cajas</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 bg-red-500 rounded"></div>
                    <span>4° Movimientos</span>
                </div>
            </div>
        </div>

        <!-- Tablas de Sincronización -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="tablas-container">
            <!-- Las tarjetas se generan dinámicamente -->
        </div>

        <!-- Log de Actividad -->
        <div class="bg-white rounded-lg shadow-md p-6 mt-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Log de Actividad</h2>
            <div id="log-container" class="bg-gray-900 text-green-400 rounded-lg p-4 h-48 overflow-y-auto font-mono text-sm">
                <p class="text-gray-500">Esperando actividad...</p>
            </div>
        </div>
    </div>

    <script>
        const API_BASE = '';
        let syncEnProgreso = false;
        
        // Inicialización
        document.addEventListener('DOMContentLoaded', async () => {
            await cargarEstado();
            
            // Verificar si es primera sincronización y auto-iniciarla
            await verificarPrimeraSincronizacion();
            
            // Auto-refresh cada 30 segundos
            setInterval(() => {
                if (!syncEnProgreso) {
                    cargarEstado();
                }
            }, 30000);
        });
        
        // Verificar y ejecutar primera sincronización automática
        async function verificarPrimeraSincronizacion() {
            try {
                // Primero verificar si hay registros antiguos que marcar
                const antiguosRes = await fetch(`${API_BASE}/sync/verificar-antiguos`);
                const antiguosData = await antiguosRes.json();
                
                if (antiguosData.estado && antiguosData.requiere_marcar) {
                    log(`Marcando ${antiguosData.pendientes_antiguos} registros anteriores a ${antiguosData.fecha_corte} como sincronizados...`, 'info');
                    
                    // Marcar registros antiguos como sincronizados
                    const marcarRes = await fetch(`${API_BASE}/sync/marcar-antiguos`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    });
                    const marcarData = await marcarRes.json();
                    
                    if (marcarData.estado) {
                        log(`✓ ${marcarData.mensaje}`, 'success');
                    }
                    
                    // Recargar estado
                    await cargarEstado();
                }
                
                // Ahora verificar si es primera sincronización
                const response = await fetch(`${API_BASE}/sync/es-primera`);
                const data = await response.json();
                
                if (data.estado && data.es_primera) {
                    log('Primera sincronización detectada - Iniciando automáticamente (desde 2025-12-01)...', 'info');
                    
                    // Mostrar aviso especial
                    const avisoReanudar = document.getElementById('aviso-reanudar');
                    const mensajeReanudar = document.getElementById('mensaje-reanudar');
                    avisoReanudar.classList.remove('hidden');
                    avisoReanudar.classList.remove('bg-green-50', 'border-green-300');
                    avisoReanudar.classList.add('bg-blue-50', 'border-blue-300');
                    mensajeReanudar.innerHTML = '<strong>🚀 Primera sincronización:</strong> Iniciando automáticamente con los registros desde 2025-12-01...';
                    mensajeReanudar.classList.remove('text-green-700');
                    mensajeReanudar.classList.add('text-blue-700');
                    
                    // Esperar 2 segundos para que el usuario vea el mensaje
                    await new Promise(resolve => setTimeout(resolve, 2000));
                    
                    // Iniciar primera sincronización
                    await iniciarPrimeraSincronizacion();
                }
            } catch (error) {
                console.error('Error verificando primera sincronización:', error);
            }
        }
        
        // Iniciar primera sincronización con filtro de 3 días
        async function iniciarPrimeraSincronizacion() {
            syncEnProgreso = true;
            mostrarSyncActiva(true);
            
            try {
                const response = await fetch(`${API_BASE}/sync/all`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        tablas: ['all'],
                        solo_nuevos: true,
                        primera_sync: true // Filtro de 3 días
                    })
                });
                
                const data = await response.json();
                
                if (data.estado) {
                    log('✅ Primera sincronización completada exitosamente', 'success');
                    
                    // Marcar como ejecutada para no repetir
                    await fetch(`${API_BASE}/sync/marcar-primera`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    });
                } else {
                    log('Error en primera sincronización: ' + data.mensaje, 'error');
                }
            } catch (error) {
                log('Error de conexión en primera sincronización: ' + error.message, 'error');
            } finally {
                syncEnProgreso = false;
                mostrarSyncActiva(false);
                cargarEstado();
            }
        }

        // Cargar estado actual
        async function cargarEstado() {
            try {
                const response = await fetch(`${API_BASE}/sync/status`);
                const data = await response.json();
                
                if (data.estado) {
                    actualizarUI(data);
                    actualizarConexion(true);
                    
                    // Verificar si hay pendientes para mostrar aviso de reanudación
                    verificarCheckpoint(data.resumen);
                } else {
                    actualizarConexion(false);
                }
            } catch (error) {
                console.error('Error cargando estado:', error);
                actualizarConexion(false);
                log('Error al conectar con el servidor', 'error');
            }
        }
        
        // Verificar checkpoint para mostrar aviso de reanudación
        function verificarCheckpoint(resumen) {
            const avisoReanudar = document.getElementById('aviso-reanudar');
            const mensajeReanudar = document.getElementById('mensaje-reanudar');
            
            if (resumen.total_pendientes > 0) {
                avisoReanudar.classList.remove('hidden');
                mensajeReanudar.textContent = `Hay ${formatNumber(resumen.total_pendientes)} registros pendientes. Al sincronizar, continuará automáticamente desde donde quedó.`;
            } else {
                avisoReanudar.classList.add('hidden');
            }
        }

        // Actualizar UI con datos
        function actualizarUI(data) {
            // Sucursal
            document.getElementById('sucursal-codigo').textContent = data.sucursal;
            
            // Resumen
            const resumen = data.resumen;
            document.getElementById('total-registros').textContent = formatNumber(resumen.total_registros);
            document.getElementById('total-sincronizados').textContent = formatNumber(resumen.total_sincronizados);
            document.getElementById('total-pendientes').textContent = formatNumber(resumen.total_pendientes);
            document.getElementById('porcentaje-global').textContent = resumen.porcentaje_global + '%';
            document.getElementById('progress-global').style.width = resumen.porcentaje_global + '%';
            
            // Tablas
            const container = document.getElementById('tablas-container');
            container.innerHTML = '';
            
            Object.entries(data.tablas).forEach(([key, tabla]) => {
                container.appendChild(crearTarjetaTabla(key, tabla));
            });
        }

        // Crear tarjeta de tabla
        function crearTarjetaTabla(key, tabla) {
            const div = document.createElement('div');
            div.className = `sync-card bg-white rounded-lg shadow-md p-4 transition-all grupo-${tabla.grupo}`;
            div.id = `card-${key}`;
            
            const estadoColor = tabla.pendientes === 0 ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800';
            const estadoTexto = tabla.pendientes === 0 ? 'Sincronizado' : `${tabla.pendientes} pendientes`;
            
            div.innerHTML = `
                <div class="flex justify-between items-start mb-3">
                    <div>
                        <h3 class="font-semibold text-gray-800">${tabla.nombre}</h3>
                        <p class="text-xs text-gray-500">→ ${tabla.tabla_destino}</p>
                    </div>
                    <span class="px-2 py-1 rounded-full text-xs ${estadoColor}">${estadoTexto}</span>
                </div>
                <div class="mb-2">
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="progress-bar ${tabla.porcentaje === 100 ? 'bg-green-500' : 'bg-blue-500'} rounded-full h-2" 
                             style="width: ${tabla.porcentaje}%"></div>
                    </div>
                </div>
                <div class="flex justify-between text-sm text-gray-600 mb-3">
                    <span>${formatNumber(tabla.sincronizados)} / ${formatNumber(tabla.total)}</span>
                    <span>${tabla.porcentaje}%</span>
                </div>
                <div class="flex gap-2">
                    <button onclick="sincronizarTabla('${key}')" 
                            class="flex-1 bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm transition-colors"
                            ${tabla.pendientes === 0 ? 'disabled class="opacity-50 cursor-not-allowed"' : ''}>
                        Sincronizar
                    </button>
                    <button onclick="resetearTabla('${key}')" 
                            class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-1 rounded text-sm transition-colors"
                            title="Resetear estado">
                        ↺
                    </button>
                </div>
                ${tabla.ultimo_sync ? `<p class="text-xs text-gray-400 mt-2">Último: ${formatDate(tabla.ultimo_sync)}</p>` : ''}
            `;
            
            return div;
        }

        // Sincronizar pendientes (siempre con filtro de fecha 2025-12-01)
        document.getElementById('btn-sync-pendientes').addEventListener('click', async () => {
            await ejecutarSincronizacion();
        });

        // Función de sincronización (siempre aplica filtro de fecha)
        async function ejecutarSincronizacion() {
            if (syncEnProgreso) return;
            
            syncEnProgreso = true;
            mostrarSyncActiva(true);
            
            log('Iniciando sincronización de pendientes (desde 2025-12-01)...', 'info');
            
            try {
                const response = await fetch(`${API_BASE}/sync/all`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        tablas: ['all'],
                        solo_nuevos: true,
                        primera_sync: true // Siempre aplica filtro de fecha de corte
                    })
                });
                
                const data = await response.json();
                
                if (data.estado) {
                    log(`Sincronización completada en ${data.resultado.tiempo_total}`, 'success');
                    log(`Total procesados: ${formatNumber(data.resultado.registros_totales)} registros`, 'success');
                    
                    // Mostrar detalle por tabla
                    Object.entries(data.resultado.tablas).forEach(([tabla, resultado]) => {
                        if (resultado.estado === 'completado') {
                            log(`✓ ${tabla}: ${resultado.registros} registros (${resultado.tiempo})`, 'success');
                        } else {
                            log(`✗ ${tabla}: ${resultado.mensaje}`, 'error');
                        }
                    });
                } else {
                    log(`Error: ${data.mensaje}`, 'error');
                }
            } catch (error) {
                log(`Error: ${error.message}`, 'error');
            } finally {
                syncEnProgreso = false;
                mostrarSyncActiva(false);
                cargarEstado();
            }
        }

        // Sincronizar tabla individual
        async function sincronizarTabla(tabla) {
            if (syncEnProgreso) return;
            
            syncEnProgreso = true;
            mostrarSyncActiva(true, tabla);
            log(`Sincronizando ${tabla}...`, 'info');
            
            try {
                const soloNuevos = document.getElementById('solo-nuevos').checked;
                
                const response = await fetch(`${API_BASE}/sync/tabla`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        tabla: tabla,
                        solo_nuevos: soloNuevos
                    })
                });
                
                const data = await response.json();
                
                if (data.estado) {
                    log(`✓ ${tabla}: ${data.registros_procesados} registros sincronizados (${data.tiempo})`, 'success');
                } else {
                    log(`✗ ${tabla}: ${data.mensaje}`, 'error');
                }
            } catch (error) {
                log(`Error: ${error.message}`, 'error');
            } finally {
                syncEnProgreso = false;
                mostrarSyncActiva(false);
                cargarEstado();
            }
        }

        // Resetear tabla
        async function resetearTabla(tabla) {
            if (!confirm(`¿Resetear estado de sincronización de ${tabla}? Esto marcará todos los registros como no sincronizados.`)) {
                return;
            }
            
            try {
                const response = await fetch(`${API_BASE}/sync/reset`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ tabla: tabla })
                });
                
                const data = await response.json();
                
                if (data.estado) {
                    log(`${tabla} reseteado`, 'info');
                    cargarEstado();
                } else {
                    log(`Error: ${data.mensaje}`, 'error');
                }
            } catch (error) {
                log(`Error: ${error.message}`, 'error');
            }
        }

        // Actualizar botón
        document.getElementById('btn-actualizar').addEventListener('click', () => {
            log('Actualizando estado...', 'info');
            cargarEstado();
        });

        // Mostrar/ocultar panel de sync activa
        function mostrarSyncActiva(mostrar, tablaActual = '') {
            const panel = document.getElementById('sync-activa');
            panel.classList.toggle('hidden', !mostrar);
            
            if (mostrar) {
                document.getElementById('sync-tabla-actual').textContent = tablaActual || 'Todas las tablas';
                document.getElementById('btn-sync-pendientes').disabled = true;
                document.getElementById('btn-sync-pendientes').classList.add('opacity-50', 'cursor-not-allowed');
            } else {
                document.getElementById('btn-sync-pendientes').disabled = false;
                document.getElementById('btn-sync-pendientes').classList.remove('opacity-50', 'cursor-not-allowed');
            }
        }

        // Actualizar indicador de conexión
        function actualizarConexion(conectado) {
            const el = document.getElementById('estado-conexion');
            if (conectado) {
                el.className = 'inline-flex items-center px-3 py-1 rounded-full text-sm bg-green-100 text-green-800';
                el.innerHTML = '<span class="w-2 h-2 rounded-full mr-2 bg-green-500"></span>Conectado';
            } else {
                el.className = 'inline-flex items-center px-3 py-1 rounded-full text-sm bg-red-100 text-red-800';
                el.innerHTML = '<span class="w-2 h-2 rounded-full mr-2 bg-red-500"></span>Sin conexión';
            }
        }

        // Log de actividad
        function log(mensaje, tipo = 'info') {
            const container = document.getElementById('log-container');
            const timestamp = new Date().toLocaleTimeString();
            
            let color = 'text-green-400';
            if (tipo === 'error') color = 'text-red-400';
            if (tipo === 'info') color = 'text-blue-400';
            if (tipo === 'success') color = 'text-green-300';
            
            // Remover mensaje de espera inicial
            if (container.querySelector('.text-gray-500')) {
                container.innerHTML = '';
            }
            
            const p = document.createElement('p');
            p.className = color;
            p.innerHTML = `<span class="text-gray-500">[${timestamp}]</span> ${mensaje}`;
            container.appendChild(p);
            container.scrollTop = container.scrollHeight;
        }

        // Utilidades
        function formatNumber(num) {
            return new Intl.NumberFormat('es-VE').format(num);
        }

        function formatDate(date) {
            if (!date) return '-';
            return new Date(date).toLocaleString('es-VE', {
                day: '2-digit',
                month: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
    </script>
</body>
</html>
