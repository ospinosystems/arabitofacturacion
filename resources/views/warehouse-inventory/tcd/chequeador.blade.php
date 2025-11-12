@extends('layouts.app')

@section('nav')
    @include('warehouse-inventory.partials.nav')
@endsection

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="mb-4">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center">
            <i class="fas fa-tower-control text-blue-500 mr-2"></i>
            TCD - Torre de Control de Despacho
        </h1>
        <p class="text-gray-600 mt-1">Crea órdenes de despacho y asigna productos a pasilleros</p>
    </div>

    <!-- Tabs: Crear Orden vs Ver Órdenes -->
    <div class="mb-4 border-b border-gray-200">
        <div class="flex gap-2">
            <button id="tabCrear" onclick="mostrarTab('crear')" class="px-4 py-2 font-semibold border-b-2 border-blue-500 text-blue-600">
                <i class="fas fa-plus-circle mr-2"></i>Crear Orden
            </button>
            <button id="tabOrdenes" onclick="mostrarTab('ordenes')" class="px-4 py-2 font-semibold border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                <i class="fas fa-list mr-2"></i>Mis Órdenes
            </button>
        </div>
    </div>

    <!-- Tab: Crear Orden -->
    <div id="contenidoCrear" class="tab-content">
        <!-- Búsqueda de Productos -->
        <div class="bg-white rounded-lg shadow p-4 mb-4">
            <h3 class="font-bold text-gray-700 mb-3">Buscar Productos</h3>
            <div class="flex gap-2">
                <input type="text" 
                       id="buscarProducto" 
                       class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-400 focus:border-blue-400" 
                       placeholder="Buscar por código de barras, código proveedor o descripción..."
                       autocomplete="off">
                <button onclick="buscarProductos()" class="px-6 py-2 bg-blue-500 hover:bg-blue-600 text-white font-semibold rounded-lg shadow transition">
                    <i class="fas fa-search mr-2"></i>Buscar
                </button>
            </div>
            <div id="resultadosBusqueda" class="mt-4 space-y-2 max-h-64 overflow-y-auto" style="display: none;">
            </div>
        </div>

        <!-- Carrito de Productos -->
        <div class="bg-white rounded-lg shadow p-4 mb-4">
            <h3 class="font-bold text-gray-700 mb-3 flex items-center justify-between">
                <span><i class="fas fa-shopping-cart mr-2"></i>Carrito de Productos</span>
                <span id="contadorCarrito" class="text-sm text-gray-500">0 productos</span>
            </h3>
            <div id="carritoProductos" class="space-y-2">
                <div class="text-center text-gray-400 py-8">
                    <i class="fas fa-shopping-cart text-4xl mb-2"></i>
                    <p>El carrito está vacío</p>
                </div>
            </div>
            <div id="accionesCarrito" class="mt-4 pt-4 border-t border-gray-200" style="display: none;">
                <div class="flex gap-2">
                    <button onclick="limpiarCarrito()" class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white font-semibold rounded-lg transition">
                        <i class="fas fa-trash mr-2"></i>Limpiar Carrito
                    </button>
                    <button onclick="crearOrden()" class="flex-1 px-6 py-2 bg-green-500 hover:bg-green-600 text-white font-semibold rounded-lg shadow transition">
                        <i class="fas fa-check-circle mr-2"></i>Crear Orden
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab: Ver Órdenes -->
    <div id="contenidoOrdenes" class="tab-content" style="display: none;">
        <div class="bg-white rounded-lg shadow p-4 mb-4">
            <div class="flex gap-2 mb-4">
                <select id="filtroEstado" onchange="cargarOrdenes()" class="px-4 py-2 border border-gray-300 rounded-lg">
                    <option value="">Todos los estados</option>
                    <option value="borrador">Borrador</option>
                    <option value="asignada">Asignada</option>
                    <option value="en_proceso">En Proceso</option>
                    <option value="completada">Completada</option>
                    <option value="despachada">Despachada</option>
                </select>
            </div>
            <div id="listaOrdenes" class="space-y-3">
                <div class="text-center text-gray-400 py-8">
                    <i class="fas fa-spinner fa-spin text-4xl mb-2"></i>
                    <p>Cargando órdenes...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Asignar a Pasilleros -->
    <div id="modalAsignar" class="fixed inset-0 bg-black bg-opacity-50 z-50" style="display: none;">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-bold text-gray-700">Asignar Productos a Pasilleros</h3>
                        <button onclick="cerrarModalAsignar()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-2xl"></i>
                        </button>
                    </div>
                    <div id="contenidoAsignar" class="space-y-4">
                    </div>
                    <div class="mt-6 flex gap-2">
                        <button onclick="cerrarModalAsignar()" class="flex-1 px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white font-semibold rounded-lg transition">
                            Cancelar
                        </button>
                        <button onclick="guardarAsignaciones()" class="flex-1 px-6 py-2 bg-green-500 hover:bg-green-600 text-white font-semibold rounded-lg shadow transition">
                            <i class="fas fa-save mr-2"></i>Guardar Asignaciones
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Ver Estado de Asignaciones -->
    <div id="modalEstado" class="fixed inset-0 bg-black bg-opacity-50 z-50" style="display: none;">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-bold text-gray-700">Estado de Asignaciones</h3>
                        <button onclick="cerrarModalEstado()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-2xl"></i>
                        </button>
                    </div>
                    <div id="contenidoEstado" class="space-y-4">
                    </div>
                    <div class="mt-6">
                        <button onclick="cerrarModalEstado()" class="w-full px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white font-semibold rounded-lg transition">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let carrito = [];
