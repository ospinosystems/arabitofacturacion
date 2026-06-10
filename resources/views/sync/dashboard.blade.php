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

        <!-- Consola de Diagnóstico en Vivo -->
        <div class="bg-white rounded-lg shadow-md p-6 mt-6 border-l-4 border-purple-500">
            <div class="flex items-center justify-between mb-2 flex-wrap gap-2">
                <h2 class="text-xl font-semibold text-gray-800">🔬 Diagnóstico en vivo</h2>
                <div class="flex items-center gap-3">
                    <button id="btn-estado-central" class="text-xs bg-purple-100 text-purple-700 hover:bg-purple-200 px-3 py-1 rounded font-semibold">Estado servidor central</button>
                    <button id="btn-limpiar-diag" class="text-xs text-gray-500 hover:text-gray-700 underline">Limpiar</button>
                    <span class="text-xs text-gray-400">Se llena automáticamente al sincronizar</span>
                </div>
            </div>
            <p class="text-sm text-gray-600 mb-3">
                Al sincronizar (botón global o por tabla) verás <strong>en tiempo real</strong>, lote por lote:
                la <strong>petición enviada</strong> (IDs, tamaño), la <strong>respuesta de central</strong>
                (HTTP, tiempo), y si central <strong>recibió e insertó/actualizó</strong> los registros o
                cuántos <strong>fallaron</strong> con su detalle de error.
            </p>
            <div id="diag-live" class="bg-gray-900 rounded-lg p-3 h-96 overflow-y-auto font-mono text-xs space-y-1">
                <p class="text-gray-500">Sin actividad todavía. Pulsa “Sincronizar Pendientes” o “Sincronizar” en una tabla.</p>
            </div>
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

        // Sincronización GLOBAL (todas las pendientes) vía stream en vivo
        async function ejecutarSincronizacion() {
            iniciarSyncStream(['all'], 'Todas las pendientes');
        }

        // Sincronizar tabla individual vía stream en vivo
        function sincronizarTabla(tabla) {
            iniciarSyncStream([tabla], tabla);
        }

        // ===== Núcleo: abre el stream SSE y vuelca el diagnóstico en vivo =====
        let _evtSource = null;
        function iniciarSyncStream(tablas, etiqueta) {
            if (syncEnProgreso) return;
            syncEnProgreso = true;
            mostrarSyncActiva(true, etiqueta);
            diagReset();
            diagLine('text-purple-300', `▶ Iniciando sincronización: ${esc(etiqueta)}`);
            log(`Iniciando sincronización: ${etiqueta}...`, 'info');

            // EventSource solo soporta GET: parámetros por query string
            const params = new URLSearchParams();
            (Array.isArray(tablas) ? tablas : [tablas]).forEach(t => params.append('tablas[]', t));
            params.append('solo_nuevos', '1');
            const es = new EventSource(`${API_BASE}/sync/stream?` + params.toString());
            _evtSource = es;
            let terminado = false;

            const cerrar = () => {
                if (terminado) return;
                terminado = true;
                try { es.close(); } catch (e) {}
                _evtSource = null;
                syncEnProgreso = false;
                mostrarSyncActiva(false);
                cargarEstado();
            };

            es.addEventListener('tabla_inicio', e => diagTabla(JSON.parse(e.data)));
            es.addEventListener('lote_envio', e => diagEnvio(JSON.parse(e.data)));
            es.addEventListener('lote_respuesta', e => diagRespuesta(JSON.parse(e.data)));
            es.addEventListener('progreso', e => actualizarProgresoGlobal(JSON.parse(e.data)));
            es.addEventListener('tabla_fin', e => {
                const d = JSON.parse(e.data);
                diagLine('text-green-300', `✓ ${esc(d.nombre)}: ${formatNumber(d.registros)} registros (${esc(d.tiempo)})`);
                log(`✓ ${d.nombre}: ${d.registros} registros`, 'success');
            });
            es.addEventListener('tabla_error', e => {
                const d = JSON.parse(e.data);
                diagLine('text-red-400', `✗ ${esc(d.nombre)}: ${esc(d.mensaje)}`);
                log(`✗ ${d.nombre}: ${d.mensaje}`, 'error');
            });
            es.addEventListener('completado', e => {
                let d = {}; try { d = JSON.parse(e.data); } catch (_) {}
                diagLine('text-green-300 font-bold', `✅ ${esc(d.mensaje || 'Sincronización completada')}`);
                log('✅ Sincronización completada', 'success');
                cerrar();
            });
            // 'error' cubre tanto el evento nombrado del servidor como fallos de conexión
            es.addEventListener('error', e => {
                if (terminado) return;
                let msg = 'Conexión SSE interrumpida o el servidor no soporta streaming.';
                if (e && e.data) { try { msg = JSON.parse(e.data).mensaje || msg; } catch (_) {} }
                diagLine('text-red-400 font-bold', `❌ ${esc(msg)}`);
                log(`Error de sincronización: ${msg}`, 'error');
                cerrar();
            });
        }

        // ===================== Consola de diagnóstico =====================
        function diagBox() { return document.getElementById('diag-live'); }
        function diagReset() {
            const box = diagBox();
            if (box) box.innerHTML = '';
        }
        function diagAppend(node) {
            const box = diagBox();
            if (!box) return;
            box.appendChild(node);
            box.scrollTop = box.scrollHeight;
        }
        function diagLine(cls, html) {
            const p = document.createElement('p');
            p.className = cls;
            p.innerHTML = `<span class="text-gray-600">[${new Date().toLocaleTimeString()}]</span> ${html}`;
            diagAppend(p);
        }
        function diagTabla(d) {
            const div = document.createElement('div');
            div.className = 'mt-2 mb-1 pt-2 border-t border-gray-700 text-purple-300 font-bold';
            div.innerHTML = `📦 ${esc(d.nombre)} <span class="text-gray-500 font-normal">→ ${esc(d.tabla_destino || '')}</span>`;
            diagAppend(div);
        }
        function diagEnvio(d) {
            const p = d.peticion || {};
            const det = document.createElement('details');
            det.className = 'text-blue-300';
            const idsTxt = (p.ids || []).join(', ') + (p.ids_total > (p.ids || []).length ? ` … (+${p.ids_total - (p.ids || []).length})` : '');
            det.innerHTML = `
                <summary class="cursor-pointer">↑ Lote ${d.lote}: enviando <b>${formatNumber(d.enviados)}</b> reg · ${fmtBytes(p.payload_bytes_comprimido)} comprimido</summary>
                <div class="pl-4 text-gray-400 break-all">
                    <div>POST ${esc(p.url || '')}</div>
                    <div>destino: ${esc(p.tabla_destino || '')} · crudo ${fmtBytes(p.payload_bytes_crudo)}</div>
                    <div>IDs: ${esc(idsTxt)}</div>
                </div>`;
            diagAppend(det);
        }
        function diagRespuesta(d) {
            const vMap = {
                'GUARDADO_OK': ['text-green-300', '✅ GUARDADO'],
                'PARCIAL': ['text-yellow-300', '⚠ PARCIAL'],
                'NO_GUARDADO': ['text-red-400', '❌ NO GUARDADO'],
            };
            const [cls, badge] = vMap[d.veredicto] || ['text-gray-300', d.veredicto || '?'];
            const recibio = d.recibio_central
                ? `central recibió e insertó <b>${formatNumber(d.insertados)}</b>, actualizó <b>${formatNumber(d.actualizados)}</b>, errores <b>${formatNumber(d.errores)}</b>, faltan <b>${formatNumber(d.faltantes)}</b>`
                : `<span class="text-red-400">central NO respondió</span>`;
            const httpTxt = d.http_status !== null && d.http_status !== undefined ? `HTTP ${d.http_status}` : 'sin HTTP';
            const reintento = d.intento > 1 ? ` <span class="text-yellow-400">(intento ${d.intento})</span>` : '';
            // Métrica de rendimiento que reporta central (sólo si respondió)
            const p = d.perf_central;
            const perfTxt = p ? ` <span class="text-gray-500">· central ${p.ms_total}ms (DB ${p.db_ms}ms / ${p.db_queries}q)</span>` : '';

            const wrap = document.createElement('div');
            wrap.className = cls;
            let inner = `↓ Lote ${d.lote}${reintento}: <b>${badge}</b> · ${httpTxt} · ${d.tiempo_ms} ms · ${recibio}${perfTxt}`;

            // Detalle si hubo errores / no se guardó / mensaje de central
            const hayDetalle = (d.errores > 0) || !d.estado_central || (d.detalles_errores && d.detalles_errores.length) || d.mensaje_central || (d.body_crudo && !d.estado_central);
            if (hayDetalle) {
                const det = document.createElement('details');
                det.open = (d.veredicto === 'NO_GUARDADO');
                det.innerHTML = `<summary class="cursor-pointer">${inner}</summary>
                    <div class="pl-4 text-gray-300">
                        ${d.mensaje_central ? `<div class="text-red-300">mensaje backend: ${esc(d.mensaje_central)}</div>` : ''}
                        ${(d.detalles_errores && d.detalles_errores.length) ? `<div class="text-red-300 mb-1">detalles_errores:</div><pre class="bg-black/40 rounded p-2 overflow-auto max-h-48 text-red-300">${esc(JSON.stringify(d.detalles_errores, null, 2))}</pre>` : ''}
                        ${(d.body_crudo && !d.estado_central) ? `<div class="text-gray-400 mt-1">body crudo:</div><pre class="bg-black/40 rounded p-2 overflow-auto max-h-48 text-gray-300">${esc(d.body_crudo)}</pre>` : ''}
                    </div>`;
                wrap.appendChild(det);
            } else {
                wrap.innerHTML = inner;
            }
            diagAppend(wrap);

            if (d.veredicto !== 'GUARDADO_OK') {
                log(`Lote ${d.lote} de ${d.nombre || d.tabla}: ${d.veredicto}` + (d.mensaje_central ? ` — ${d.mensaje_central}` : ''), d.veredicto === 'PARCIAL' ? 'info' : 'error');
            }
        }
        function actualizarProgresoGlobal(d) {
            const pct = d.porcentaje_global ?? 0;
            const bar = document.getElementById('sync-progress');
            if (bar) bar.style.width = pct + '%';
            const proc = document.getElementById('sync-procesados');
            if (proc) proc.textContent = `${formatNumber(d.procesados_global)} / ${formatNumber(d.total_global)}`;
            const t = document.getElementById('sync-tiempo');
            if (t) t.textContent = `Restante ~${d.tiempo_estimado_restante}s`;
        }
        function fmtBytes(n) {
            if (n === null || n === undefined) return '-';
            if (n < 1024) return n + ' B';
            if (n < 1048576) return (n / 1024).toFixed(1) + ' KB';
            return (n / 1048576).toFixed(2) + ' MB';
        }
        function esc(s) {
            return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        }
        document.getElementById('btn-limpiar-diag').addEventListener('click', () => {
            diagReset();
            diagLine('text-gray-500', 'Consola limpiada.');
        });

        // ===== Estado del servidor central (MySQL/carga) — para diagnosticar saturación =====
        document.getElementById('btn-estado-central').addEventListener('click', async () => {
            const btn = document.getElementById('btn-estado-central');
            btn.disabled = true; const txt = btn.textContent; btn.textContent = 'Consultando...';
            diagLine('text-purple-300', '— Consultando estado del servidor central —');
            try {
                const res = await fetch(`${API_BASE}/sync/central-server-stats`);
                const raw = await res.text();
                let j;
                try {
                    j = JSON.parse(raw);
                } catch (_) {
                    diagLine('text-red-400', `❌ La sucursal respondió NO-JSON (HTTP ${res.status}) en /sync/central-server-stats.`);
                    if (res.status === 404) {
                        diagLine('text-yellow-300', 'Ruta no encontrada → falta resetear opcache en ESTA sucursal (abre /opcache-reset.php y recarga).');
                    } else {
                        const p = document.createElement('pre');
                        p.className = 'bg-black/40 text-red-300 rounded p-2 overflow-auto max-h-60 whitespace-pre-wrap';
                        p.textContent = raw.slice(0, 3000);
                        diagAppend(p);
                    }
                    return;
                }
                if (!j.estado) {
                    diagLine('text-red-400', `❌ ${esc(j.mensaje || 'No se pudo obtener el estado')}` + (j.body ? ` · ${esc(j.body.slice(0,200))}` : ''));
                    diagLine('text-yellow-300', 'Si ni siquiera conecta, ESA es la prueba: central está saturado (sin workers o MySQL al tope).');
                } else {
                    const d = j.data || {};
                    const c = d.mysql_conexiones || {};
                    const t = d.transacciones || {};
                    const s = d.sistema || {};
                    const pre = document.createElement('pre');
                    pre.className = 'bg-black/40 text-cyan-200 rounded p-2 overflow-auto max-h-96 whitespace-pre-wrap';
                    pre.textContent = JSON.stringify(d, null, 2);
                    diagLine('text-cyan-300', `🩺 Central — latencia ${d._latencia_sucursal_a_central_ms ?? '?'}ms · `
                        + `MySQL conexiones ${c.threads_connected ?? '?'}/${c.max_connections ?? '?'} (${c.uso_pct ?? '?'}%), corriendo ${c.threads_running ?? '?'} · `
                        + `trx activas ${t.total_activas ?? '?'} (más vieja ${t.mas_vieja_seg ?? '?'}s) · `
                        + `load ${s.load_1m ?? '?'} · locks en espera ${d.locks_en_espera ?? '?'}`);
                    (d.alertas || []).forEach(a => diagLine('text-yellow-300', '⚠ ' + esc(a)));
                    diagAppend(pre);
                }
            } catch (e) {
                diagLine('text-red-400', `❌ Error consultando estado central: ${esc(e.message)}`);
            } finally {
                btn.disabled = false; btn.textContent = txt;
            }
        });

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
