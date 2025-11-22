@extends('layouts.app')

@section('nav')
    @include('warehouse-inventory.partials.nav')
@endsection

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="mb-4">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center">
            <i class="fas fa-clipboard-check text-blue-500 mr-2"></i>
            TCR - Chequeador
        </h1>
        <p class="text-gray-600 mt-1">Asigna productos a pasilleros para su procesamiento</p>
    </div>

    <!-- Filtros de búsqueda -->
    <div class="bg-white rounded-lg shadow p-4 mb-4">
        <form id="formBuscarPedidos" class="space-y-3">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <div class="md:col-span-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nº Transferencia</label>
                    <input type="text" id="qpedidoscentralq" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-400" placeholder="Buscar por ID...">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                    <select id="qpedidocentralestado" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-400">
                        <option value="">Todos</option>
                        <option value="4">REVISADO</option>
                        <option value="3">EN REVISIÓN</option>
                        <option value="1">PENDIENTE</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Límite</label>
                    <select id="qpedidocentrallimit" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-400">
                        <option value="5">5</option>
                        <option value="10">10</option>
                        <option value="20" selected>20</option>
                        <option value="50">50</option>
                        <option value="">Todo</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Emisor</label>
                    <select id="qpedidocentralemisor" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-400">
                        <option value="">Todas las Sucursales</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="w-full md:w-auto px-6 py-2 bg-blue-500 hover:bg-blue-600 text-white font-semibold rounded-lg shadow transition">
                <i class="fas fa-search mr-2"></i>
                BUSCAR TRANSFERENCIAS
            </button>
        </form>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <!-- Lista de Pedidos -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow">
                <div class="p-4 border-b border-gray-200">
                    <h2 class="font-bold text-gray-700">Transferencias</h2>
                </div>
                <div id="listaPedidos" class="p-4 space-y-2 max-h-[600px] overflow-y-auto">
                    <div class="text-center text-gray-400 py-8">
                        <i class="fas fa-inbox text-4xl mb-2"></i>
                        <p>Busca transferencias para comenzar</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detalle del Pedido y Asignación -->
        <div class="lg:col-span-2">
            <div id="detallePedido" class="bg-white rounded-lg shadow p-4" style="display: none;">
                <div class="mb-4">
                    <h2 class="text-xl font-bold text-gray-700 mb-2">Pedido #<span id="pedidoId"></span></h2>
                    <div class="text-sm text-gray-600">
                        <span id="pedidoFecha"></span> | 
                        <span id="pedidoOrigen"></span> | 
                        <span id="pedidoItems"></span> items
                    </div>
                </div>

                <!-- Tabs: Asignar vs Ver Estado -->
                <div class="mb-4 border-b border-gray-200">
                    <div class="flex gap-2">
                        <button id="tabAsignar" onclick="mostrarTab('asignar')" class="px-4 py-2 font-semibold border-b-2 border-blue-500 text-blue-600">
                            Asignar Productos
                        </button>
                        <button id="tabEstado" onclick="mostrarTab('estado')" class="px-4 py-2 font-semibold border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                            Ver Estado de Asignaciones
                        </button>
                    </div>
                </div>

                <!-- Tab: Asignar Productos -->
                <div id="contenidoAsignar" class="tab-content">
                    <div class="mb-4">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="font-bold text-gray-700">Productos</h3>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" id="checkTodo" onchange="toggleSeleccionarTodos(this.checked)" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                <span class="text-sm font-semibold text-gray-700">Seleccionar Todos</span>
                            </label>
                        </div>
                        <div id="listaProductos" class="space-y-2 max-h-[400px] overflow-y-auto">
                        </div>
                    </div>

                    <!-- Botón de Asignar -->
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <button id="btnAsignar" class="w-full px-6 py-3 bg-green-500 hover:bg-green-600 text-white font-semibold rounded-lg shadow transition">
                            <i class="fas fa-user-check mr-2"></i>
                            Asignar Productos Seleccionados
                        </button>
                    </div>
                </div>

                <!-- Tab: Ver Estado de Asignaciones -->
                <div id="contenidoEstado" class="tab-content" style="display: none;">
                    <div class="mb-4">
                        <h3 class="font-bold text-gray-700 mb-3">Estado de Asignaciones por Pasillero</h3>
                        <div id="estadoAsignaciones" class="space-y-4">
                            <div class="text-center text-gray-400 py-8">
                                <i class="fas fa-spinner fa-spin text-4xl mb-2"></i>
                                <p>Cargando estado de asignaciones...</p>
                            </div>
                        </div>
                    </div>

                    <!-- Resumen y Botón Guardar -->
                    <div id="resumenPedido" class="mt-4 pt-4 border-t border-gray-200" style="display: none;">
                        <div id="resumenInfo" class="mb-4 p-4 bg-gray-50 rounded-lg">
                            <!-- Se llenará con JavaScript -->
                        </div>
                        <button id="btnGuardarPedido" class="w-full px-6 py-4 bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-lg rounded-lg shadow-lg transition" style="display: none;">
                            <i class="fas fa-save mr-2"></i>
                            Guardar Pedido Procesado
                        </button>
                    </div>
                </div>
            </div>

            <div id="sinSeleccion" class="bg-white rounded-lg shadow p-12 text-center">
                <i class="fas fa-hand-pointer text-gray-300 text-6xl mb-4"></i>
                <h3 class="text-xl font-bold text-gray-700 mb-2">Seleccione una transferencia</h3>
                <p class="text-gray-500">Haz clic en una transferencia de la lista para ver sus detalles</p>
            </div>
        </div>
    </div>