let ordenActual = null;
let pasilleros = [];

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    cargarPasilleros();
    cargarOrdenes();
    
    // Buscar producto al presionar Enter
    document.getElementById('buscarProducto').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            buscarProductos();
        }
    });
});

function mostrarTab(tab) {
    // Ocultar todos los contenidos
    document.getElementById('contenidoCrear').style.display = 'none';
    document.getElementById('contenidoOrdenes').style.display = 'none';
    
    // Remover estilos activos
    document.getElementById('tabCrear').classList.remove('border-blue-500', 'text-blue-600');
    document.getElementById('tabCrear').classList.add('border-transparent', 'text-gray-500');
    document.getElementById('tabOrdenes').classList.remove('border-blue-500', 'text-blue-600');
    document.getElementById('tabOrdenes').classList.add('border-transparent', 'text-gray-500');
    
    // Mostrar tab seleccionado
    if (tab === 'crear') {
        document.getElementById('contenidoCrear').style.display = 'block';
        document.getElementById('tabCrear').classList.remove('border-transparent', 'text-gray-500');
        document.getElementById('tabCrear').classList.add('border-blue-500', 'text-blue-600');
    } else {
        document.getElementById('contenidoOrdenes').style.display = 'block';
        document.getElementById('tabOrdenes').classList.remove('border-transparent', 'text-gray-500');
        document.getElementById('tabOrdenes').classList.add('border-blue-500', 'text-blue-600');
        cargarOrdenes();
    }
}

