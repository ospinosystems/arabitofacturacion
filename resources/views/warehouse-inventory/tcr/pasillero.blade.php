@extends('layouts.app')
@section('nav')
    @include('warehouse-inventory.partials.nav')
@endsection

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="mb-4">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center">
            <i class="fas fa-barcode text-green-500 mr-2"></i>
            TCR - Pasillero
        </h1>
        <p class="text-gray-600 mt-1">Escanea ubicación, producto y asigna cantidad</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <!-- Panel de Escaneo -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-bold text-gray-700 mb-4">Procesar Asignación</h2>
                
                <!-- Paso 1: Seleccionar Pedido -->
                <div id="pasoSeleccionarPedido" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Seleccione un pedido</label>
                        <select id="selectPedido" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-400" onchange="seleccionarPedido()">
                            <option value="">Seleccione un pedido...</option>
                        </select>
                    </div>
                    <button onclick="cargarAsignaciones()" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition">
                        <i class="fas fa-sync-alt mr-2"></i>
                        Actualizar Lista
                    </button>
                </div>

                <!-- Paso 2: Escanear Producto -->
                <div id="pasoProducto" class="space-y-4 mt-6" style="display: none;">
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="font-bold text-green-700">Pedido #<span id="pedidoSeleccionadoId"></span> - Escanear Producto</h3>
                            <button onclick="volverASeleccionarPedido()" class="text-xs px-2 py-1 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded">
                                <i class="fas fa-arrow-left mr-1"></i> Cambiar Pedido
                            </button>
                        </div>
                        <p class="text-sm text-gray-600 mb-3">Escanea el código de barras o código de proveedor del producto</p>
                        <input type="text" 
                               id="inputProducto" 
                               class="w-full px-4 py-3 text-lg font-mono border-2 border-green-400 rounded-lg focus:ring-2 focus:ring-green-500"
                               placeholder="Escanea código de barras o proveedor"
                               autofocus>
                        <div id="mensajeProducto" class="mt-2 text-sm"></div>
                    </div>
                </div>

                <!-- Paso 3: Escanear Ubicación -->
                <div id="pasoUbicacion" class="space-y-4 mt-6" style="display: none;">
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

                <!-- Paso 5: Ingresar Cantidad -->
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
                        Confirmar y Enviar a Revisión
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
                        <p>Seleccione un pedido para comenzar</p>
                    </div>
                </div>
            </div>

            <!-- Lista de Pedidos con Asignaciones -->
            <div class="bg-white rounded-lg shadow p-4">
                <h3 class="font-bold text-gray-700 mb-4">Mis Pedidos</h3>
                <div id="listaPedidos" class="space-y-2 max-h-[500px] overflow-y-auto">
                    <div class="text-center text-gray-400 py-4">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p class="mt-2">Cargando...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Contenedor de notificaciones -->
