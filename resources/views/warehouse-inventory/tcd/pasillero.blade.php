@extends('layouts.app')
@section('nav')
    @include('warehouse-inventory.partials.nav')
@endsection

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="mb-4">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center">
            <i class="fas fa-barcode text-green-500 mr-2"></i>
            TCD - Pasillero
        </h1>
        <p class="text-gray-600 mt-1">Escanea ubicación, producto y asigna cantidad</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <!-- Panel de Escaneo -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-bold text-gray-700 mb-4">Procesar Asignación</h2>
                
                <!-- Selección de Orden -->
                <div id="pasoSeleccionarOrden" class="space-y-4">
                    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
                        <h3 class="font-bold text-blue-700 mb-2">Selecciona una Orden</h3>
                        <p class="text-sm text-gray-600 mb-3">Elige una orden de la lista para procesar sus productos</p>
                        <div id="ordenSeleccionadaInfo" class="mt-3 p-3 bg-white rounded border border-blue-200" style="display: none;">
                            <div class="flex justify-between items-center">
                                <div>
                                    <span class="font-bold text-gray-800" id="ordenSeleccionadaNumero"></span>
                                    <p class="text-xs text-gray-500 mt-1" id="ordenSeleccionadaFecha"></p>
                                </div>
                                <button onclick="deseleccionarOrden()" class="text-xs px-2 py-1 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded">
                                    <i class="fas fa-times mr-1"></i> Cambiar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Lista de Productos de la Orden (oculto inicialmente) -->
                <div id="listaProductosOrden" class="space-y-4" style="display: none;">
                    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="font-bold text-blue-700">Productos de la Orden</h3>
                            <button onclick="volverASeleccionarOrden()" class="text-xs px-2 py-1 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded">
                                <i class="fas fa-arrow-left mr-1"></i> Cambiar Orden
                            </button>
                        </div>
                        <p class="text-sm text-gray-600 mb-3">Selecciona un producto de la lista o escanea directamente</p>
                    </div>
                    
                    <!-- Lista de productos -->
                    <div id="contenidoProductosOrden" class="space-y-3">
                    </div>
                    
                    <!-- Opción de escanear directamente -->
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded mt-4">
                        <h4 class="font-semibold text-green-700 mb-2">O escanea directamente:</h4>
                        <input type="text" 
                               id="inputProductoInicial" 
                               class="w-full px-4 py-4 text-xl font-mono border-2 border-green-400 rounded-lg focus:ring-2 focus:ring-green-500 text-gray-900"
                               placeholder="Escanea código de barras o proveedor"
                               autofocus>
                        <div id="mensajeProductoInicial" class="mt-2 text-sm"></div>
                    </div>
                </div>

                <!-- Paso 1: Verificar Producto (oculto inicialmente) -->
                <div id="pasoProducto" class="space-y-4 mt-6" style="display: none;">
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded">
                        <div class="flex items-center justify-between mb-2">
                        <h3 class="font-bold text-green-700">Paso 1: Escanear Código del Producto</h3>
                        <button onclick="volverAInicio()" class="text-xs px-2 py-1 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded">
                            <i class="fas fa-arrow-left mr-1"></i> Volver
                        </button>
                        </div>
                        <p class="text-sm text-gray-600 mb-3">Escanea el código de barras o código de proveedor del producto seleccionado</p>
                        <input type="text" 
                               id="inputProducto" 
                               class="w-full px-4 py-3 text-lg font-mono border-2 border-green-400 rounded-lg focus:ring-2 focus:ring-green-500 text-gray-900"
                               placeholder="Escanea código de barras o proveedor"
                               autofocus>
                        <div id="mensajeProducto" class="mt-2 text-sm"></div>
                    </div>
                </div>

                <!-- Paso 3: Escanear Ubicación -->
                <div id="pasoUbicacion" class="space-y-4 mt-6" style="display: none;">
                    <!-- Información del Producto -->
                    <div id="infoProductoUbicacion" class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
                        <!-- Se llenará dinámicamente con JavaScript -->
                    </div>
                    
                    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
                        <h3 class="font-bold text-blue-700 mb-2">Paso 2: Escanear Ubicación</h3>
                        <input type="text" 
                               id="inputUbicacion" 
                               class="w-full px-4 py-3 text-lg font-mono border-2 border-blue-400 rounded-lg focus:ring-2 focus:ring-blue-500"
                               placeholder="Escanea o ingresa código de ubicación"
                               autofocus>
                        <div id="mensajeUbicacion" class="mt-2 text-sm"></div>
                    </div>
                </div>

                <!-- Paso 4: Ingresar Cantidad -->
                <div id="pasoCantidad" class="space-y-4 mt-6" style="display: none;">
                    <div class="bg-orange-50 border-l-4 border-orange-500 p-4 rounded">
                        <h3 class="font-bold text-orange-700 mb-2">Paso 3: Ingresar Cantidad</h3>
                        <div class="mb-2">
                            <span class="text-sm text-gray-600">Cantidad pendiente: </span>
                            <span id="cantidadPendiente" class="font-bold text-lg text-orange-600"></span>
                        </div>
                        <input type="number" 
                               id="inputCantidad" 
                               step="0.0001"
                               min="0.0001"
                               class="w-full px-4 py-3 text-lg font-mono border-2 border-orange-400 rounded-lg focus:ring-2 focus:ring-orange-500"
                               placeholder="Ingrese cantidad"
                               autofocus>
                        <div class="mt-2 flex gap-2">
                            <button onclick="usarCantidadTotal()" class="flex-1 px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg transition">
                                Usar Cantidad Total
                            </button>
                            <button onclick="usarCantidadParcial()" class="flex-1 px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition">
                                Cantidad Parcial
                            </button>
                        </div>
                        <div id="mensajeCantidad" class="mt-2 text-sm"></div>
                    </div>
                </div>

                <!-- Botón Finalizar -->
                <div id="pasoFinalizar" class="mt-6" style="display: none;">
                    <button onclick="procesarAsignacion()" 
                            class="w-full px-6 py-4 bg-green-500 hover:bg-green-600 text-white font-bold text-lg rounded-lg shadow-lg transition">
                        <i class="fas fa-check-circle mr-2"></i>
                        Confirmar y Enviar
                    </button>
                </div>
            </div>
        </div>

        <!-- Panel de Información -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow p-4 mb-4">
                <h3 class="font-bold text-gray-700 mb-4">Información de Asignación</h3>
                <div id="infoAsignacion" class="space-y-3 text-sm">
                    <div class="text-center text-gray-400 py-8">
                        <i class="fas fa-info-circle text-4xl mb-2"></i>
                        <p id="mensajeInfoInicial">Selecciona una orden para comenzar</p>
                    </div>
                </div>
            </div>

            <!-- Lista de Órdenes -->
            <div id="panelOrdenes" class="bg-white rounded-lg shadow p-4">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-gray-700">Mis Órdenes</h3>
                </div>
                <div id="listaOrdenes" class="space-y-3 max-h-[500px] overflow-y-auto">
                    <div class="text-center text-gray-400 py-4">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p class="mt-2">Cargando...</p>
                    </div>
                </div>
            </div>
            
            <!-- Botón para volver a ver órdenes (oculto inicialmente) -->
            <div id="botonVolverOrdenes" class="bg-white rounded-lg shadow p-4" style="display: none;">
                <button onclick="volverASeleccionarOrden()" class="w-full px-4 py-3 bg-blue-500 hover:bg-blue-600 text-white font-semibold rounded-lg transition flex items-center justify-center">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Ver Todas las Órdenes
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Contenedor de notificaciones -->
<div id="notificacionesContainer" class="fixed bottom-4 right-4 z-50 space-y-2 max-w-sm"></div>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
let asignaciones = [];
let ordenesAgrupadas = [];
let ordenSeleccionada = null;
let asignacionActual = null;
let ubicacionActual = null;
let productoSeleccionadoId = null; // ID de la asignación seleccionada de la lista