function buscarProductos() {
    const buscar = document.getElementById('buscarProducto').value.trim();
    
    if (!buscar || buscar.length < 2) {
        alert('Ingrese al menos 2 caracteres para buscar');
        return;
    }
    
    fetch(`/warehouse-inventory/tcd/buscar-productos?buscar=${encodeURIComponent(buscar)}`)
        .then(res => res.json())
        .then(data => {
            if (data.estado) {
                mostrarResultadosBusqueda(data.productos);
            } else {
                alert(data.msj || 'Error al buscar productos');
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error al buscar productos');
        });
}

function mostrarResultadosBusqueda(productos) {
    const contenedor = document.getElementById('resultadosBusqueda');
    contenedor.style.display = 'block';
    
    if (productos.length === 0) {
        contenedor.innerHTML = '<div class="text-center text-gray-400 py-4">No se encontraron productos</div>';
        return;
    }
    
    contenedor.innerHTML = productos.map(p => `
        <div class="border border-gray-200 rounded-lg p-3 hover:bg-gray-50 cursor-pointer" onclick="agregarAlCarrito(${JSON.stringify(p).replace(/"/g, '&quot;')})">
            <div class="flex justify-between items-start">
                <div class="flex-1">
                    <div class="font-semibold text-gray-800">${p.descripcion}</div>
                    <div class="text-sm text-gray-600 mt-1">
                        <span class="mr-3"><strong>Código Barras:</strong> ${p.codigo_barras || 'N/A'}</span>
                        <span class="mr-3"><strong>Código Proveedor:</strong> ${p.codigo_proveedor || 'N/A'}</span>
                        <span><strong>Ubicación:</strong> ${p.ubicacion}</span>
                    </div>
                    <div class="text-sm text-gray-600 mt-1">
                        <span class="mr-3"><strong>Precio:</strong> ${p.precio || 0}</span>
                        <span><strong>Precio Base:</strong> ${p.precio_base || 0}</span>
                    </div>
                </div>
                <button class="ml-2 px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
        </div>
    `).join('');
}

function agregarAlCarrito(producto) {
    // Verificar si ya está en el carrito
    const existe = carrito.find(p => p.id === producto.id);
    if (existe) {
        if (confirm('El producto ya está en el carrito. ¿Desea actualizar la cantidad?')) {
            const cantidad = prompt('Ingrese la cantidad:', existe.cantidad);
            if (cantidad && parseFloat(cantidad) > 0) {
                existe.cantidad = parseFloat(cantidad);
                actualizarCarrito();
            }
        }
        return;
    }
    
    const cantidad = prompt('Ingrese la cantidad:', '1');
    if (cantidad && parseFloat(cantidad) > 0) {
        producto.cantidad = parseFloat(cantidad);
        carrito.push(producto);
        actualizarCarrito();
    }
}

function actualizarCarrito() {
    const contenedor = document.getElementById('carritoProductos');
    const acciones = document.getElementById('accionesCarrito');
    const contador = document.getElementById('contadorCarrito');
    
    contador.textContent = `${carrito.length} producto(s)`;
    
    if (carrito.length === 0) {
        contenedor.innerHTML = `
            <div class="text-center text-gray-400 py-8">
                <i class="fas fa-shopping-cart text-4xl mb-2"></i>
                <p>El carrito está vacío</p>
            </div>
        `;
        acciones.style.display = 'none';
        return;
    }
    
    acciones.style.display = 'block';
    contenedor.innerHTML = carrito.map((p, index) => `
        <div class="border border-gray-200 rounded-lg p-3">
            <div class="flex justify-between items-start">
                <div class="flex-1">
                    <div class="font-semibold text-gray-800">${p.descripcion}</div>
                    <div class="text-sm text-gray-600 mt-1">
                        <span class="mr-3"><strong>Código Barras:</strong> ${p.codigo_barras || 'N/A'}</span>
                        <span class="mr-3"><strong>Ubicación:</strong> ${p.ubicacion}</span>
                    </div>
                    <div class="text-sm text-gray-600 mt-1">
                        <span class="mr-3"><strong>Precio:</strong> ${p.precio || 0}</span>
                        <span class="mr-3"><strong>Precio Base:</strong> ${p.precio_base || 0}</span>
                        <span><strong>Cantidad:</strong> ${p.cantidad}</span>
                    </div>
                </div>
                <div class="flex gap-2">
                    <input type="number" 
                           value="${p.cantidad}" 
                           min="0.01" 
                           step="0.01"
                           onchange="actualizarCantidad(${index}, this.value)"
                           class="w-20 px-2 py-1 border border-gray-300 rounded">
                    <button onclick="eliminarDelCarrito(${index})" class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `).join('');
}

function actualizarCantidad(index, cantidad) {
    if (parseFloat(cantidad) > 0) {
        carrito[index].cantidad = parseFloat(cantidad);
        actualizarCarrito();
    }
}

function eliminarDelCarrito(index) {
    carrito.splice(index, 1);
    actualizarCarrito();
}

function limpiarCarrito() {
    if (confirm('¿Está seguro de limpiar el carrito?')) {
        carrito = [];
        actualizarCarrito();
        document.getElementById('buscarProducto').value = '';
        document.getElementById('resultadosBusqueda').style.display = 'none';
    }
}

function crearOrden() {
    if (carrito.length === 0) {
        alert('El carrito está vacío');
        return;
    }
    
    const observaciones = prompt('Observaciones (opcional):', '');
    
    const items = carrito.map(p => ({
        inventario_id: p.id,
        cantidad: p.cantidad
    }));
    
    fetch('/warehouse-inventory/tcd/crear-orden', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            items: items,
            observaciones: observaciones
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.estado) {
            alert('Orden creada exitosamente: ' + data.orden.numero_orden);
            carrito = [];
            actualizarCarrito();
            mostrarTab('ordenes');
            cargarOrdenes();
        } else {
            alert('Error: ' + data.msj);
        }
    })
    .catch(err => {
        console.error(err);
        alert('Error al crear la orden');
    });
}