<div id="notificacionesContainer" class="fixed bottom-4 right-4 z-50 space-y-2 max-w-sm"></div>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
let asignacionesPorPedido = [];
let pedidoSeleccionado = null;
let asignacionActual = null;
let ubicacionActual = null;

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
    
    const iconos = {
        'success': 'fa-check-circle',
        'error': 'fa-times-circle',
        'warning': 'fa-exclamation-triangle',
        'info': 'fa-info-circle'
    };
    
    const notificacion = document.createElement('div');
    notificacion.id = id;
    notificacion.className = `${colores[tipo]} text-white px-4 py-3 rounded-lg shadow-lg flex items-center gap-2 animate-slide-up`;
    notificacion.innerHTML = `
        <i class="fas ${iconos[tipo]}"></i>
        <span class="flex-1 text-sm">${mensaje}</span>
        <button onclick="cerrarNotificacion('${id}')" class="text-white hover:text-gray-200">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    container.appendChild(notificacion);
    
    // Auto-cerrar después de 5 segundos
    setTimeout(() => {
        cerrarNotificacion(id);
    }, 5000);
}

function cerrarNotificacion(id) {
    const notificacion = document.getElementById(id);
    if (notificacion) {
        notificacion.style.animation = 'slide-down 0.3s ease-out';
        setTimeout(() => {
            notificacion.remove();
        }, 300);
    }
}

// Agregar estilos de animación
const style = document.createElement('style');
style.textContent = `
    @keyframes slide-up {
        from {
            transform: translateY(100%);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
    @keyframes slide-down {
        from {
            transform: translateY(0);
            opacity: 1;
        }
        to {
            transform: translateY(100%);
            opacity: 0;
        }
    }
    .animate-slide-up {
        animation: slide-up 0.3s ease-out;
    }
`;
document.head.appendChild(style);

// Cargar asignaciones al iniciar
cargarAsignaciones();

// Event listeners
document.getElementById('inputUbicacion').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        procesarUbicacion();
    }
});

document.getElementById('inputProducto').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        buscarProductoPorCodigo();
    }
});

document.getElementById('inputCantidad').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        procesarAsignacion();
    }
});

// Funciones
function cargarAsignaciones() {
    fetch('/warehouse-inventory/tcr/mis-asignaciones', {
        headers: {
            'X-CSRF-TOKEN': csrfToken
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.estado) {
            asignacionesPorPedido = data.asignaciones_por_pedido || [];
            mostrarListaPedidos();
            actualizarSelectPedidos();
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

function mostrarListaPedidos() {
    const lista = document.getElementById('listaPedidos');
    
    if (asignacionesPorPedido.length === 0) {
        lista.innerHTML = `
            <div class="text-center text-gray-400 py-4">
                <i class="fas fa-inbox text-2xl mb-2"></i>
                <p>No hay asignaciones pendientes</p>
            </div>
        `;
        return;
    }

    lista.innerHTML = asignacionesPorPedido.map(pedido => {
        const total = pedido.total_asignaciones;
        const completadas = pedido.completadas;
        const pendientes = pedido.pendientes;
        const enProceso = pedido.en_proceso;
        
        return `
            <div class="p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-blue-50 transition ${pedidoSeleccionado?.pedido_id === pedido.pedido_id ? 'bg-blue-50 border-blue-400' : ''}" 
                 onclick="seleccionarPedidoDesdeLista(${pedido.pedido_id})">
                <div class="font-semibold text-gray-700 mb-2">Pedido #${pedido.pedido_id}</div>
                <div class="text-xs text-gray-600 space-y-1">
                    <div>Total: <span class="font-bold">${total}</span></div>
                    <div class="flex gap-2">
                        <span class="px-2 py-1 bg-orange-100 text-orange-700 rounded text-xs">Pendientes: ${pendientes}</span>
                        <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs">En proceso: ${enProceso}</span>
                        <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs">En espera: ${completadas}</span>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function actualizarSelectPedidos() {
    const select = document.getElementById('selectPedido');
    select.innerHTML = '<option value="">Seleccione un pedido...</option>' +
        asignacionesPorPedido.map(pedido => `
            <option value="${pedido.pedido_id}">
                Pedido #${pedido.pedido_id} (${pedido.pendientes} pendientes, ${pedido.en_proceso} en proceso, ${pedido.completadas} en espera)
            </option>
        `).join('');
}

function seleccionarPedido() {
    const pedidoId = parseInt(document.getElementById('selectPedido').value);
    if (!pedidoId) return;
    
    pedidoSeleccionado = asignacionesPorPedido.find(p => p.pedido_id === pedidoId);
    if (pedidoSeleccionado) {
        mostrarAsignacionesDelPedido();
    }
}

function seleccionarPedidoDesdeLista(pedidoId) {
    document.getElementById('selectPedido').value = pedidoId;
    seleccionarPedido();
}

function mostrarAsignacionesDelPedido() {
    document.getElementById('pedidoSeleccionadoId').textContent = pedidoSeleccionado.pedido_id;
    document.getElementById('pasoSeleccionarPedido').style.display = 'none';
    document.getElementById('pasoProducto').style.display = 'block';
    document.getElementById('inputProducto').value = '';
    document.getElementById('inputProducto').focus();
    asignacionActual = null;
}

function mostrarInfoAsignacion() {
    const info = document.getElementById('infoAsignacion');
    const cantidadPendiente = (asignacionActual.cantidad - asignacionActual.cantidad_asignada).toFixed(4);
    
    info.innerHTML = `
        <div>
            <div class="font-semibold text-gray-700 mb-2">${asignacionActual.descripcion}</div>
            <div class="space-y-1 text-gray-600">
                <div><span class="font-medium">Código Barras:</span> <span class="font-mono text-xs">${asignacionActual.codigo_barras || 'N/A'}</span></div>
                <div><span class="font-medium">Código Proveedor:</span> <span class="font-mono text-xs">${asignacionActual.codigo_proveedor || 'N/A'}</span></div>
                <div><span class="font-medium">Cantidad Total:</span> ${asignacionActual.cantidad}</div>
                <div><span class="font-medium">Cantidad Asignada:</span> ${asignacionActual.cantidad_asignada}</div>
                <div><span class="font-medium text-orange-600">Cantidad Pendiente:</span> <span class="font-bold">${cantidadPendiente}</span></div>
                <div><span class="font-medium">Estado:</span> <span class="px-2 py-1 bg-${asignacionActual.estado === 'pendiente' ? 'orange' : 'blue'}-100 text-${asignacionActual.estado === 'pendiente' ? 'orange' : 'blue'}-700 rounded text-xs">${asignacionActual.estado === 'pendiente' ? 'Pendiente' : 'En proceso'}</span></div>
            </div>
        </div>
    `;
    
    document.getElementById('cantidadPendiente').textContent = cantidadPendiente;
}

function mostrarPasoUbicacion() {
    document.getElementById('pasoUbicacion').style.display = 'block';
    document.getElementById('inputUbicacion').focus();
}

function procesarUbicacion() {
    const codigo = document.getElementById('inputUbicacion').value.trim();
    const mensaje = document.getElementById('mensajeUbicacion');
    
    if (!codigo) {
        mensaje.innerHTML = '<span class="text-red-600">Ingrese un código de ubicación</span>';
        return;
    }

    mensaje.innerHTML = '<span class="text-blue-600"><i class="fas fa-spinner fa-spin"></i> Validando...</span>';

    fetch('/warehouse-inventory/tcr/buscar-ubicacion?codigo=' + encodeURIComponent(codigo), {
        headers: {
            'X-CSRF-TOKEN': csrfToken
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.estado) {
            ubicacionActual = data.ubicacion;
            mensaje.innerHTML = `<span class="text-green-600"><i class="fas fa-check-circle"></i> Ubicación válida: ${data.ubicacion.codigo}</span>`;
            mostrarPasoCantidad();
        } else {
            mensaje.innerHTML = `<span class="text-red-600"><i class="fas fa-times-circle"></i> ${data.msj}</span>`;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mensaje.innerHTML = '<span class="text-red-600">Error al validar ubicación</span>';
    });
}

// Buscar producto por código escaneado en las asignaciones del pedido
function buscarProductoPorCodigo() {
    const codigo = document.getElementById('inputProducto').value.trim().toUpperCase();
    const mensaje = document.getElementById('mensajeProducto');
    
    if (!codigo) {
        mensaje.innerHTML = '<span class="text-red-600">Ingrese un código de producto</span>';
        return;
    }

    if (!pedidoSeleccionado) {
        mensaje.innerHTML = '<span class="text-red-600">Seleccione un pedido primero</span>';
        return;
    }

    mensaje.innerHTML = '<span class="text-blue-600"><i class="fas fa-spinner fa-spin"></i> Buscando producto...</span>';

    // Buscar en las asignaciones del pedido que estén pendientes o en proceso
    const asignacionesPendientes = pedidoSeleccionado.asignaciones.filter(a => 
        (a.estado === 'pendiente' || a.estado === 'en_proceso') &&
        (a.cantidad - a.cantidad_asignada) > 0
    );

    // Buscar por código de barras o código de proveedor
    asignacionActual = asignacionesPendientes.find(a => {
        const codigoBarras = (a.codigo_barras || '').toUpperCase();
        const codigoProveedor = (a.codigo_proveedor || '').toUpperCase();
        return codigo === codigoBarras || codigo === codigoProveedor;
    });

    if (asignacionActual) {
        mensaje.innerHTML = `<span class="text-green-600"><i class="fas fa-check-circle"></i> Producto encontrado: ${asignacionActual.descripcion}</span>`;
        mostrarInfoAsignacion();
        mostrarPasoUbicacion();
    } else {
        mensaje.innerHTML = '<span class="text-red-600"><i class="fas fa-times-circle"></i> Producto no encontrado en este pedido o ya está completado</span>';
        asignacionActual = null;
    }
}

function mostrarPasoCantidad() {
    document.getElementById('pasoCantidad').style.display = 'block';
    document.getElementById('pasoFinalizar').style.display = 'block';
    document.getElementById('inputCantidad').focus();
    const cantidadPendiente = asignacionActual.cantidad - asignacionActual.cantidad_asignada;
    document.getElementById('inputCantidad').max = cantidadPendiente;
}

function usarCantidadTotal() {
    const cantidadPendiente = asignacionActual.cantidad - asignacionActual.cantidad_asignada;
    document.getElementById('inputCantidad').value = cantidadPendiente;
}

function usarCantidadParcial() {
    document.getElementById('inputCantidad').value = '';
    document.getElementById('inputCantidad').focus();
}

function procesarAsignacion() {
    const cantidad = parseFloat(document.getElementById('inputCantidad').value);
    const mensaje = document.getElementById('mensajeCantidad');
    
    if (!cantidad || cantidad <= 0) {
        mensaje.innerHTML = '<span class="text-red-600">Ingrese una cantidad válida</span>';
        return;
    }

    const cantidadPendiente = asignacionActual.cantidad - asignacionActual.cantidad_asignada;
    if (cantidad > cantidadPendiente) {
        mensaje.innerHTML = `<span class="text-red-600">La cantidad excede lo pendiente (${cantidadPendiente})</span>`;
        return;
    }

    mensaje.innerHTML = '<span class="text-blue-600"><i class="fas fa-spinner fa-spin"></i> Procesando...</span>';

    fetch('/warehouse-inventory/tcr/procesar-asignacion', {
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
    .then(response => response.json())
    .then(data => {
        if (data.estado) {
            mensaje.innerHTML = '<span class="text-green-600"><i class="fas fa-check-circle"></i> Asignación enviada a revisión</span>';
            
            setTimeout(() => {
                resetearFormulario();
                cargarAsignaciones();
                if (data.en_espera) {
                    mostrarNotificacion('¡Asignación completada y enviada a revisión del chequeador!', 'success');
                }
            }, 1500);
        } else {
            mensaje.innerHTML = `<span class="text-red-600"><i class="fas fa-times-circle"></i> ${data.msj}</span>`;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mensaje.innerHTML = '<span class="text-red-600">Error al procesar asignación</span>';
    });
}

function resetearFormulario() {
    asignacionActual = null;
    ubicacionActual = null;
    // No resetear pedidoSeleccionado para permitir escanear múltiples productos del mismo pedido
    document.getElementById('inputUbicacion').value = '';
    document.getElementById('inputProducto').value = '';
    document.getElementById('inputCantidad').value = '';
    document.getElementById('pasoUbicacion').style.display = 'none';
    document.getElementById('pasoProducto').style.display = 'block'; // Volver al paso de escanear producto
    document.getElementById('pasoCantidad').style.display = 'none';
    document.getElementById('pasoFinalizar').style.display = 'none';
    document.getElementById('inputProducto').focus();
    document.getElementById('infoAsignacion').innerHTML = `
        <div class="text-center text-gray-400 py-8">
            <i class="fas fa-info-circle text-4xl mb-2"></i>
            <p>Escanea un producto del pedido</p>
        </div>
    `;
}

// Función para volver a seleccionar pedido
function volverASeleccionarPedido() {
    pedidoSeleccionado = null;
    asignacionActual = null;
    ubicacionActual = null;
    document.getElementById('selectPedido').value = '';
    document.getElementById('inputUbicacion').value = '';
    document.getElementById('inputProducto').value = '';
    document.getElementById('inputCantidad').value = '';
    document.getElementById('pasoSeleccionarPedido').style.display = 'block';
    document.getElementById('pasoUbicacion').style.display = 'none';
    document.getElementById('pasoProducto').style.display = 'none';
    document.getElementById('pasoCantidad').style.display = 'none';
    document.getElementById('pasoFinalizar').style.display = 'none';
    document.getElementById('infoAsignacion').innerHTML = `
        <div class="text-center text-gray-400 py-8">
            <i class="fas fa-info-circle text-4xl mb-2"></i>
            <p>Seleccione un pedido para comenzar</p>
        </div>
    `;
}
</script>
@endsection