</div>

<!-- Contenedor de notificaciones -->
<div id="notificacionesContainer" class="fixed bottom-4 right-4 z-50 space-y-2 max-w-sm"></div>

<!-- Modal para seleccionar pasillero -->
<div id="modalPasillero" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="p-6">
            <h3 class="text-xl font-bold text-gray-700 mb-4">Seleccionar Pasillero</h3>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Pasillero</label>
                <select id="selectPasillero" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-400">
                    <option value="">Seleccione un pasillero...</option>
                </select>
            </div>
            <div class="flex gap-3">
                <button id="btnConfirmarAsignacion" class="flex-1 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white font-semibold rounded-lg transition">
                    Confirmar
                </button>
                <button id="btnCancelarAsignacion" class="flex-1 px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-700 font-semibold rounded-lg transition">
                    Cancelar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let pedidosCentral = [];
let pedidoSeleccionado = null;
let pasilleros = [];
let productosSeleccionados = [];

// CSRF Token
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

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

// Cargar pasilleros al iniciar
cargarPasilleros();

// Event listeners
document.getElementById('formBuscarPedidos').addEventListener('submit', function(e) {
    e.preventDefault();
    buscarPedidos();
});

document.getElementById('btnAsignar').addEventListener('click', function() {
    abrirModalPasillero();
});

document.getElementById('btnConfirmarAsignacion').addEventListener('click', function() {
    confirmarAsignacion();
});

document.getElementById('btnCancelarAsignacion').addEventListener('click', function() {
    cerrarModalPasillero();
});