function cargarPasilleros() {
    fetch('/warehouse-inventory/tcd/get-pasilleros')
        .then(res => res.json())
        .then(data => {
            if (data.estado) {
                pasilleros = data.pasilleros;
            }
        })
        .catch(err => console.error(err));
}

function cargarOrdenes() {
    const estado = document.getElementById('filtroEstado')?.value || '';
    const url = estado ? `/warehouse-inventory/tcd/get-ordenes?estado=${estado}` : '/warehouse-inventory/tcd/get-ordenes';
    
    fetch(url)
        .then(res => res.json())
        .then(data => {
            if (data.estado) {
                mostrarOrdenes(data.ordenes);
            } else {
                alert('Error: ' + data.msj);
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error al cargar órdenes');
        });
}

function mostrarOrdenes(ordenes) {
    const contenedor = document.getElementById('listaOrdenes');
    
    if (ordenes.length === 0) {
        contenedor.innerHTML = '<div class="text-center text-gray-400 py-8">No hay órdenes</div>';
        return;
    }
    
    contenedor.innerHTML = ordenes.map(orden => {
        const estadoColors = {
            'borrador': 'bg-gray-100 text-gray-800',
            'asignada': 'bg-blue-100 text-blue-800',
            'en_proceso': 'bg-yellow-100 text-yellow-800',
            'completada': 'bg-green-100 text-green-800',
            'despachada': 'bg-purple-100 text-purple-800'
        };
        
        return `
            <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex justify-between items-start mb-2">
                    <div>
                        <h4 class="font-bold text-gray-800">${orden.numero_orden}</h4>
                        <p class="text-sm text-gray-600">${orden.items.length} producto(s)</p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-semibold ${estadoColors[orden.estado] || 'bg-gray-100'}">
                        ${orden.estado.toUpperCase()}
                    </span>
                </div>
                <div class="flex gap-2 mt-3">
                    ${orden.estado === 'borrador' ? `
                        <button onclick="abrirModalAsignar(${orden.id})" class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg">
                            <i class="fas fa-user-check mr-2"></i>Asignar
                        </button>
                    ` : ''}
                    ${orden.estado === 'completada' ? `
                        <button onclick="confirmarOrden(${orden.id})" class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg">
                            <i class="fas fa-check-circle mr-2"></i>Confirmar y Descontar
                        </button>
                    ` : ''}
                    <button onclick="verEstadoAsignaciones(${orden.id})" class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg">
                        <i class="fas fa-eye mr-2"></i>Ver Estado
                    </button>
                </div>
            </div>
        `;
    }).join('');
}

function abrirModalAsignar(ordenId) {
    ordenActual = ordenId;
    fetch(`/warehouse-inventory/tcd/get-ordenes?estado=borrador`)
        .then(res => res.json())
        .then(data => {
            const orden = data.ordenes.find(o => o.id === ordenId);
            if (orden) {
                mostrarFormularioAsignar(orden);
                document.getElementById('modalAsignar').style.display = 'flex';
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error al cargar la orden');
        });
}

function mostrarFormularioAsignar(orden) {
    const contenedor = document.getElementById('contenidoAsignar');
    
    contenedor.innerHTML = orden.items.map(item => `
        <div class="border border-gray-200 rounded-lg p-4">
            <div class="mb-3">
                <h4 class="font-semibold text-gray-800">${item.descripcion}</h4>
                <p class="text-sm text-gray-600">Cantidad: ${item.cantidad}</p>
            </div>
            <div class="space-y-2" id="asignaciones-item-${item.id}">
                <div class="flex gap-2">
                    <select class="flex-1 px-3 py-2 border border-gray-300 rounded" id="pasillero-${item.id}">
                        <option value="">Seleccionar pasillero...</option>
                        ${pasilleros.map(p => `<option value="${p.id}">${p.nombre}</option>`).join('')}
                    </select>
                    <input type="number" 
                           id="cantidad-${item.id}" 
                           value="${item.cantidad}" 
                           min="0.01" 
                           step="0.01"
                           class="w-32 px-3 py-2 border border-gray-300 rounded"
                           placeholder="Cantidad">
                    <button onclick="agregarAsignacion(${item.id})" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                <div id="lista-asignaciones-${item.id}" class="mt-2 space-y-1">
                </div>
            </div>
        </div>
    `).join('');
}

let asignacionesTemporales = {};

function agregarAsignacion(itemId) {
    const pasilleroId = document.getElementById(`pasillero-${itemId}`).value;
    const cantidad = parseFloat(document.getElementById(`cantidad-${itemId}`).value);
    
    if (!pasilleroId || !cantidad || cantidad <= 0) {
        alert('Seleccione un pasillero e ingrese una cantidad válida');
        return;
    }
    
    if (!asignacionesTemporales[itemId]) {
        asignacionesTemporales[itemId] = [];
    }
    
    const pasillero = pasilleros.find(p => p.id == pasilleroId);
    asignacionesTemporales[itemId].push({
        item_id: itemId,
        pasillero_id: parseInt(pasilleroId),
        pasillero_nombre: pasillero.nombre,
        cantidad: cantidad
    });
    
    actualizarListaAsignaciones(itemId);
}

function actualizarListaAsignaciones(itemId) {
    const lista = document.getElementById(`lista-asignaciones-${itemId}`);
    const asignaciones = asignacionesTemporales[itemId] || [];
    
    lista.innerHTML = asignaciones.map((asig, index) => `
        <div class="flex justify-between items-center bg-gray-50 p-2 rounded">
            <span class="text-sm">${asig.pasillero_nombre} - ${asig.cantidad}</span>
            <button onclick="eliminarAsignacion(${itemId}, ${index})" class="text-red-500 hover:text-red-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `).join('');
}

function eliminarAsignacion(itemId, index) {
    asignacionesTemporales[itemId].splice(index, 1);
    actualizarListaAsignaciones(itemId);
}

function guardarAsignaciones() {
    const todasAsignaciones = [];
    
    for (let itemId in asignacionesTemporales) {
        asignacionesTemporales[itemId].forEach(asig => {
            todasAsignaciones.push({
                item_id: asig.item_id,
                pasillero_id: asig.pasillero_id,
                cantidad: asig.cantidad
            });
        });
    }
    
    if (todasAsignaciones.length === 0) {
        alert('No hay asignaciones para guardar');
        return;
    }
    
    fetch('/warehouse-inventory/tcd/asignar-productos', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            orden_id: ordenActual,
            asignaciones: todasAsignaciones,
            modo: 'manual'
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.estado) {
            alert('Asignaciones guardadas exitosamente');
            cerrarModalAsignar();
            cargarOrdenes();
        } else {
            alert('Error: ' + data.msj);
        }
    })
    .catch(err => {
        console.error(err);
        alert('Error al guardar asignaciones');
    });
}