// Función para mostrar notificaciones
function mostrarNotificacion(mensaje, tipo = 'info') {
    const container = document.getElementById('notificacionesContainer');
    const id = 'notif-' + Date.now();
    
    const colores = {
        'success': 'bg-green-500',
        'error': 'bg-red-500',
        'warning': 'bg-yellow-500',
        'info': 'bg-blue-500'
    };
    
    const notificacion = document.createElement('div');
    notificacion.id = id;
    notificacion.className = `${colores[tipo] || colores.info} text-white px-4 py-3 rounded-lg shadow-lg flex items-center justify-between`;
    notificacion.innerHTML = `
        <span>${mensaje}</span>
        <button onclick="cerrarNotificacion('${id}')" class="ml-4 text-white hover:text-gray-200">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    container.appendChild(notificacion);
    
    setTimeout(() => {
        cerrarNotificacion(id);
    }, 5000);
}

function cerrarNotificacion(id) {
    const notif = document.getElementById(id);
    if (notif) {
        notif.style.transition = 'opacity 0.3s';
        notif.style.opacity = '0';
        setTimeout(() => notif.remove(), 300);
    }
}

// Cargar asignaciones al iniciar
document.addEventListener('DOMContentLoaded', function() {
    cargarAsignaciones();
    
    // Event listener para input inicial de producto
    document.getElementById('inputProductoInicial').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            buscarAsignacionPorProducto();
        }
    });
    
    // Event listeners para inputs
    document.getElementById('inputProducto').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            verificarProducto();
        }
    });
    
    document.getElementById('inputUbicacion').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            verificarUbicacion();
        }
    });
    
    document.getElementById('inputCantidad').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            procesarAsignacion();
        }
    });
});

function cargarAsignaciones() {
    fetch('/warehouse-inventory/tcd/mis-asignaciones')
        .then(res => res.json())
        .then(data => {
            if (data.estado) {
                asignaciones = data.asignaciones || [];
                ordenesAgrupadas = data.ordenes || [];
                
                // Si hay una orden seleccionada, actualizarla
                if (ordenSeleccionada) {
                    const ordenActualizada = ordenesAgrupadas.find(o => o.orden_id === ordenSeleccionada.orden_id);
                    if (ordenActualizada) {
                        ordenSeleccionada = ordenActualizada;
                        // Actualizar lista de productos si está visible
                        if (document.getElementById('listaProductosOrden').style.display !== 'none') {
                            mostrarListaProductosOrden();
                        }
                    } else {
                        // Si la orden ya no existe, deseleccionar
                        deseleccionarOrden();
                    }
                }
                
                mostrarOrdenes();
            } else {
                mostrarNotificacion('Error al cargar asignaciones: ' + data.msj, 'error');
            }
        })
        .catch(err => {
            console.error(err);
            mostrarNotificacion('Error al cargar asignaciones', 'error');
        });
}

function mostrarOrdenes() {
    const contenedor = document.getElementById('listaOrdenes');
    
    if (ordenesAgrupadas.length === 0) {
        contenedor.innerHTML = '<div class="text-center text-gray-400 py-4">No hay órdenes asignadas</div>';
        return;
    }
    
    contenedor.innerHTML = ordenesAgrupadas.map(orden => {
        const estadoColors = {
            'borrador': 'bg-gray-100 text-gray-800',
            'asignada': 'bg-blue-100 text-blue-800',
            'en_proceso': 'bg-yellow-100 text-yellow-800',
            'completada': 'bg-green-100 text-green-800',
            'despachada': 'bg-purple-100 text-purple-800'
        };
        
        const fechaCreacion = new Date(orden.fecha_creacion).toLocaleDateString('es-VE', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
        
        const totalProductos = orden.asignaciones.length;
        const productosCompletados = orden.asignaciones.filter(a => a.estado === 'completada').length;
        const esSeleccionada = ordenSeleccionada && ordenSeleccionada.orden_id === orden.orden_id;
        
        return `
            <div class="border ${esSeleccionada ? 'border-blue-500 border-2' : 'border-gray-200'} rounded-lg p-3 mb-3 ${esSeleccionada ? 'bg-blue-50' : 'hover:bg-gray-50'} cursor-pointer" onclick="seleccionarOrden(${orden.orden_id})">
                <div class="flex justify-between items-start mb-2">
                    <div class="flex-1">
                        <h4 class="font-bold text-gray-800 text-sm">${orden.numero_orden}</h4>
                        <p class="text-xs text-gray-500 mt-1">${fechaCreacion}</p>
                        <p class="text-xs text-gray-600 mt-1">${productosCompletados} / ${totalProductos} productos completados</p>
                    </div>
                    <span class="px-2 py-1 rounded text-xs font-semibold ${estadoColors[orden.estado] || 'bg-gray-100'}">
                        ${orden.estado}
                    </span>
                </div>
            </div>
        `;
    }).join('');
}

function seleccionarOrden(ordenId) {
    ordenSeleccionada = ordenesAgrupadas.find(o => o.orden_id === ordenId);
    if (!ordenSeleccionada) return;
    
    // Limpiar asignación actual
    asignacionActual = null;
    ubicacionActual = null;
    
    // Mostrar información de la orden seleccionada
    const fechaCreacion = new Date(ordenSeleccionada.fecha_creacion).toLocaleDateString('es-VE', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
    
    document.getElementById('ordenSeleccionadaNumero').textContent = ordenSeleccionada.numero_orden;
    document.getElementById('ordenSeleccionadaFecha').textContent = fechaCreacion;
    document.getElementById('ordenSeleccionadaInfo').style.display = 'block';
    
    // Ocultar selección de orden y mostrar lista de productos
    document.getElementById('pasoSeleccionarOrden').style.display = 'none';
    document.getElementById('listaProductosOrden').style.display = 'block';
    
    // Ocultar otros pasos
    document.getElementById('pasoProducto').style.display = 'none';
    document.getElementById('pasoUbicacion').style.display = 'none';
    document.getElementById('pasoCantidad').style.display = 'none';
    document.getElementById('pasoFinalizar').style.display = 'none';
    
    // Ocultar panel de órdenes y mostrar botón para volver
    document.getElementById('panelOrdenes').style.display = 'none';
    document.getElementById('botonVolverOrdenes').style.display = 'block';
    
    // Limpiar inputs
    document.getElementById('inputProductoInicial').value = '';
    document.getElementById('inputProducto').value = '';
    document.getElementById('inputUbicacion').value = '';
    document.getElementById('inputCantidad').value = '';
    
    // Mostrar lista de productos de la orden
    mostrarListaProductosOrden();
    
    // Actualizar lista de órdenes para resaltar la seleccionada
    mostrarOrdenes();
    
    // Actualizar información
    mostrarInfoAsignacion();
}

function deseleccionarOrden() {
    ordenSeleccionada = null;
    asignacionActual = null;
    ubicacionActual = null;
    productoSeleccionadoId = null; // Limpiar selección de producto
    
    document.getElementById('ordenSeleccionadaInfo').style.display = 'none';
    document.getElementById('pasoSeleccionarOrden').style.display = 'block';
    document.getElementById('listaProductosOrden').style.display = 'none';
    document.getElementById('pasoProducto').style.display = 'none';
    document.getElementById('pasoUbicacion').style.display = 'none';
    document.getElementById('pasoCantidad').style.display = 'none';
    document.getElementById('pasoFinalizar').style.display = 'none';
    
    // Mostrar panel de órdenes y ocultar botón
    document.getElementById('panelOrdenes').style.display = 'block';
    document.getElementById('botonVolverOrdenes').style.display = 'none';
    
    // Limpiar inputs
    document.getElementById('inputProductoInicial').value = '';
    document.getElementById('inputProducto').value = '';
    document.getElementById('inputUbicacion').value = '';
    document.getElementById('inputCantidad').value = '';
    
    mostrarOrdenes();
    mostrarInfoAsignacion();
}

function mostrarListaProductosOrden() {
    if (!ordenSeleccionada) return;
    
    const contenedor = document.getElementById('contenidoProductosOrden');
    const productos = ordenSeleccionada.asignaciones.filter(a => a.estado !== 'completada');
    
    if (productos.length === 0) {
        contenedor.innerHTML = '<div class="text-center text-gray-400 py-4">Todos los productos de esta orden están completados</div>';
        return;
    }
    
    contenedor.innerHTML = productos.map(asig => {
        const porcentaje = (asig.cantidad_procesada / asig.cantidad) * 100;
        const cantidadPendiente = asig.cantidad - asig.cantidad_procesada;
        const stockDisponible = asig.stock_disponible || 0;
        const tieneUbicacionSuficiente = asig.tiene_ubicacion_suficiente !== false;
        const ubicacionesSuficientes = asig.ubicaciones_suficientes || [];
        const ubicacionesInsuficientes = asig.ubicaciones_insuficientes || [];
        const todasUbicaciones = asig.ubicaciones_disponibles || [];
        // Solo mostrar ubicación asignada si realmente hay una warehouse_id asignada (no null)
        const ubicacionAsignada = (asig.warehouse_id && asig.warehouse) ? asig.warehouse.codigo : null;
        const estadoColors = {
            'pendiente': 'bg-gray-100 text-gray-800',
            'en_proceso': 'bg-yellow-100 text-yellow-800',
            'completada': 'bg-green-100 text-green-800'
        };
        const esSeleccionado = productoSeleccionadoId === asig.id;
        const estaBloqueado = !tieneUbicacionSuficiente;
        const clasesBorde = esSeleccionado ? 'border-2 border-blue-500 bg-blue-50' : (estaBloqueado ? 'border-2 border-red-300 bg-red-50' : 'border border-gray-200');
        const cursorClass = estaBloqueado ? 'cursor-not-allowed opacity-75' : 'cursor-pointer hover:shadow-md';
        
        // Generar lista de ubicaciones disponibles
        let ubicacionesHTML = '';
        if (todasUbicaciones.length > 0) {
            ubicacionesHTML = `
                <div class="mt-2 pt-2 border-t border-gray-200">
                    ${ubicacionesSuficientes.length > 0 ? `
                        <div class="text-xs font-semibold text-green-700 mb-1">Ubicaciones con stock suficiente:</div>
                        <div class="flex flex-wrap gap-1 mb-2">
                            ${ubicacionesSuficientes.map(ubic => `
                                <span class="px-2 py-0.5 bg-green-100 text-green-800 rounded text-xs font-mono">
                                    ${ubic.codigo} (${ubic.stock_disponible.toFixed(2)})
                                </span>
                            `).join('')}
                        </div>
                    ` : ''}
                    ${ubicacionesInsuficientes.length > 0 ? `
                        <div class="text-xs font-semibold text-orange-700 mb-1">Ubicaciones con stock insuficiente:</div>
                        <div class="flex flex-wrap gap-1">
                            ${ubicacionesInsuficientes.map(ubic => `
                                <span class="px-2 py-0.5 bg-orange-100 text-orange-800 rounded text-xs font-mono" title="Stock: ${ubic.stock_disponible.toFixed(2)}, Necesita: ${cantidadPendiente.toFixed(2)}">
                                    ${ubic.codigo} (${ubic.stock_disponible.toFixed(2)})
                                </span>
                            `).join('')}
                        </div>
                    ` : ''}
                </div>
            `;
        } else {
            ubicacionesHTML = `
                <div class="mt-2 pt-2 border-t border-red-200">
                    <div class="text-xs text-red-600">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        No hay ubicaciones con stock disponible para este producto
                    </div>
                </div>
            `;
        }
        
        return `
            <div class="bg-white ${clasesBorde} rounded-lg p-4 transition-shadow ${cursorClass}" ${estaBloqueado ? '' : `onclick="seleccionarProductoDeLista(${asig.id})"`}>
                ${estaBloqueado ? `
                    <div class="mb-2 p-2 bg-red-100 border border-red-300 rounded text-xs text-red-800">
                        <i class="fas fa-lock mr-1"></i>
                        <strong>BLOQUEADO:</strong> No hay ubicaciones con stock suficiente para cubrir la cantidad pendiente
                    </div>
                ` : ''}
                <div class="flex justify-between items-start mb-2">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            ${esSeleccionado ? '<i class="fas fa-check-circle text-blue-600"></i>' : ''}
                            <h5 class="font-semibold text-gray-800 text-base">${asig.orden_item.descripcion}</h5>
                        </div>
                        <div class="text-xs text-gray-600 space-y-1 mt-2">
                            <div><strong>Código Barras:</strong> <span class="font-mono">${asig.orden_item.codigo_barras || 'N/A'}</span></div>
                            <div><strong>Código Proveedor:</strong> <span class="font-mono">${asig.orden_item.codigo_proveedor || 'N/A'}</span></div>
                            ${ubicacionAsignada ? `
                                <div><strong>Ubicación Asignada:</strong> <span class="font-semibold text-blue-600">${ubicacionAsignada}</span></div>
                            ` : ''}
                        </div>
                    </div>
                    <span class="px-2 py-1 rounded text-xs font-semibold ${estadoColors[asig.estado] || 'bg-gray-100'}">
                        ${asig.estado}
                    </span>
                </div>
                <div class="mt-3 space-y-2">
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600">Total:</span>
                        <span class="font-semibold">${asig.cantidad.toFixed(4)}</span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600">Procesado:</span>
                        <span class="text-blue-600 font-semibold">${asig.cantidad_procesada.toFixed(4)}</span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600">Pendiente:</span>
                        <span class="text-orange-600 font-bold">${cantidadPendiente.toFixed(4)}</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                        <div class="bg-blue-500 h-2 rounded-full transition-all" style="width: ${porcentaje}%"></div>
                    </div>
                    <div class="text-xs ${tieneUbicacionSuficiente ? 'text-green-600' : 'text-red-600'} mt-1">
                        <i class="fas ${tieneUbicacionSuficiente ? 'fa-check-circle' : 'fa-exclamation-triangle'} mr-1"></i>
                        Stock Total Disponible: ${stockDisponible.toFixed(4)}
                    </div>
                    ${ubicacionesHTML}
                </div>
            </div>
        `;
    }).join('');
}

function seleccionarProductoDeLista(asignacionId) {
    // Buscar la asignación en el array de asignaciones
    const asignacionEncontrada = asignaciones.find(a => a.id === asignacionId);
    if (!asignacionEncontrada) return;
    
    // Verificar si el producto está bloqueado (no tiene ubicaciones suficientes)
    if (!asignacionEncontrada.tiene_ubicacion_suficiente) {
        mostrarNotificacion('Este producto está bloqueado. No hay ubicaciones con stock suficiente para cubrir la cantidad pendiente.', 'error');
        return;
    }
    
    // Marcar este producto como seleccionado
    productoSeleccionadoId = asignacionId;
    
    // Actualizar la lista para mostrar el producto seleccionado visualmente
    mostrarListaProductosOrden();
    
    // Mostrar información de la asignación seleccionada (pero no la asignamos aún a asignacionActual)
    const contenedor = document.getElementById('infoAsignacion');
    if (contenedor) {
        const ubicacionesSuficientes = asignacionEncontrada.ubicaciones_suficientes || [];
        const ubicacionesInsuficientes = asignacionEncontrada.ubicaciones_insuficientes || [];
        const todasUbicaciones = asignacionEncontrada.ubicaciones_disponibles || [];
        const cantidadPendiente = asignacionEncontrada.cantidad - asignacionEncontrada.cantidad_procesada;
        
        let ubicacionesHTML = '';
        if (todasUbicaciones.length > 0) {
            ubicacionesHTML = `
                <div class="mt-2 pt-2 border-t border-gray-200">
                    ${ubicacionesSuficientes.length > 0 ? `
                        <div class="text-xs font-semibold text-green-700 mb-1">Ubicaciones con stock suficiente:</div>
                        <div class="flex flex-wrap gap-1 mb-2">
                            ${ubicacionesSuficientes.map(ubic => `
                                <span class="px-2 py-0.5 bg-green-100 text-green-800 rounded text-xs font-mono">
                                    ${ubic.codigo} (${ubic.stock_disponible.toFixed(2)})
                                </span>
                            `).join('')}
                        </div>
                    ` : ''}
                    ${ubicacionesInsuficientes.length > 0 ? `
                        <div class="text-xs font-semibold text-orange-700 mb-1">Ubicaciones con stock insuficiente:</div>
                        <div class="flex flex-wrap gap-1">
                            ${ubicacionesInsuficientes.map(ubic => `
                                <span class="px-2 py-0.5 bg-orange-100 text-orange-800 rounded text-xs font-mono" title="Stock: ${ubic.stock_disponible.toFixed(2)}, Necesita: ${cantidadPendiente.toFixed(2)}">
                                    ${ubic.codigo} (${ubic.stock_disponible.toFixed(2)})
                                </span>
                            `).join('')}
                        </div>
                    ` : ''}
                </div>
            `;
        } else {
            ubicacionesHTML = `
                <div class="mt-2 pt-2 border-t border-red-200">
                    <div class="text-xs text-red-600">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        No hay ubicaciones con stock disponible para este producto
                    </div>
                </div>
            `;
        }
        
        contenedor.innerHTML = `
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-center gap-2 mb-2">
                    <i class="fas fa-check-circle text-blue-600"></i>
                    <h4 class="font-semibold text-gray-800">Producto Seleccionado</h4>
                </div>
                <div class="text-sm text-gray-700 space-y-1">
                    <p><strong>Producto:</strong> ${asignacionEncontrada.orden_item.descripcion}</p>
                    <p><strong>Código Barras:</strong> <span class="font-mono">${asignacionEncontrada.orden_item.codigo_barras || 'N/A'}</span></p>
                    <p><strong>Código Proveedor:</strong> <span class="font-mono">${asignacionEncontrada.orden_item.codigo_proveedor || 'N/A'}</span></p>
                    <p><strong>Cantidad Pendiente:</strong> <span class="font-semibold text-orange-600">${(asignacionEncontrada.cantidad - asignacionEncontrada.cantidad_procesada).toFixed(4)}</span></p>
                </div>
                ${ubicacionesHTML}
                <div class="mt-3 p-2 bg-yellow-100 border border-yellow-300 rounded text-xs text-yellow-800">
                    <i class="fas fa-info-circle mr-1"></i>
                    <strong>Importante:</strong> Debes escanear el código del producto para continuar
                </div>
            </div>
        `;
    }
    
    // Ocultar lista de productos y mostrar flujo de escaneo
    document.getElementById('listaProductosOrden').style.display = 'none';
    document.getElementById('pasoProducto').style.display = 'block';
    
    // NO pre-llenar el código - el usuario DEBE escanearlo
    document.getElementById('inputProducto').value = '';
    document.getElementById('inputProducto').focus();
    document.getElementById('mensajeProducto').innerHTML = '<span class="text-blue-600"><i class="fas fa-info-circle mr-1"></i>Escanea el código del producto seleccionado</span>';
    
    // Limpiar asignación actual hasta que se escanee correctamente
    asignacionActual = null;
}

function volverASeleccionarOrden() {
    asignacionActual = null;
    ubicacionActual = null;
    productoSeleccionadoId = null; // Limpiar selección de producto
    
    // Deseleccionar orden
    ordenSeleccionada = null;
    
    document.getElementById('ordenSeleccionadaInfo').style.display = 'none';
    document.getElementById('pasoSeleccionarOrden').style.display = 'block';
    document.getElementById('listaProductosOrden').style.display = 'none';
    document.getElementById('pasoProducto').style.display = 'none';
    document.getElementById('pasoUbicacion').style.display = 'none';
    document.getElementById('pasoCantidad').style.display = 'none';
    document.getElementById('pasoFinalizar').style.display = 'none';
    
    // Mostrar panel de órdenes y ocultar botón
    document.getElementById('panelOrdenes').style.display = 'block';
    document.getElementById('botonVolverOrdenes').style.display = 'none';
    
    // Limpiar inputs
    document.getElementById('inputProductoInicial').value = '';
    document.getElementById('inputProducto').value = '';
    document.getElementById('inputUbicacion').value = '';
    document.getElementById('inputCantidad').value = '';
    
    // Actualizar lista de órdenes
    mostrarOrdenes();
    mostrarInfoAsignacion();
}



function buscarAsignacionPorProducto() {
    const codigo = document.getElementById('inputProductoInicial').value.trim().toUpperCase();
    const mensaje = document.getElementById('mensajeProductoInicial');
    
    if (!codigo) {
        mensaje.innerHTML = '<span class="text-red-600">Ingrese un código</span>';
        return;
    }
    
    // Si hay una orden seleccionada, buscar solo en esa orden
    let asignacionesABuscar = asignaciones;
    if (ordenSeleccionada) {
        // Filtrar asignaciones de la orden seleccionada
        asignacionesABuscar = asignaciones.filter(a => a.tcd_orden_id === ordenSeleccionada.orden_id);
    }
    
    // Buscar asignaciones que coincidan con el código escaneado
    const asignacionesCoincidentes = asignacionesABuscar.filter(a => {
        if (a.estado === 'completada') return false;
        
        const codigoBarras = (a.orden_item.codigo_barras || '').toUpperCase();
        const codigoProveedor = (a.orden_item.codigo_proveedor || '').toUpperCase();
        
        return codigo === codigoBarras || codigo === codigoProveedor;
    });
    
    if (asignacionesCoincidentes.length === 0) {
        if (ordenSeleccionada) {
            mensaje.innerHTML = '<span class="text-red-600"><i class="fas fa-times-circle mr-1"></i>Este producto no pertenece a la orden seleccionada o ya está completado</span>';
        } else {
            mensaje.innerHTML = '<span class="text-red-600"><i class="fas fa-times-circle mr-1"></i>No se encontró ninguna asignación pendiente para este producto</span>';
        }
        return;
    }
    
    // Validar que haya al menos una asignación con ubicaciones disponibles (no necesariamente suficientes)
    const asignacionesConUbicaciones = asignacionesCoincidentes.filter(a => {
        const ubicaciones = a.ubicaciones_disponibles || [];
        return ubicaciones.length > 0;
    });
    
    // Si no hay ninguna con ubicaciones, mostrar error
    if (asignacionesConUbicaciones.length === 0) {
        mensaje.innerHTML = '<span class="text-red-600"><i class="fas fa-exclamation-triangle mr-1"></i>Este producto no tiene ubicaciones con stock disponible</span>';
        return;
    }
    
    // Permitir todas las asignaciones que tengan ubicaciones disponibles (aunque no sean suficientes)
    // El usuario puede trabajar con ubicaciones parciales
    if (asignacionesConUbicaciones.length === 1) {
        // Si hay solo una, seleccionarla automáticamente
        seleccionarAsignacionPorId(asignacionesConUbicaciones[0].id, codigo);
    } else {
        // Si hay múltiples, mostrar opciones
        mostrarOpcionesAsignaciones(asignacionesConUbicaciones, codigo);
    }
}

function mostrarOpcionesAsignaciones(asignacionesList, codigo) {
    const mensaje = document.getElementById('mensajeProductoInicial');
    const opcionesHTML = asignacionesList.map(asig => {
        const stockDisponible = asig.stock_disponible || 0;
        const cantidadPendiente = asig.cantidad - asig.cantidad_procesada;
        const tieneStock = stockDisponible >= cantidadPendiente;
        const stockClass = tieneStock ? 'text-green-600' : 'text-red-600';
        
        return `
        <div class="border border-gray-200 rounded-lg p-3 mb-2 hover:bg-gray-50 cursor-pointer" onclick="seleccionarAsignacionPorId(${asig.id}, '${codigo}')">
            <div class="font-semibold text-gray-800">${asig.orden_item.descripcion}</div>
            <div class="text-sm text-gray-600 mt-1">
                Orden: ${asig.orden.numero_orden} | 
                Pendiente: ${cantidadPendiente.toFixed(4)}
            </div>
            <div class="text-sm ${stockClass} mt-1">
                <i class="fas ${tieneStock ? 'fa-check-circle' : 'fa-exclamation-triangle'} mr-1"></i>
                Stock Disponible: ${stockDisponible.toFixed(4)}
            </div>
        </div>
    `;
    }).join('');
    
    mensaje.innerHTML = `
        <div class="mt-3">
            <p class="text-blue-600 font-semibold mb-2">Se encontraron ${asignacionesList.length} asignaciones. Seleccione una:</p>
            <div class="max-h-64 overflow-y-auto">
                ${opcionesHTML}
            </div>
        </div>
    `;
}

function seleccionarAsignacionPorId(asignacionId, codigoProducto = null) {
    asignacionActual = asignaciones.find(a => a.id == asignacionId);
    
    if (!asignacionActual) {
        mostrarNotificacion('Asignación no encontrada', 'error');
        return;
    }
    
    // Mostrar información
    mostrarInfoAsignacion();
    
    // Ocultar lista de productos y mostrar paso de producto
    document.getElementById('listaProductosOrden').style.display = 'none';
    document.getElementById('pasoProducto').style.display = 'block';
    
    // Si se pasó un código, usarlo; si no, dejar vacío
    const codigo = codigoProducto || '';
    document.getElementById('inputProducto').value = codigo;
    document.getElementById('inputProducto').focus();
    document.getElementById('mensajeProducto').innerHTML = '';
    
    // Si ya se pasó el código, verificar automáticamente
    if (codigo) {
        setTimeout(() => {
            verificarProducto();
        }, 100);
    }
}

function mostrarInfoAsignacion() {
    const contenedor = document.getElementById('infoAsignacion');
    
    if (!asignacionActual) {
        const mensaje = ordenSeleccionada 
            ? 'Selecciona un producto de la orden para procesar' 
            : 'Selecciona una orden para comenzar';
        contenedor.innerHTML = `
            <div class="text-center text-gray-400 py-8">
                <i class="fas fa-info-circle text-4xl mb-2"></i>
                <p>${mensaje}</p>
            </div>
        `;
        return;
    }
    
    const asig = asignacionActual;
    const stockDisponible = asig.stock_disponible || 0;
    const cantidadPendiente = asig.cantidad - asig.cantidad_procesada;
    const tieneStock = stockDisponible >= cantidadPendiente;
    const stockClass = tieneStock ? 'text-green-600' : 'text-red-600';
    
    contenedor.innerHTML = `
        <div class="space-y-2">
            <div>
                <span class="font-semibold text-gray-700">Producto:</span>
                <p class="text-gray-600">${asig.orden_item.descripcion}</p>
            </div>
            <div>
                <span class="font-semibold text-gray-700">Código Barras:</span>
                <p class="text-gray-600 font-mono">${asig.orden_item.codigo_barras || 'N/A'}</p>
            </div>
            <div>
                <span class="font-semibold text-gray-700">Código Proveedor:</span>
                <p class="text-gray-600 font-mono">${asig.orden_item.codigo_proveedor || 'N/A'}</p>
            </div>
            <div>
                <span class="font-semibold text-gray-700">Cantidad Total:</span>
                <p class="text-gray-600">${asig.cantidad}</p>
            </div>
            <div>
                <span class="font-semibold text-gray-700">Cantidad Procesada:</span>
                <p class="text-gray-600">${asig.cantidad_procesada}</p>
            </div>
            <div>
                <span class="font-semibold text-gray-700">Cantidad Pendiente:</span>
                <p class="text-orange-600 font-bold">${cantidadPendiente.toFixed(4)}</p>
            </div>
            <div>
                <span class="font-semibold text-gray-700">Stock Disponible:</span>
                <p class="${stockClass} font-bold">
                    <i class="fas ${tieneStock ? 'fa-check-circle' : 'fa-exclamation-triangle'} mr-1"></i>
                    ${stockDisponible.toFixed(4)}
                </p>
            </div>
            <div>
                <span class="font-semibold text-gray-700">Estado:</span>
                <p class="text-gray-600">${asig.estado}</p>
            </div>
        </div>
    `;
}

function volverAInicio() {
    asignacionActual = null;
    ubicacionActual = null;
    productoSeleccionadoId = null; // Limpiar selección de producto
    
    document.getElementById('listaProductosOrden').style.display = 'block';
    document.getElementById('pasoProducto').style.display = 'none';
    document.getElementById('pasoUbicacion').style.display = 'none';
    document.getElementById('pasoCantidad').style.display = 'none';
    document.getElementById('pasoFinalizar').style.display = 'none';
    
    document.getElementById('inputProductoInicial').value = '';
    document.getElementById('mensajeProductoInicial').innerHTML = '';
    document.getElementById('inputProducto').value = '';
    document.getElementById('inputUbicacion').value = '';
    document.getElementById('inputCantidad').value = '';
    document.getElementById('mensajeUbicacion').innerHTML = '';
    document.getElementById('infoProductoUbicacion').innerHTML = '';
    
    // Actualizar lista de productos (para quitar el resaltado)
    mostrarListaProductosOrden();
    
    // Actualizar información
    mostrarInfoAsignacion();
}

function verificarProducto() {
    const codigo = document.getElementById('inputProducto').value.trim().toUpperCase();
    const mensaje = document.getElementById('mensajeProducto');
    
    if (!codigo) {
        mensaje.innerHTML = '<span class="text-red-600">Ingrese un código</span>';
        return;
    }
    
    // Si hay un producto seleccionado de la lista, validar contra ese
    if (productoSeleccionadoId) {
        const asignacionSeleccionada = asignaciones.find(a => a.id === productoSeleccionadoId);
        if (asignacionSeleccionada) {
            const codigoBarras = (asignacionSeleccionada.orden_item.codigo_barras || '').toUpperCase();
            const codigoProveedor = (asignacionSeleccionada.orden_item.codigo_proveedor || '').toUpperCase();
            
            if (codigo === codigoBarras || codigo === codigoProveedor) {
                // Código correcto - ahora sí asignamos a asignacionActual
                asignacionActual = asignacionSeleccionada;
                mostrarInfoAsignacion();
                
                mensaje.innerHTML = '<span class="text-green-600"><i class="fas fa-check-circle mr-1"></i>Producto correcto</span>';
                setTimeout(() => {
                    document.getElementById('pasoProducto').style.display = 'none';
                    mostrarInfoProductoUbicacion();
                    document.getElementById('pasoUbicacion').style.display = 'block';
                    document.getElementById('inputUbicacion').value = '';
                    document.getElementById('inputUbicacion').focus();
                }, 500);
            } else {
                mensaje.innerHTML = '<span class="text-red-600"><i class="fas fa-times-circle mr-1"></i>El código no coincide con el producto seleccionado</span>';
            }
            return;
        }
    }
    
    // Si no hay producto seleccionado de la lista, buscar en todas las asignaciones
    // (esto es para el flujo de escaneo directo)
    if (asignacionActual) {
        const codigoBarras = (asignacionActual.orden_item.codigo_barras || '').toUpperCase();
        const codigoProveedor = (asignacionActual.orden_item.codigo_proveedor || '').toUpperCase();
        
        if (codigo === codigoBarras || codigo === codigoProveedor) {
            mensaje.innerHTML = '<span class="text-green-600"><i class="fas fa-check-circle mr-1"></i>Producto correcto</span>';
            setTimeout(() => {
                document.getElementById('pasoProducto').style.display = 'none';
                mostrarInfoProductoUbicacion();
                document.getElementById('pasoUbicacion').style.display = 'block';
                document.getElementById('inputUbicacion').value = '';
                document.getElementById('inputUbicacion').focus();
            }, 500);
        } else {
            mensaje.innerHTML = '<span class="text-red-600"><i class="fas fa-times-circle mr-1"></i>El código no coincide</span>';
        }
    } else {
        // Buscar asignación por código escaneado
        buscarAsignacionPorProducto();
    }
}

function mostrarInfoProductoUbicacion() {
    if (!asignacionActual) return;
    
    const contenedor = document.getElementById('infoProductoUbicacion');
    if (!contenedor) return;
    
    const producto = asignacionActual.orden_item;
    const cantidadPendiente = asignacionActual.cantidad - asignacionActual.cantidad_procesada;
    const ubicacionesSuficientes = asignacionActual.ubicaciones_suficientes || [];
    const ubicacionesInsuficientes = asignacionActual.ubicaciones_insuficientes || [];
    const todasUbicaciones = asignacionActual.ubicaciones_disponibles || [];
    
    // Generar HTML de ubicaciones
    let ubicacionesHTML = '';
    if (todasUbicaciones.length > 0) {
        ubicacionesHTML = `
            <div class="mt-3 pt-3 border-t border-gray-200">
                <div class="text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-map-marker-alt mr-1"></i>Ubicaciones Disponibles:
                </div>
                ${ubicacionesSuficientes.length > 0 ? `
                    <div class="mb-2">
                        <div class="text-xs font-semibold text-green-700 mb-1">
                            <i class="fas fa-check-circle mr-1"></i>Con stock suficiente:
                        </div>
                        <div class="flex flex-wrap gap-1">
                            ${ubicacionesSuficientes.map(ubic => `
                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs font-mono border border-green-300">
                                    ${ubic.codigo} <span class="text-green-600">(${ubic.stock_disponible.toFixed(2)})</span>
                                </span>
                            `).join('')}
                        </div>
                    </div>
                ` : ''}
                ${ubicacionesInsuficientes.length > 0 ? `
                    <div>
                        <div class="text-xs font-semibold text-orange-700 mb-1">
                            <i class="fas fa-exclamation-triangle mr-1"></i>Con stock insuficiente:
                        </div>
                        <div class="flex flex-wrap gap-1">
                            ${ubicacionesInsuficientes.map(ubic => `
                                <span class="px-2 py-1 bg-orange-100 text-orange-800 rounded text-xs font-mono border border-orange-300" title="Stock: ${ubic.stock_disponible.toFixed(2)}, Necesita: ${cantidadPendiente.toFixed(2)}">
                                    ${ubic.codigo} <span class="text-orange-600">(${ubic.stock_disponible.toFixed(2)})</span>
                                </span>
                            `).join('')}
                        </div>
                    </div>
                ` : ''}
            </div>
        `;
    } else {
        ubicacionesHTML = `
            <div class="mt-3 pt-3 border-t border-red-200">
                <div class="text-xs text-red-600">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    No hay ubicaciones con stock disponible para este producto
                </div>
            </div>
        `;
    }
    
    contenedor.innerHTML = `
        <div class="space-y-2">
            <div class="flex items-center gap-2 mb-2">
                <i class="fas fa-box text-blue-600"></i>
                <h4 class="font-semibold text-gray-800">Información del Producto</h4>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
                <div>
                    <span class="text-gray-600 font-medium">Descripción:</span>
                    <span class="ml-2 text-gray-800">${producto.descripcion || 'N/A'}</span>
                </div>
                <div>
                    <span class="text-gray-600 font-medium">Código Barras:</span>
                    <span class="ml-2 font-mono text-gray-800">${producto.codigo_barras || 'N/A'}</span>
                </div>
                <div>
                    <span class="text-gray-600 font-medium">Código Proveedor:</span>
                    <span class="ml-2 font-mono text-gray-800">${producto.codigo_proveedor || 'N/A'}</span>
                </div>
                <div>
                    <span class="text-gray-600 font-medium">Cantidad Pendiente:</span>
                    <span class="ml-2 font-bold text-orange-600">${cantidadPendiente.toFixed(4)}</span>
                </div>
            </div>
            ${ubicacionesHTML}
        </div>
    `;
}

function verificarUbicacion() {
    const codigo = document.getElementById('inputUbicacion').value.trim();
    const mensaje = document.getElementById('mensajeUbicacion');
    
    if (!codigo) {
        mensaje.innerHTML = '<span class="text-red-600">Ingrese un código de ubicación</span>';
        return;
    }
    
    if (!asignacionActual) {
        mensaje.innerHTML = '<span class="text-red-600">Debe seleccionar y escanear un producto primero</span>';
        return;
    }
    
    // Obtener el inventario_id del producto
    const inventarioId = asignacionActual.orden_item.inventario_id;
    const cantidadPendiente = asignacionActual.cantidad - asignacionActual.cantidad_procesada;
    
    fetch(`/warehouse-inventory/tcd/buscar-ubicacion?codigo=${encodeURIComponent(codigo)}&inventario_id=${inventarioId}`)
        .then(res => res.json())
        .then(data => {
            if (data.estado) {
                ubicacionActual = data.ubicacion;
                const stockDisponible = data.stock_disponible || 0;
                
                // Validar stock disponible en este warehouse
                if (stockDisponible < cantidadPendiente) {
                    const diferencia = cantidadPendiente - stockDisponible;
                    mensaje.innerHTML = `
                        <div class="bg-red-50 border border-red-200 rounded p-3">
                            <div class="flex items-start gap-2">
                                <i class="fas fa-exclamation-triangle text-red-600 text-lg mt-0.5"></i>
                                <div class="flex-1">
                                    <div class="text-red-600 font-semibold mb-2">
                                        Stock insuficiente en esta ubicación
                                    </div>
                                    <div class="text-sm text-red-700 space-y-1">
                                        <div class="flex justify-between">
                                            <span>Ubicación escaneada:</span>
                                            <span class="font-mono font-semibold">${data.ubicacion.codigo}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Stock disponible en esta ubicación:</span>
                                            <span class="font-semibold">${stockDisponible.toFixed(4)}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Cantidad pendiente requerida:</span>
                                            <span class="font-semibold text-orange-600">${cantidadPendiente.toFixed(4)}</span>
                                        </div>
                                        <div class="flex justify-between pt-1 border-t border-red-200">
                                            <span>Falta:</span>
                                            <span class="font-bold text-red-700">${diferencia.toFixed(4)}</span>
                                        </div>
                                    </div>
                                    <div class="mt-2 text-xs text-red-600 italic">
                                        Esta ubicación no tiene suficiente stock para cubrir la cantidad pendiente de la asignación.
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    return;
                }
                
                // Stock suficiente - continuar
                mensaje.innerHTML = `
                    <div class="bg-green-50 border border-green-200 rounded p-3">
                        <div class="flex items-start gap-2">
                            <i class="fas fa-check-circle text-green-600 text-lg mt-0.5"></i>
                            <div class="flex-1">
                                <div class="text-green-600 font-semibold mb-2">
                                    Ubicación válida: <span class="font-mono">${data.ubicacion.codigo}</span>
                                </div>
                                <div class="text-sm text-green-700 space-y-1">
                                    <div class="flex justify-between">
                                        <span>Stock disponible:</span>
                                        <span class="font-semibold">${stockDisponible.toFixed(4)}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Cantidad pendiente:</span>
                                        <span class="font-semibold">${cantidadPendiente.toFixed(4)}</span>
                                    </div>
                                    <div class="pt-1 border-t border-green-200 text-xs">
                                        ✓ Esta ubicación tiene stock suficiente
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                // Guardar stock disponible en la ubicación para usarlo después
                ubicacionActual.stock_disponible = stockDisponible;
                
                setTimeout(() => {
                    document.getElementById('pasoUbicacion').style.display = 'none';
                    document.getElementById('pasoCantidad').style.display = 'block';
                    document.getElementById('cantidadPendiente').textContent = cantidadPendiente.toFixed(4);
                    document.getElementById('inputCantidad').value = '';
                    document.getElementById('inputCantidad').focus();
                }, 500);
            } else {
                mensaje.innerHTML = '<span class="text-red-600"><i class="fas fa-times-circle mr-1"></i>' + data.msj + '</span>';
            }
        })
        .catch(err => {
            console.error(err);
            mensaje.innerHTML = '<span class="text-red-600">Error al verificar ubicación</span>';
        });
}

function usarCantidadTotal() {
    const pendiente = asignacionActual.cantidad - asignacionActual.cantidad_procesada;
    document.getElementById('inputCantidad').value = pendiente.toFixed(4);
    document.getElementById('pasoFinalizar').style.display = 'block';
}

function usarCantidadParcial() {
    document.getElementById('inputCantidad').value = '';
    document.getElementById('inputCantidad').focus();
    document.getElementById('pasoFinalizar').style.display = 'block';
}

function procesarAsignacion() {
    if (!asignacionActual || !ubicacionActual) {
        mostrarNotificacion('Complete todos los pasos', 'error');
        return;
    }
    
    const cantidad = parseFloat(document.getElementById('inputCantidad').value);
    const pendiente = asignacionActual.cantidad - asignacionActual.cantidad_procesada;
    
    if (!cantidad || cantidad <= 0) {
        mostrarNotificacion('Ingrese una cantidad válida', 'error');
        return;
    }
    
    if (cantidad > pendiente) {
        mostrarNotificacion(`La cantidad excede lo pendiente (${pendiente.toFixed(4)})`, 'error');
        return;
    }
    
    // Validar stock disponible en el warehouse específico
    const stockDisponibleEnUbicacion = ubicacionActual.stock_disponible || 0;
    if (stockDisponibleEnUbicacion < cantidad) {
        mostrarNotificacion(`Stock insuficiente en la ubicación ${ubicacionActual.codigo}. Disponible: ${stockDisponibleEnUbicacion.toFixed(4)}, Solicitado: ${cantidad.toFixed(4)}`, 'error');
        return;
    }
    
    fetch('/warehouse-inventory/tcd/procesar-asignacion', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            asignacion_id: asignacionActual.id,
            codigo_ubicacion: ubicacionActual.codigo,
            codigo_producto: document.getElementById('inputProducto').value.trim(),
            cantidad: cantidad
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.estado) {
            mostrarNotificacion('Asignación procesada exitosamente', 'success');
            
            // Recargar asignaciones
            cargarAsignaciones();
            
            // Reiniciar formulario pero mantener la orden seleccionada
            asignacionActual = null;
            ubicacionActual = null;
            productoSeleccionadoId = null; // Limpiar selección de producto
            
            // Si hay una orden seleccionada, volver a mostrar la lista de productos
            if (ordenSeleccionada) {
                document.getElementById('pasoSeleccionarOrden').style.display = 'none';
                document.getElementById('listaProductosOrden').style.display = 'block';
                mostrarListaProductosOrden();
            } else {
                document.getElementById('pasoSeleccionarOrden').style.display = 'block';
                document.getElementById('listaProductosOrden').style.display = 'none';
            }
            
            document.getElementById('pasoProducto').style.display = 'none';
            document.getElementById('pasoUbicacion').style.display = 'none';
            document.getElementById('pasoCantidad').style.display = 'none';
            document.getElementById('pasoFinalizar').style.display = 'none';
            
            document.getElementById('inputProductoInicial').value = '';
            document.getElementById('inputProducto').value = '';
            document.getElementById('inputUbicacion').value = '';
            document.getElementById('inputCantidad').value = '';
            
            mostrarInfoAsignacion();
        } else {
            mostrarNotificacion('Error: ' + data.msj, 'error');
        }
    })
    .catch(err => {
        console.error(err);
        mostrarNotificacion('Error al procesar asignación', 'error');
    });
}
</script>
@endsection