// Funciones
function buscarPedidos() {
    const qpedidoscentralq = document.getElementById('qpedidoscentralq').value;
    const qpedidocentrallimit = document.getElementById('qpedidocentrallimit').value;
    const qpedidocentralemisor = document.getElementById('qpedidocentralemisor').value;
    const qpedidocentralestado = document.getElementById('qpedidocentralestado').value;

    fetch('/warehouse-inventory/tcr/get-pedidos-central', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            qpedidoscentralq,
            qpedidocentrallimit,
            qpedidocentralestado,
            qpedidocentralemisor
        })
    })
    .then(response => response.json())
    .then(data => {
        if (Array.isArray(data)) {
            pedidosCentral = data;
            mostrarListaPedidos();
        } else {
            mostrarNotificacion('Error: ' + (data.msj || 'No se encontraron pedidos'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarNotificacion('Error al buscar pedidos', 'error');
    });
}

function mostrarListaPedidos() {
    const listaPedidos = document.getElementById('listaPedidos');
    
    if (pedidosCentral.length === 0) {
        listaPedidos.innerHTML = `
            <div class="text-center text-gray-400 py-8">
                <i class="fas fa-inbox text-4xl mb-2"></i>
                <p>No se encontraron transferencias</p>
            </div>
        `;
        return;
    }

    listaPedidos.innerHTML = pedidosCentral.map((pedido, index) => {
        // Determinar color según estado
        let estadoColor = '';
        let estadoTexto = '';
        let borderColor = '';
        
        if (pedido.estado == 1) {
            // PENDIENTE - Rojo
            estadoColor = 'bg-red-50';
            borderColor = 'border-red-300';
            estadoTexto = 'PENDIENTE';
        } else if (pedido.estado == 3) {
            // EN REVISIÓN - Naranja
            estadoColor = 'bg-orange-50';
            borderColor = 'border-orange-300';
            estadoTexto = 'EN REVISIÓN';
        } else if (pedido.estado == 4) {
            // REVISADO - Verde/Azul
            estadoColor = 'bg-blue-50';
            borderColor = 'border-blue-300';
            estadoTexto = 'REVISADO';
        } else {
            // Estado desconocido - Gris
            estadoColor = 'bg-gray-50';
            borderColor = 'border-gray-300';
            estadoTexto = 'DESCONOCIDO';
        }
        
        // Si está seleccionado, usar color más intenso
        const isSelected = pedidoSeleccionado?.id === pedido.id;
        const selectedClass = isSelected ? 'ring-2 ring-blue-400 shadow-md' : '';
        
        return `
        <div class="p-3 border-2 ${borderColor} ${estadoColor} rounded-lg cursor-pointer hover:shadow-md transition ${selectedClass}" 
             onclick="seleccionarPedido(${index})">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                    <span class="font-bold text-gray-700">#${pedido.id}</span>
                    <span class="px-2 py-0.5 text-[10px] font-bold rounded border ${
                        pedido.estado == 1 ? 'bg-red-100 text-red-700 border-red-200' :
                        pedido.estado == 3 ? 'bg-orange-100 text-orange-700 border-orange-200' :
                        pedido.estado == 4 ? 'bg-blue-100 text-blue-700 border-blue-200' :
                        'bg-gray-100 text-gray-600 border-gray-200'
                    }">
                        ${estadoTexto}
                    </span>
                </div>
                <span class="text-xs text-gray-500">${pedido.items.length} items</span>
            </div>
            <div class="text-xs text-gray-600">
                ${pedido.origen?.codigo || 'N/A'} | ${pedido.created_at || ''}
            </div>
        </div>
    `;
    }).join('');
}

function seleccionarPedido(index) {
    pedidoSeleccionado = pedidosCentral[index];
    mostrarDetallePedido();
    // Si estamos en la tab de estado, cargar asignaciones
    if (document.getElementById('contenidoEstado').style.display !== 'none') {
        cargarEstadoAsignaciones();
    }
}

function mostrarDetallePedido() {
    document.getElementById('detallePedido').style.display = 'block';
    document.getElementById('sinSeleccion').style.display = 'none';
    
    document.getElementById('pedidoId').textContent = pedidoSeleccionado.id;
    document.getElementById('pedidoFecha').textContent = pedidoSeleccionado.created_at || '';
    document.getElementById('pedidoOrigen').textContent = pedidoSeleccionado.origen?.codigo || 'N/A';
    document.getElementById('pedidoItems').textContent = pedidoSeleccionado.items.length;

    const listaProductos = document.getElementById('listaProductos');
    productosSeleccionados = [];
    // Resetear checkbox de seleccionar todos
    const checkTodo = document.getElementById('checkTodo');
    if (checkTodo) checkTodo.checked = false;
    
    listaProductos.innerHTML = pedidoSeleccionado.items.map((item, index) => {
        const cantidadPendiente = item.cantidad_pendiente || item.cantidad;
        const cantidadAsignada = item.cantidad_asignada_total || 0;
        
        return `
            <div class="p-3 border border-gray-200 rounded-lg">
                <div class="flex items-start gap-3">
                    <input type="checkbox" 
                           class="mt-1 checkbox-producto" 
                           data-item-id="${item.id}"
                           data-index="${index}"
                           onchange="toggleProducto(${index})">
                    <div class="flex-1">
                        <div class="font-semibold text-gray-700 mb-1">${item.producto?.descripcion || 'Sin descripción'}</div>
                        <div class="text-sm text-gray-600 space-y-1">
                            <div>Código Barras: <span class="font-mono">${item.producto?.codigo_barras || 'N/A'}</span></div>
                            <div>Código Proveedor: <span class="font-mono">${item.producto?.codigo_proveedor || 'N/A'}</span></div>
                            <div>Cantidad: <span class="font-bold">${item.cantidad}</span> | 
                                 Asignada: <span class="text-green-600">${cantidadAsignada}</span> | 
                                 Pendiente: <span class="text-orange-600">${cantidadPendiente}</span>
                            </div>
                        </div>
                        <div class="mt-2">
                            <label class="text-xs text-gray-600">Cantidad a asignar:</label>
                            <input type="number" 
                                   step="0.0001" 
                                   min="0.0001" 
                                   max="${cantidadPendiente}"
                                   value="${cantidadPendiente}"
                                   class="w-full px-2 py-1 border border-gray-300 rounded text-sm cantidad-input"
                                   data-item-id="${item.id}"
                                   data-index="${index}">
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function toggleProducto(index) {
    const checkbox = document.querySelector(`.checkbox-producto[data-index="${index}"]`);
    const cantidadInput = document.querySelector(`.cantidad-input[data-index="${index}"]`);
    const itemId = parseInt(checkbox.getAttribute('data-item-id'));
    
    if (checkbox.checked) {
        // Verificar si ya está seleccionado para evitar duplicados
        const existe = productosSeleccionados.some(p => p.item_id === itemId);
        if (!existe) {
            productosSeleccionados.push({
                item_id: itemId,
                cantidad: parseFloat(cantidadInput.value)
            });
        }
    } else {
        productosSeleccionados = productosSeleccionados.filter(p => p.item_id !== itemId);
    }
}

function toggleSeleccionarTodos(checked) {
    const checkboxes = document.querySelectorAll('.checkbox-producto');
    
    // Si desmarcamos, limpiamos todo
    if (!checked) {
        productosSeleccionados = [];
        checkboxes.forEach(cb => cb.checked = false);
        return;
    }
    
    // Si marcamos, seleccionamos todo
    checkboxes.forEach(cb => {
        cb.checked = true;
        const index = cb.getAttribute('data-index');
        toggleProducto(index);
    });
}

function cargarPasilleros() {
    fetch('/warehouse-inventory/tcr/get-pasilleros', {
        headers: {
            'X-CSRF-TOKEN': csrfToken
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Error en la respuesta del servidor');
        }
        return response.json();
    })
    .then(data => {
        console.log('Datos recibidos:', data); // Debug
        if (data.estado && data.pasilleros) {
            pasilleros = data.pasilleros;
            const select = document.getElementById('selectPasillero');
            if (pasilleros.length > 0) {
                select.innerHTML = '<option value="">Seleccione un pasillero...</option>' +
                    pasilleros.map(p => `<option value="${p.id}">${p.nombre || p.name || 'Usuario ' + p.id}</option>`).join('');
            } else {
                select.innerHTML = '<option value="">No hay pasilleros disponibles</option>';
                console.warn('No se encontraron pasilleros');
            }
        } else {
            console.error('Error en la respuesta:', data);
            mostrarNotificacion('Error al cargar pasilleros: ' + (data.msj || 'Error desconocido'), 'error');
        }
    })
    .catch(error => {
        console.error('Error al cargar pasilleros:', error);
        mostrarNotificacion('Error al cargar pasilleros. Ver consola para más detalles.', 'error');
    });
}

function abrirModalPasillero() {
    if (productosSeleccionados.length === 0) {
        mostrarNotificacion('Seleccione al menos un producto', 'warning');
        return;
    }
    document.getElementById('modalPasillero').classList.remove('hidden');
}

function cerrarModalPasillero() {
    document.getElementById('modalPasillero').classList.add('hidden');
}

function confirmarAsignacion() {
    const pasilleroId = document.getElementById('selectPasillero').value;
    
    if (!pasilleroId) {
        mostrarNotificacion('Seleccione un pasillero', 'warning');
        return;
    }

    // Actualizar productos seleccionados con las cantidades del input
    productosSeleccionados = productosSeleccionados.map(prod => {
        const input = document.querySelector(`.cantidad-input[data-item-id="${prod.item_id}"]`);
        return {
            ...prod,
            cantidad: parseFloat(input.value),
            pasillero_id: parseInt(pasilleroId)
        };
    });

    fetch('/warehouse-inventory/tcr/asignar-productos', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            pedido_id: pedidoSeleccionado.id,
            items: productosSeleccionados.map(p => ({
                item_id: p.item_id,
                pasillero_id: p.pasillero_id,
                cantidad: p.cantidad
            }))
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.estado) {
            mostrarNotificacion('Productos asignados exitosamente', 'success');
            cerrarModalPasillero();
            buscarPedidos(); // Recargar lista
            seleccionarPedido(pedidosCentral.findIndex(p => p.id === pedidoSeleccionado.id));
        } else {
            mostrarNotificacion('Error: ' + data.msj, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarNotificacion('Error al asignar productos', 'error');
    });
}

// Actualizar cantidades cuando cambian los inputs
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('cantidad-input')) {
        const itemId = parseInt(e.target.getAttribute('data-item-id'));
        const producto = productosSeleccionados.find(p => p.item_id === itemId);
        if (producto) {
            producto.cantidad = parseFloat(e.target.value);
        }
    }
});

// Funciones para tabs
function mostrarTab(tab) {
    if (tab === 'asignar') {
        document.getElementById('contenidoAsignar').style.display = 'block';
        document.getElementById('contenidoEstado').style.display = 'none';
        document.getElementById('tabAsignar').classList.add('border-blue-500', 'text-blue-600');
        document.getElementById('tabAsignar').classList.remove('border-transparent', 'text-gray-500');
        document.getElementById('tabEstado').classList.remove('border-blue-500', 'text-blue-600');
        document.getElementById('tabEstado').classList.add('border-transparent', 'text-gray-500');
    } else {
        document.getElementById('contenidoAsignar').style.display = 'none';
        document.getElementById('contenidoEstado').style.display = 'block';
        document.getElementById('tabEstado').classList.add('border-blue-500', 'text-blue-600');
        document.getElementById('tabEstado').classList.remove('border-transparent', 'text-gray-500');
        document.getElementById('tabAsignar').classList.remove('border-blue-500', 'text-blue-600');
        document.getElementById('tabAsignar').classList.add('border-transparent', 'text-gray-500');
        
        // Cargar estado de asignaciones cuando se cambia a la tab de estado
        if (pedidoSeleccionado) {
            cargarEstadoAsignaciones();
        }
    }
}

// Cargar estado de asignaciones del pedido
function cargarEstadoAsignaciones() {
    if (!pedidoSeleccionado) return;
    
    const estadoDiv = document.getElementById('estadoAsignaciones');
    estadoDiv.innerHTML = '<div class="text-center text-gray-400 py-4"><i class="fas fa-spinner fa-spin"></i> Cargando...</div>';
    
    fetch(`/warehouse-inventory/tcr/asignaciones-por-pedido?pedido_id=${pedidoSeleccionado.id}`, {
        headers: {
            'X-CSRF-TOKEN': csrfToken
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.estado) {
            mostrarEstadoAsignaciones(data);
        } else {
            estadoDiv.innerHTML = `<div class="text-center text-red-500 py-4">${data.msj || 'Error al cargar asignaciones'}</div>`;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        estadoDiv.innerHTML = '<div class="text-center text-red-500 py-4">Error al cargar asignaciones</div>';
    });
}

// Mostrar estado de asignaciones
function mostrarEstadoAsignaciones(data) {
    const estadoDiv = document.getElementById('estadoAsignaciones');
    const resumenDiv = document.getElementById('resumenInfo');
    const btnGuardar = document.getElementById('btnGuardarPedido');
    const resumenPedido = document.getElementById('resumenPedido');
    
    if (data.asignaciones_por_pedido.length === 0) {
        estadoDiv.innerHTML = `
            <div class="text-center text-gray-400 py-8">
                <i class="fas fa-inbox text-4xl mb-2"></i>
                <p>No hay asignaciones para este pedido</p>
            </div>
        `;
        resumenPedido.style.display = 'none';
        return;
    }
    
    // Mostrar asignaciones agrupadas por pasillero
    estadoDiv.innerHTML = data.asignaciones_por_pedido.map(pasillero => `
        <div class="border border-gray-200 rounded-lg p-4">
            <div class="flex items-center justify-between mb-3">
                <h4 class="font-bold text-gray-700">Pasillero: ${pasillero.pasillero_nombre}</h4>
                <div class="flex gap-2 text-xs">
                    <span class="px-2 py-1 bg-orange-100 text-orange-700 rounded">Pendientes: ${pasillero.pendientes}</span>
                    <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded">En proceso: ${pasillero.en_proceso}</span>
                    <span class="px-2 py-1 bg-green-100 text-green-700 rounded">Completadas: ${pasillero.completadas}</span>
                </div>
            </div>
            <div class="space-y-2">
                ${pasillero.asignaciones.map(asig => {
                    const estadoColor = {
                        'pendiente': 'bg-orange-50 border-orange-300',
                        'en_proceso': 'bg-blue-50 border-blue-300',
                        'completado': 'bg-green-50 border-green-300'
                    }[asig.estado] || 'bg-gray-50 border-gray-300';
                    
                    const estadoTexto = {
                        'pendiente': 'Pendiente',
                        'en_proceso': 'En proceso',
                        'completado': 'Completado (listo para confirmar)'
                    }[asig.estado] || asig.estado;
                    
                    return `
                        <div class="p-2 border rounded ${estadoColor}">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="font-semibold text-sm">${asig.descripcion}</div>
                                    <div class="text-xs text-gray-600 mt-1">
                                        Cantidad: ${asig.cantidad} | Asignada: ${asig.cantidad_asignada} | 
                                        ${asig.warehouse_codigo ? `Ubicación: <span class="font-mono font-bold">${asig.warehouse_codigo}</span>` : 'Sin ubicación'}
                                    </div>
                                </div>
                                <span class="px-2 py-1 text-xs font-semibold rounded ${asig.estado === 'completado' ? 'bg-green-500 text-white' : asig.estado === 'en_proceso' ? 'bg-blue-500 text-white' : asig.estado === 'pendiente' ? 'bg-orange-500 text-white' : 'bg-gray-400 text-white'}">
                                    ${estadoTexto}
                                </span>
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
        </div>
    `).join('');
    
    // Mostrar resumen
    resumenDiv.innerHTML = `
        <div class="grid grid-cols-3 gap-4 text-center">
            <div>
                <div class="text-2xl font-bold text-orange-600">${data.resumen.pendientes}</div>
                <div class="text-xs text-gray-600">Pendientes</div>
            </div>
            <div>
                <div class="text-2xl font-bold text-blue-600">${data.resumen.en_proceso}</div>
                <div class="text-xs text-gray-600">En Proceso</div>
            </div>
            <div>
                <div class="text-2xl font-bold text-green-600">${data.resumen.completadas}</div>
                <div class="text-xs text-gray-600">Completadas</div>
            </div>
        </div>
        <div class="mt-4 text-center">
            <div class="text-sm text-gray-600">Total: <span class="font-bold">${data.total_asignaciones}</span> asignaciones</div>
        </div>
    `;
    
    resumenPedido.style.display = 'block';
    
    // Mostrar botón de guardar si todas están listas
    if (data.todas_listas) {
        btnGuardar.style.display = 'block';
        btnGuardar.onclick = () => guardarPedido();
    } else {
        btnGuardar.style.display = 'none';
    }
}

// Guardar pedido procesado
function guardarPedido() {
    if (!pedidoSeleccionado) {
        mostrarNotificacion('No hay pedido seleccionado', 'warning');
        return;
    }
    
    if (!confirm('¿Está seguro de que desea guardar este pedido? Esto creará los registros en warehouse_inventory.')) {
        return;
    }
    
    const btnGuardar = document.getElementById('btnGuardarPedido');
    btnGuardar.disabled = true;
    btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Guardando...';
    
    fetch('/warehouse-inventory/tcr/confirmar-pedido', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            pedido_id: pedidoSeleccionado.id
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.estado) {
            mostrarNotificacion(data.msj || 'Pedido guardado exitosamente', 'success');
            // Recargar estado
            cargarEstadoAsignaciones();
            buscarPedidos();
        } else {
            mostrarNotificacion('Error: ' + (data.msj || 'Error al guardar pedido'), 'error');
        }
        btnGuardar.disabled = false;
        btnGuardar.innerHTML = '<i class="fas fa-save mr-2"></i> Guardar Pedido Procesado';
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarNotificacion('Error al guardar pedido', 'error');
        btnGuardar.disabled = false;
        btnGuardar.innerHTML = '<i class="fas fa-save mr-2"></i> Guardar Pedido Procesado';
    });
}
</script>
@endsection