function cerrarModalAsignar() {
    document.getElementById('modalAsignar').style.display = 'none';
    asignacionesTemporales = {};
    ordenActual = null;
}

function verEstadoAsignaciones(ordenId) {
    fetch(`/warehouse-inventory/tcd/get-asignaciones-por-orden?orden_id=${ordenId}`)
        .then(res => res.json())
        .then(data => {
            if (data.estado) {
                mostrarEstadoAsignaciones(data);
                document.getElementById('modalEstado').style.display = 'flex';
            } else {
                alert('Error: ' + data.msj);
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error al cargar el estado');
        });
}

function mostrarEstadoAsignaciones(data) {
    const contenedor = document.getElementById('contenidoEstado');
    const orden = data.orden;
    
    contenedor.innerHTML = `
        <div class="mb-4">
            <h4 class="font-bold text-gray-800">${orden.numero_orden}</h4>
            <p class="text-sm text-gray-600">Estado: ${orden.estado}</p>
        </div>
        ${data.asignaciones_por_pasillero.map(pasillero => `
            <div class="border border-gray-200 rounded-lg p-4 mb-3">
                <h5 class="font-semibold text-gray-700 mb-2">${pasillero.pasillero_nombre}</h5>
                <div class="text-sm text-gray-600 mb-2">
                    <span class="mr-3">Total: ${pasillero.total_asignaciones}</span>
                    <span class="mr-3 text-green-600">Completadas: ${pasillero.completadas}</span>
                    <span class="mr-3 text-yellow-600">En Proceso: ${pasillero.en_proceso}</span>
                    <span class="text-gray-600">Pendientes: ${pasillero.pendientes}</span>
                </div>
                <div class="space-y-2">
                    ${pasillero.asignaciones.map(asig => {
                        const porcentaje = (asig.cantidad_procesada / asig.cantidad) * 100;
                        return `
                            <div class="bg-gray-50 p-2 rounded">
                                <div class="flex justify-between items-center mb-1">
                                    <span class="text-sm font-semibold">${asig.orden_item.descripcion}</span>
                                    <span class="text-xs text-gray-600">${asig.cantidad_procesada} / ${asig.cantidad}</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-500 h-2 rounded-full" style="width: ${porcentaje}%"></div>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">Estado: ${asig.estado}</div>
                            </div>
                        `;
                    }).join('')}
                </div>
            </div>
        `).join('')}
        ${data.todas_listas ? `
            <div class="mt-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                <p class="text-green-800 font-semibold">✓ Todas las asignaciones están completadas</p>
                <button onclick="confirmarOrden(${orden.id})" class="mt-2 px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg">
                    Confirmar y Descontar del Inventario
                </button>
            </div>
        ` : ''}
    `;
}

function cerrarModalEstado() {
    document.getElementById('modalEstado').style.display = 'none';
}

function confirmarOrden(ordenId) {
    if (!confirm('¿Está seguro de confirmar esta orden? Se descontarán los productos del inventario y se bloquearán hasta el despacho.')) {
        return;
    }
    
    fetch('/warehouse-inventory/tcd/confirmar-orden', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            orden_id: ordenId
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.estado) {
            alert('Orden confirmada. Productos descontados. Código de ticket: ' + data.ticket_codigo_barras);
            cerrarModalEstado();
            cargarOrdenes();
        } else {
            alert('Error: ' + data.msj);
        }
    })
    .catch(err => {
        console.error(err);
        alert('Error al confirmar la orden');
    });
}
</script>
@endsection

