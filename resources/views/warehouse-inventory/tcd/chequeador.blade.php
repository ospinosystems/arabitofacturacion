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
                    
                    <!-- Asignación Masiva -->
                    <div id="seccionAsignacionMasiva" class="mb-6 p-4 bg-blue-50 border-l-4 border-blue-500 rounded-lg" style="display: none;">
                        <h4 class="font-semibold text-gray-700 mb-3">
                            <i class="fas fa-users mr-2 text-blue-600"></i>Asignación Rápida (Todos los Productos)
                        </h4>
                        <div class="flex gap-2">
                            <select id="pasilleroMasivo" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-400">
                                <option value="">Seleccionar pasillero...</option>
                            </select>
                            <button onclick="asignarTodosLosProductos()" class="px-6 py-2 bg-blue-500 hover:bg-blue-600 text-white font-semibold rounded-lg transition">
                                <i class="fas fa-check-double mr-2"></i>Asignar Todos
                            </button>
                        </div>
                        <p class="text-xs text-gray-600 mt-2">
                            <i class="fas fa-info-circle mr-1"></i>Asignará todos los productos de la orden a un solo pasillero
                        </p>
                        <div id="mensajeAsignacionMasiva" class="mt-3 p-2 bg-green-100 border border-green-300 rounded text-sm text-green-800" style="display: none;">
                        </div>
                    </div>
                    
                    <div id="seccionAsignacionIndividual" class="mb-4 border-t border-gray-300 pt-4" style="display: none;">
                        <h4 class="font-semibold text-gray-700 mb-3">
                            <i class="fas fa-list mr-2 text-gray-600"></i>Asignación Individual
                        </h4>
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

    <!-- Modal: Reversar Asignación -->
    <div id="modalReversar" class="fixed inset-0 bg-black bg-opacity-50 z-50" style="display: none;">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-bold text-gray-700">Reversar Asignación</h3>
                        <button onclick="cerrarModalReversar()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-2xl"></i>
                        </button>
                    </div>
                    <div id="infoReversar" class="mb-4 p-3 bg-gray-50 rounded-lg">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Cantidad a Reversar:</label>
                        <input type="text" 
                               id="inputCantidadReversar" 
                               inputmode="decimal"
                               class="w-full px-4 py-3 text-lg font-mono border-2 border-red-400 rounded-lg focus:ring-2 focus:ring-red-500 text-gray-900"
                               placeholder="Ingrese cantidad"
                               autofocus
                               oninput="validarCantidadReversar(this)"
                               onkeypress="if(event.key === 'Enter') confirmarReversar()">
                        <div id="mensajeCantidadReversar" class="mt-2 text-xs"></div>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="cerrarModalReversar()" class="flex-1 px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white font-semibold rounded-lg transition">
                            Cancelar
                        </button>
                        <button onclick="confirmarReversar()" type="button" class="flex-1 px-4 py-2 bg-red-500 hover:bg-red-600 text-white font-semibold rounded-lg transition">
                            <i class="fas fa-undo mr-2"></i>Reversar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Ingresar Cantidad -->
    <!-- Modal: Transferir Orden a Sucursal -->
    <div id="modalTransferir" class="fixed inset-0 bg-black bg-opacity-50 z-50" style="display: none;">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full" onclick="event.stopPropagation()">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-bold text-gray-800">Transferir Orden a Sucursal</h3>
                        <button onclick="cerrarModalTransferir()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div id="infoTransferir" class="mb-4 text-sm text-gray-600"></div>
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Seleccionar Sucursal:</label>
                        <select id="selectSucursal" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <option value="">Cargando sucursales...</option>
                        </select>
                        <div id="mensajeTransferir" class="mt-2 text-xs"></div>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="transferirOrden()" class="flex-1 px-4 py-2 bg-purple-500 hover:bg-purple-600 text-white font-semibold rounded-lg transition">
                            <i class="fas fa-paper-plane mr-2"></i>Transferir
                        </button>
                        <button onclick="cerrarModalTransferir()" class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white font-semibold rounded-lg transition">
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="modalCantidad" class="fixed inset-0 bg-black bg-opacity-50 z-50" style="display: none;">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-bold text-gray-700">Ingresar Cantidad</h3>
                        <button onclick="cerrarModalCantidad()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-2xl"></i>
                        </button>
                    </div>
                    <div id="infoProductoCantidad" class="mb-4 p-3 bg-gray-50 rounded-lg">
                        <div class="font-semibold text-gray-800" id="productoCantidadNombre"></div>
                        <div class="text-sm text-gray-600 mt-1" id="productoCantidadDetalles"></div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Cantidad:</label>
                        <input type="text" 
                               id="inputCantidadModal" 
                               inputmode="decimal"
                               class="w-full px-4 py-3 text-lg font-mono border-2 border-blue-400 rounded-lg focus:ring-2 focus:ring-blue-500 text-gray-900"
                               placeholder="Ingrese cantidad"
                               autofocus
                               oninput="validarCantidadModal(this)"
                               onkeypress="if(event.key === 'Enter') confirmarCantidad()">
                        <div id="mensajeCantidadModal" class="mt-2 text-xs"></div>
                        <div id="infoStockModal" class="mt-2 text-xs text-gray-600"></div>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="cerrarModalCantidad()" class="flex-1 px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white font-semibold rounded-lg transition">
                            Cancelar
                        </button>
                        <button onclick="confirmarCantidad()" class="flex-1 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white font-semibold rounded-lg transition">
                            <i class="fas fa-check mr-2"></i>Confirmar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Observaciones -->
    <div id="modalObservaciones" class="fixed inset-0 bg-black bg-opacity-50 z-50" style="display: none;">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-bold text-gray-700">Observaciones</h3>
                        <button onclick="cerrarModalObservaciones()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-2xl"></i>
                        </button>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Observaciones (opcional):</label>
                        <textarea id="inputObservaciones" 
                                  class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-gray-900"
                                  rows="4"
                                  placeholder="Ingrese observaciones sobre la orden..."></textarea>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="cerrarModalObservaciones()" class="flex-1 px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white font-semibold rounded-lg transition">
                            Cancelar
                        </button>
                        <button onclick="confirmarObservaciones()" class="flex-1 px-4 py-2 bg-green-500 hover:bg-green-600 text-white font-semibold rounded-lg transition">
                            <i class="fas fa-check mr-2"></i>Crear Orden
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
let productoSeleccionadoCantidad = null;
let esActualizacionCantidad = false;

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
    
    // Cerrar modales con Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            cerrarModalCantidad();
            cerrarModalObservaciones();
        }
    });
    
    // Cerrar modales al hacer clic fuera
    document.getElementById('modalCantidad').addEventListener('click', function(e) {
        if (e.target === this) {
            cerrarModalCantidad();
        }
    });
    
    document.getElementById('modalObservaciones').addEventListener('click', function(e) {
        if (e.target === this) {
            cerrarModalObservaciones();
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
        .then(res => {
            // Verificar si la respuesta es OK
            if (!res.ok) {
                throw new Error(`HTTP error! status: ${res.status}`);
            }
            // Intentar parsear como JSON
            return res.json();
        })
        .then(data => {
            console.log('Respuesta del servidor:', data); // Debug
            if (data.estado === true || data.estado === 1) {
                mostrarResultadosBusqueda(data.productos || []);
            } else {
                alert(data.msj || 'Error al buscar productos');
            }
        })
        .catch(err => {
            console.error('Error completo:', err); // Debug
            alert('Error al buscar productos: ' + err.message);
        });
}

function mostrarResultadosBusqueda(productos) {
    const contenedor = document.getElementById('resultadosBusqueda');
    contenedor.style.display = 'block';
    
    if (productos.length === 0) {
        contenedor.innerHTML = '<div class="text-center text-gray-400 py-4">No se encontraron productos</div>';
        return;
    }
    
    contenedor.innerHTML = productos.map(p => {
        const stockDisponible = p.stock_disponible || 0;
        const tieneStock = stockDisponible > 0;
        const stockClass = tieneStock ? 'text-green-600' : 'text-red-600';
        const stockIcon = tieneStock ? 'fa-check-circle' : 'fa-exclamation-triangle';
        
        return `
        <div class="border border-gray-200 rounded-lg p-3 hover:bg-gray-50 ${tieneStock ? 'cursor-pointer' : 'opacity-60 cursor-not-allowed'}" ${tieneStock ? `onclick="agregarAlCarrito(${JSON.stringify(p).replace(/"/g, '&quot;')})"` : ''}>
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
                        <span class="mr-3"><strong>Precio Base:</strong> ${p.precio_base || 0}</span>
                        <span class="${stockClass} font-bold">
                            <i class="fas ${stockIcon} mr-1"></i>
                            Stock Disponible: ${stockDisponible.toFixed(4)}
                        </span>
                    </div>
                </div>
                <button class="ml-2 px-3 py-1 ${tieneStock ? 'bg-blue-500 hover:bg-blue-600' : 'bg-gray-400 cursor-not-allowed'} text-white rounded" ${!tieneStock ? 'disabled' : ''}>
                    <i class="fas fa-plus"></i>
                </button>
            </div>
        </div>
    `;
    }).join('');
}

function agregarAlCarrito(producto) {
    // Verificar si ya está en el carrito
    const existe = carrito.find(p => p.id === producto.id);
    if (existe) {
        if (confirm('El producto ya está en el carrito. ¿Desea actualizar la cantidad?')) {
            esActualizacionCantidad = true;
            productoSeleccionadoCantidad = { producto: existe, esActualizacion: true };
            abrirModalCantidad(existe);
        }
        return;
    }
    
    esActualizacionCantidad = false;
    productoSeleccionadoCantidad = { producto: producto, esActualizacion: false };
    abrirModalCantidad(producto);
}

function abrirModalCantidad(producto) {
    const stockDisponible = producto.stock_disponible || 0;
    const stockClass = stockDisponible > 0 ? 'text-green-600' : 'text-red-600';
    const stockIcon = stockDisponible > 0 ? 'fa-check-circle' : 'fa-exclamation-triangle';
    
    document.getElementById('productoCantidadNombre').textContent = producto.descripcion;
    document.getElementById('productoCantidadDetalles').innerHTML = `
        <div><strong>Código Barras:</strong> ${producto.codigo_barras || 'N/A'}</div>
        <div><strong>Ubicación:</strong> ${producto.ubicacion || 'N/A'}</div>
        <div><strong>Precio:</strong> ${producto.precio || 0} | <strong>Precio Base:</strong> ${producto.precio_base || 0}</div>
        <div class="mt-2 ${stockClass} font-bold">
            <i class="fas ${stockIcon} mr-1"></i>
            <strong>Stock Disponible:</strong> ${stockDisponible.toFixed(4)}
        </div>
    `;
    const input = document.getElementById('inputCantidadModal');
    const cantidadSugerida = producto.cantidad || (stockDisponible > 0 ? Math.min(1, stockDisponible) : '');
    input.value = cantidadSugerida;
    input.max = stockDisponible;
    input.focus();
    document.getElementById('mensajeCantidadModal').innerHTML = '';
    document.getElementById('modalCantidad').style.display = 'flex';
}

function cerrarModalCantidad() {
    document.getElementById('modalCantidad').style.display = 'none';
    document.getElementById('inputCantidadModal').value = '';
    productoSeleccionadoCantidad = null;
    esActualizacionCantidad = false;
}

function validarCantidadModal(input) {
    let valor = input.value;
    
    // Remover cualquier carácter que no sea número o punto decimal
    valor = valor.replace(/[^0-9.]/g, '');
    
    // Evitar múltiples puntos decimales
    const partes = valor.split('.');
    if (partes.length > 2) {
        valor = partes[0] + '.' + partes.slice(1).join('');
    }
    
    // Actualizar el valor del input
    if (input.value !== valor) {
        input.value = valor;
        const mensaje = document.getElementById('mensajeCantidadModal');
        if (mensaje) {
            mensaje.innerHTML = '<span class="text-red-600 text-xs"><i class="fas fa-exclamation-circle mr-1"></i>Solo se permiten números y decimales</span>';
            setTimeout(() => {
                mensaje.innerHTML = '';
            }, 2000);
        }
    } else {
        const mensaje = document.getElementById('mensajeCantidadModal');
        if (mensaje && mensaje.innerHTML.includes('Solo se permiten')) {
            mensaje.innerHTML = '';
        }
    }
    
    // Validar contra stock disponible
    if (productoSeleccionadoCantidad) {
        const producto = productoSeleccionadoCantidad.producto;
        const stockDisponible = producto.stock_disponible || 0;
        const cantidad = parseFloat(valor);
        const infoStock = document.getElementById('infoStockModal');
        
        if (infoStock && !isNaN(cantidad) && cantidad > 0) {
            if (cantidad > stockDisponible) {
                infoStock.innerHTML = `<span class="text-red-600"><i class="fas fa-exclamation-triangle mr-1"></i>Stock insuficiente. Disponible: ${stockDisponible.toFixed(4)}</span>`;
            } else {
                const disponible = stockDisponible - cantidad;
                infoStock.innerHTML = `<span class="text-green-600">Quedarán disponibles: ${disponible.toFixed(4)}</span>`;
            }
        } else if (infoStock) {
            infoStock.innerHTML = '';
        }
    }
}

function confirmarCantidad() {
    const input = document.getElementById('inputCantidadModal');
    const valorCantidad = input.value.trim();
    
    if (!valorCantidad) {
        alert('Ingrese una cantidad');
        input.focus();
        return;
    }
    
    const cantidad = parseFloat(valorCantidad);
    if (isNaN(cantidad) || cantidad <= 0) {
        alert('Ingrese una cantidad válida (solo números y decimales)');
        input.focus();
        return;
    }
    
    if (productoSeleccionadoCantidad) {
        const producto = productoSeleccionadoCantidad.producto;
        const stockDisponible = producto.stock_disponible || 0;
        
        // Validar stock disponible
        if (cantidad > stockDisponible) {
            alert(`Stock insuficiente. Disponible: ${stockDisponible.toFixed(4)}, Solicitado: ${cantidad.toFixed(4)}`);
            input.focus();
            return;
        }
        
        if (productoSeleccionadoCantidad.esActualizacion) {
            // Actualizar cantidad existente
            productoSeleccionadoCantidad.producto.cantidad = cantidad;
            actualizarCarrito();
        } else {
            // Agregar nuevo producto
            productoSeleccionadoCantidad.producto.cantidad = cantidad;
            carrito.push(productoSeleccionadoCantidad.producto);
            actualizarCarrito();
        }
    }
    
    cerrarModalCantidad();
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
    contenedor.innerHTML = carrito.map((p, index) => {
        const stockDisponible = p.stock_disponible || 0;
        const cantidad = p.cantidad || 0;
        const tieneStockSuficiente = stockDisponible >= cantidad;
        const stockClass = tieneStockSuficiente ? 'text-green-600' : 'text-red-600';
        const stockIcon = tieneStockSuficiente ? 'fa-check-circle' : 'fa-exclamation-triangle';
        
        return `
        <div class="border border-gray-200 rounded-lg p-3 ${!tieneStockSuficiente ? 'border-red-300 bg-red-50' : ''}">
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
                        <span><strong>Cantidad:</strong> ${cantidad.toFixed(4)}</span>
                    </div>
                    <div class="text-sm ${stockClass} mt-1 font-semibold">
                        <i class="fas ${stockIcon} mr-1"></i>
                        Stock Disponible: ${stockDisponible.toFixed(4)}
                        ${!tieneStockSuficiente ? ` | <span class="text-red-600">Faltan: ${(cantidad - stockDisponible).toFixed(4)}</span>` : ''}
                    </div>
                </div>
                <div class="flex gap-2">
                    <input type="number" 
                           value="${cantidad}" 
                           min="0.01" 
                           step="0.01"
                           max="${stockDisponible}"
                           onchange="actualizarCantidad(${index}, this.value)"
                           class="w-20 px-2 py-1 border border-gray-300 rounded">
                    <button onclick="eliminarDelCarrito(${index})" class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    }).join('');
}

function actualizarCantidad(index, cantidad) {
    const cantidadNum = parseFloat(cantidad);
    if (cantidadNum > 0) {
        const producto = carrito[index];
        const stockDisponible = producto.stock_disponible || 0;
        
        if (cantidadNum > stockDisponible) {
            alert(`Stock insuficiente. Disponible: ${stockDisponible.toFixed(4)}, Solicitado: ${cantidadNum.toFixed(4)}`);
            // Restaurar cantidad anterior
            actualizarCarrito();
            return;
        }
        
        carrito[index].cantidad = cantidadNum;
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
    
    // Abrir modal de observaciones
    document.getElementById('inputObservaciones').value = '';
    document.getElementById('modalObservaciones').style.display = 'flex';
}

function cerrarModalObservaciones() {
    document.getElementById('modalObservaciones').style.display = 'none';
    document.getElementById('inputObservaciones').value = '';
}

function confirmarObservaciones() {
    const observaciones = document.getElementById('inputObservaciones').value.trim();
    
    // Validar stock antes de crear la orden
    const productosSinStock = carrito.filter(p => {
        const stockDisponible = p.stock_disponible || 0;
        return stockDisponible < p.cantidad;
    });
    
    if (productosSinStock.length > 0) {
        const productos = productosSinStock.map(p => p.descripcion).join(', ');
        alert(`Los siguientes productos no tienen stock suficiente:\n${productos}\n\nPor favor, ajuste las cantidades antes de crear la orden.`);
        return;
    }
    
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
            cerrarModalObservaciones();
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
                    ${orden.estado === 'completada' && !orden.tiene_ticket_despacho ? `
                        <button onclick="confirmarOrden(${orden.id})" class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg">
                            <i class="fas fa-check-circle mr-2"></i>Confirmar y Descontar
                        </button>
                    ` : ''}
                    ${orden.tiene_ticket_despacho && !orden.fue_transferida ? `
                        <button onclick="abrirModalTransferir(${orden.id})" class="px-4 py-2 bg-purple-500 hover:bg-purple-600 text-white rounded-lg">
                            <i class="fas fa-truck mr-2"></i>Transferir a Sucursal
                        </button>
                    ` : ''}
                    ${orden.fue_transferida ? `
                        <span class="px-4 py-2 bg-green-500 text-white rounded-lg">
                            <i class="fas fa-check-circle mr-2"></i>Transferida
                        </span>
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

let ordenItemsGlobal = [];

function mostrarFormularioAsignar(orden) {
    ordenItemsGlobal = orden.items;
    
    // Limpiar mensaje de asignación masiva
    const mensajeMasivo = document.getElementById('mensajeAsignacionMasiva');
    if (mensajeMasivo) {
        mensajeMasivo.style.display = 'none';
        mensajeMasivo.innerHTML = '';
    }
    
    // Mostrar secciones
    document.getElementById('seccionAsignacionMasiva').style.display = 'block';
    document.getElementById('seccionAsignacionIndividual').style.display = 'block';
    
    // Llenar select de pasilleros masivo
    const selectMasivo = document.getElementById('pasilleroMasivo');
    selectMasivo.innerHTML = '<option value="">Seleccionar pasillero...</option>';
    pasilleros.forEach(p => {
        const option = document.createElement('option');
        option.value = p.id;
        option.textContent = p.nombre;
        selectMasivo.appendChild(option);
    });
    
    const contenedor = document.getElementById('contenidoAsignar');
    
    contenedor.innerHTML = orden.items.map(item => {
        const cantidadAsignada = obtenerCantidadAsignada(item.id);
        const cantidadDisponible = (item.cantidad - cantidadAsignada).toFixed(4);
        
        return `
        <div class="border border-gray-200 rounded-lg p-4">
            <div class="mb-3">
                <h4 class="font-semibold text-gray-800">${item.descripcion}</h4>
                <div class="flex items-center gap-4 text-sm text-gray-600 mt-1">
                    <span><strong>Total:</strong> ${item.cantidad}</span>
                    <span class="text-blue-600"><strong>Asignado:</strong> ${cantidadAsignada.toFixed(4)}</span>
                    <span class="text-orange-600"><strong>Disponible:</strong> ${cantidadDisponible}</span>
                </div>
            </div>
            <div class="space-y-2" id="asignaciones-item-${item.id}">
                <div class="flex gap-2">
                    <select class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-400" id="pasillero-${item.id}">
                        <option value="">Seleccionar pasillero...</option>
                        ${pasilleros.map(p => `<option value="${p.id}">${p.nombre}</option>`).join('')}
                    </select>
                    <input type="number" 
                           id="cantidad-${item.id}" 
                           value="${cantidadDisponible}" 
                           min="0.0001" 
                           step="0.0001"
                           max="${cantidadDisponible}"
                           class="w-32 px-3 py-2 border border-gray-300 rounded-lg"
                           placeholder="Cantidad"
                           onchange="validarCantidadAsignacion(${item.id}, this.value, ${item.cantidad})">
                    <button onclick="agregarAsignacion(${item.id})" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition">
                        <i class="fas fa-plus mr-1"></i>Agregar
                    </button>
                </div>
                <div id="lista-asignaciones-${item.id}" class="mt-2 space-y-1">
                </div>
            </div>
        </div>
    `;
    }).join('');
    
    // Limpiar asignaciones temporales al cargar
    asignacionesTemporales = {};
}

function obtenerCantidadAsignada(itemId) {
    if (!asignacionesTemporales[itemId]) {
        return 0;
    }
    return asignacionesTemporales[itemId].reduce((sum, asig) => sum + asig.cantidad, 0);
}

function validarCantidadAsignacion(itemId, cantidad, cantidadTotal) {
    const cantidadAsignada = obtenerCantidadAsignada(itemId);
    const cantidadDisponible = cantidadTotal - cantidadAsignada;
    const input = document.getElementById(`cantidad-${itemId}`);
    
    if (parseFloat(cantidad) > cantidadDisponible) {
        input.value = cantidadDisponible.toFixed(4);
        alert(`La cantidad no puede exceder lo disponible: ${cantidadDisponible.toFixed(4)}`);
    }
}

function asignarTodosLosProductos() {
    const pasilleroId = document.getElementById('pasilleroMasivo').value;
    
    if (!pasilleroId) {
        alert('Seleccione un pasillero');
        return;
    }
    
    if (pasilleros.length === 0) {
        alert('No hay pasilleros disponibles');
        return;
    }
    
    const pasillero = pasilleros.find(p => p.id == pasilleroId);
    
    if (!confirm(`¿Desea asignar TODOS los productos de esta orden a ${pasillero.nombre}?`)) {
        return;
    }
    
    // Limpiar asignaciones temporales existentes
    asignacionesTemporales = {};
    
    // Asignar todos los productos al pasillero seleccionado
    ordenItemsGlobal.forEach(item => {
        if (!asignacionesTemporales[item.id]) {
            asignacionesTemporales[item.id] = [];
        }
        
        asignacionesTemporales[item.id].push({
            item_id: item.id,
            pasillero_id: parseInt(pasilleroId),
            pasillero_nombre: pasillero.nombre,
            cantidad: item.cantidad
        });
    });
    
    // Mostrar mensaje de confirmación en la sección de asignación masiva
    const mensajeMasivo = document.getElementById('mensajeAsignacionMasiva');
    if (mensajeMasivo) {
        mensajeMasivo.innerHTML = `
            <i class="fas fa-check-circle mr-2"></i>
            <strong>Todos los productos asignados a:</strong> ${pasillero.nombre}
        `;
        mensajeMasivo.style.display = 'block';
    }
    
    // Actualizar la visualización de cada item después de asegurar que las asignaciones están guardadas
    // Usar requestAnimationFrame para asegurar que el DOM esté listo
    requestAnimationFrame(() => {
        ordenItemsGlobal.forEach(item => {
            // Actualizar la lista de asignaciones
            actualizarListaAsignaciones(item.id);
            
            // Actualizar el input de cantidad (debe estar vacío y deshabilitado)
            const cantidadInput = document.getElementById(`cantidad-${item.id}`);
            if (cantidadInput) {
                cantidadInput.value = '';
                cantidadInput.disabled = true;
                cantidadInput.max = 0;
            }
            
            // Limpiar selección de pasillero individual
            const pasilleroSelect = document.getElementById(`pasillero-${item.id}`);
            if (pasilleroSelect) {
                pasilleroSelect.value = '';
            }
        });
        
        // Forzar una segunda actualización después de un pequeño delay para asegurar que el DOM se actualizó completamente
        setTimeout(() => {
            ordenItemsGlobal.forEach(item => {
                actualizarListaAsignaciones(item.id);
            });
            mostrarNotificacion('Todos los productos han sido asignados a ' + pasillero.nombre, 'success');
        }, 150);
    });
}

let asignacionesTemporales = {};

function agregarAsignacion(itemId) {
    const pasilleroId = document.getElementById(`pasillero-${itemId}`).value;
    const cantidad = parseFloat(document.getElementById(`cantidad-${itemId}`).value);
    
    if (!pasilleroId || !cantidad || cantidad <= 0) {
        alert('Seleccione un pasillero e ingrese una cantidad válida');
        return;
    }
    
    // Obtener el item para validar cantidad total
    const item = ordenItemsGlobal.find(i => i.id == itemId);
    if (!item) {
        alert('Error: Producto no encontrado');
        return;
    }
    
    // Validar que no se exceda la cantidad disponible
    const cantidadAsignada = obtenerCantidadAsignada(itemId);
    const cantidadDisponible = item.cantidad - cantidadAsignada;
    
    if (cantidad > cantidadDisponible) {
        alert(`La cantidad excede lo disponible. Disponible: ${cantidadDisponible.toFixed(4)}`);
        document.getElementById(`cantidad-${itemId}`).value = cantidadDisponible.toFixed(4);
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
    
    // Actualizar cantidad disponible en el input
    const nuevaCantidadDisponible = item.cantidad - obtenerCantidadAsignada(itemId);
    document.getElementById(`cantidad-${itemId}`).value = nuevaCantidadDisponible > 0 ? nuevaCantidadDisponible.toFixed(4) : '';
    document.getElementById(`cantidad-${itemId}`).max = nuevaCantidadDisponible;
    
    // Limpiar selección de pasillero
    document.getElementById(`pasillero-${itemId}`).value = '';
}

function actualizarListaAsignaciones(itemId) {
    const lista = document.getElementById(`lista-asignaciones-${itemId}`);
    if (!lista) {
        console.error('No se encontró el elemento lista-asignaciones-' + itemId);
        // Intentar encontrar el elemento después de un pequeño delay
        setTimeout(() => {
            const listaRetry = document.getElementById(`lista-asignaciones-${itemId}`);
            if (listaRetry) {
                actualizarListaAsignaciones(itemId);
            }
        }, 100);
        return;
    }
    
    const asignaciones = asignacionesTemporales[itemId] || [];
    const item = ordenItemsGlobal.find(i => i.id == itemId);
    
    if (!item) {
        console.error('No se encontró el item con id: ' + itemId);
        return;
    }
    
    const cantidadAsignada = obtenerCantidadAsignada(itemId);
    const cantidadDisponible = item.cantidad - cantidadAsignada;
    
    // Actualizar información de cantidad en el encabezado del item
    // Buscar el contenedor del item de forma más robusta
    let itemContainer = lista.parentElement;
    while (itemContainer && !itemContainer.classList.contains('border')) {
        itemContainer = itemContainer.parentElement;
    }
    
    if (itemContainer) {
        const infoContainer = itemContainer.querySelector('.flex.items-center.gap-4');
        if (infoContainer) {
            infoContainer.innerHTML = `
                <span><strong>Total:</strong> ${item.cantidad}</span>
                <span class="text-blue-600"><strong>Asignado:</strong> ${cantidadAsignada.toFixed(4)}</span>
                <span class="text-orange-600"><strong>Disponible:</strong> ${cantidadDisponible.toFixed(4)}</span>
            `;
        }
    }
    
    // Actualizar la lista de asignaciones
    if (asignaciones.length === 0) {
        lista.innerHTML = '<p class="text-xs text-gray-400 italic">No hay asignaciones</p>';
    } else {
        // Verificar que todas las asignaciones tengan el nombre del pasillero
        const asignacionesConNombre = asignaciones.map(asig => {
            if (!asig.pasillero_nombre && asig.pasillero_id) {
                // Buscar el nombre del pasillero si no está presente
                const pasillero = pasilleros.find(p => p.id == asig.pasillero_id);
                if (pasillero) {
                    asig.pasillero_nombre = pasillero.nombre;
                }
            }
            return asig;
        });
        
        lista.innerHTML = asignacionesConNombre.map((asig, index) => {
            const pasilleroNombre = asig.pasillero_nombre || 'Pasillero desconocido';
            return `
            <div class="flex justify-between items-center bg-gray-50 p-2 rounded-lg border border-gray-200">
                <div class="flex-1">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-user text-blue-600"></i>
                        <span class="text-sm font-semibold text-gray-800">${pasilleroNombre}</span>
                    </div>
                    <div class="text-xs text-gray-600 mt-1 ml-6">
                        Cantidad: <span class="font-semibold">${asig.cantidad.toFixed(4)}</span>
                    </div>
                </div>
                <button onclick="eliminarAsignacion(${itemId}, ${index})" class="ml-2 px-2 py-1 text-red-500 hover:text-red-700 hover:bg-red-50 rounded transition" title="Eliminar asignación">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        }).join('');
    }
    
    // Actualizar el input de cantidad disponible
    const cantidadInput = document.getElementById(`cantidad-${itemId}`);
    if (cantidadInput) {
        cantidadInput.max = cantidadDisponible;
        if (cantidadDisponible <= 0) {
            cantidadInput.value = '';
            cantidadInput.disabled = true;
        } else {
            cantidadInput.disabled = false;
        }
    }
}

function eliminarAsignacion(itemId, index) {
    asignacionesTemporales[itemId].splice(index, 1);
    actualizarListaAsignaciones(itemId);
    
    // Actualizar el input de cantidad disponible
    const item = ordenItemsGlobal.find(i => i.id == itemId);
    if (item) {
        const cantidadDisponible = item.cantidad - obtenerCantidadAsignada(itemId);
        const cantidadInput = document.getElementById(`cantidad-${itemId}`);
        if (cantidadInput) {
            cantidadInput.value = cantidadDisponible > 0 ? cantidadDisponible.toFixed(4) : '';
            cantidadInput.max = cantidadDisponible;
            cantidadInput.disabled = cantidadDisponible <= 0;
        }
    }
}

function mostrarNotificacion(mensaje, tipo = 'info') {
    const tipos = {
        success: { bg: 'bg-green-500', icon: 'fa-check-circle' },
        error: { bg: 'bg-red-500', icon: 'fa-exclamation-circle' },
        warning: { bg: 'bg-yellow-500', icon: 'fa-exclamation-triangle' },
        info: { bg: 'bg-blue-500', icon: 'fa-info-circle' }
    };
    
    const tipoConfig = tipos[tipo] || tipos.info;
    const notificacion = document.createElement('div');
    notificacion.className = `fixed bottom-4 right-4 ${tipoConfig.bg} text-white px-6 py-4 rounded-lg shadow-lg flex items-center space-x-3 z-50`;
    notificacion.innerHTML = `
        <i class="fas ${tipoConfig.icon} text-xl"></i>
        <span>${mensaje}</span>
        <button onclick="this.parentElement.remove()" class="text-white hover:text-gray-200 ml-4">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    document.body.appendChild(notificacion);
    
    setTimeout(() => {
        notificacion.style.opacity = '0';
        notificacion.style.transition = 'opacity 0.3s';
        setTimeout(() => notificacion.remove(), 300);
    }, 3000);
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
    ordenItemsGlobal = [];
    document.getElementById('pasilleroMasivo').value = '';
    document.getElementById('seccionAsignacionMasiva').style.display = 'none';
    document.getElementById('seccionAsignacionIndividual').style.display = 'none';
    
    // Limpiar mensaje de asignación masiva
    const mensajeMasivo = document.getElementById('mensajeAsignacionMasiva');
    if (mensajeMasivo) {
        mensajeMasivo.style.display = 'none';
        mensajeMasivo.innerHTML = '';
    }
}

function verEstadoAsignaciones(ordenId) {
    // Guardar el ordenId para poder recargar después de reversar
    const modalEstado = document.getElementById('modalEstado');
    modalEstado.setAttribute('data-orden-id', ordenId);
    
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
                        const cantidadPendiente = asig.cantidad - asig.cantidad_procesada;
                        const estadoColors = {
                            'pendiente': 'bg-gray-100 text-gray-800',
                            'en_proceso': 'bg-yellow-100 text-yellow-800',
                            'completada': 'bg-green-100 text-green-800'
                        };
                        const ubicacion = asig.warehouse ? asig.warehouse.codigo : (asig.warehouse_codigo || 'Sin ubicación');
                        
                        return `
                            <div class="bg-gray-50 p-3 rounded border border-gray-200">
                                <div class="mb-2">
                                    <div class="flex justify-between items-start mb-1">
                                        <div class="flex-1">
                                            <span class="text-sm font-semibold text-gray-800">${asig.orden_item.descripcion}</span>
                                        </div>
                                        <span class="px-2 py-0.5 rounded text-xs font-semibold ${estadoColors[asig.estado] || 'bg-gray-100'}">
                                            ${asig.estado}
                                        </span>
                                    </div>
                                    <div class="text-xs text-gray-600 mt-1 space-y-0.5">
                                        <div><strong>Código Barras:</strong> ${asig.orden_item.codigo_barras || 'N/A'}</div>
                                        <div><strong>Código Proveedor:</strong> ${asig.orden_item.codigo_proveedor || 'N/A'}</div>
                                        <div><strong>Ubicación:</strong> <span class="font-semibold text-blue-600">${ubicacion}</span></div>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="text-xs text-gray-600">Progreso:</span>
                                        <span class="text-xs font-semibold text-gray-700">${asig.cantidad_procesada.toFixed(4)} / ${asig.cantidad.toFixed(4)}</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-blue-500 h-2 rounded-full transition-all" style="width: ${porcentaje}%"></div>
                                    </div>
                                    ${cantidadPendiente > 0 ? `
                                        <div class="text-xs text-orange-600 mt-1">
                                            Pendiente: ${cantidadPendiente.toFixed(4)}
                                        </div>
                                    ` : ''}
                                </div>
                                ${asig.orden_item.precio_base || asig.orden_item.precio_venta ? `
                                    <div class="text-xs text-gray-500 mt-1 pt-1 border-t border-gray-200">
                                        ${asig.orden_item.precio_base ? `<span class="mr-3"><strong>Precio Base:</strong> ${asig.orden_item.precio_base.toFixed(2)}</span>` : ''}
                                        ${asig.orden_item.precio_venta ? `<span><strong>Precio Venta:</strong> ${asig.orden_item.precio_venta.toFixed(2)}</span>` : ''}
                                    </div>
                                ` : ''}
                                ${asig.cantidad_procesada > 0 && !data.tiene_ticket_despacho ? `
                                    <div class="mt-2 pt-2 border-t border-gray-200">
                                        <button onclick="abrirModalReversar(${asig.id}, ${asig.cantidad_procesada}, event)" 
                                                data-descripcion="${(asig.orden_item.descripcion || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;')}"
                                                class="w-full px-3 py-1.5 bg-red-500 hover:bg-red-600 text-white text-sm font-semibold rounded transition">
                                            <i class="fas fa-undo mr-1"></i>Reversar Asignación
                                        </button>
                                    </div>
                                ` : ''}
                            </div>
                        `;
                    }).join('')}
                </div>
            </div>
        `).join('')}
        ${data.todas_listas && !data.tiene_ticket_despacho ? `
            <div class="mt-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                <p class="text-green-800 font-semibold">✓ Todas las asignaciones están completadas</p>
                <button onclick="confirmarOrden(${orden.id})" class="mt-2 px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg">
                    Confirmar y Descontar del Inventario
                </button>
            </div>
        ` : ''}
        ${data.tiene_ticket_despacho ? `
            <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <p class="text-blue-800 font-semibold mb-2">✓ Orden confirmada y descontada del inventario</p>
                <div class="text-sm text-blue-700">
                    <p><strong>Ticket de Despacho:</strong> ${data.ticket_despacho.codigo_barras}</p>
                    <p class="mt-1"><strong>Estado:</strong> ${data.ticket_despacho.estado}</p>
                    ${data.ticket_despacho.fecha_generacion ? `
                        <p class="mt-1"><strong>Fecha:</strong> ${new Date(data.ticket_despacho.fecha_generacion).toLocaleString('es-VE')}</p>
                    ` : ''}
                </div>
                ${data.transferencia ? `
                    <div class="mt-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                        <p class="text-green-800 font-semibold mb-2">✓ Orden Transferida</p>
                        <div class="text-sm text-green-700">
                            <p><strong>Sucursal Destino:</strong> ${data.transferencia.sucursal_destino_codigo || 'ID: ' + data.transferencia.sucursal_destino_id}</p>
                            ${data.transferencia.pedido_central_numero ? `
                                <p class="mt-1"><strong>Pedido Central:</strong> ${data.transferencia.pedido_central_numero}</p>
                            ` : ''}
                            ${data.transferencia.fecha_transferencia ? `
                                <p class="mt-1"><strong>Fecha Transferencia:</strong> ${new Date(data.transferencia.fecha_transferencia).toLocaleString('es-VE')}</p>
                            ` : ''}
                        </div>
                    </div>
                ` : `
                    <button onclick="abrirModalTransferir(${orden.id})" class="mt-3 px-4 py-2 bg-purple-500 hover:bg-purple-600 text-white rounded-lg">
                        <i class="fas fa-truck mr-2"></i>Transferir a Sucursal
                    </button>
                `}
            </div>
        ` : ''}
    `;
}

function cerrarModalEstado() {
    document.getElementById('modalEstado').style.display = 'none';
}

let asignacionReversarId = null;
let cantidadMaximaReversar = 0;

function abrirModalReversar(asignacionId, cantidadProcesada, evt) {
    asignacionReversarId = asignacionId;
    cantidadMaximaReversar = cantidadProcesada;
    
    // Obtener la descripción del botón que fue clickeado
    const boton = evt ? evt.target.closest('button') : null;
    const descripcion = boton ? boton.getAttribute('data-descripcion') || 'Producto' : 'Producto';
    
    document.getElementById('infoReversar').innerHTML = `
        <div class="text-sm text-gray-700">
            <p class="font-semibold mb-1">${descripcion}</p>
            <p class="text-xs text-gray-600">Cantidad procesada: <strong>${cantidadProcesada.toFixed(4)}</strong></p>
        </div>
    `;
    
    document.getElementById('inputCantidadReversar').value = '';
    document.getElementById('inputCantidadReversar').max = cantidadProcesada;
    document.getElementById('mensajeCantidadReversar').innerHTML = '';
    document.getElementById('modalReversar').style.display = 'flex';
    document.getElementById('inputCantidadReversar').focus();
}

function cerrarModalReversar() {
    document.getElementById('modalReversar').style.display = 'none';
    asignacionReversarId = null;
    cantidadMaximaReversar = 0;
    document.getElementById('inputCantidadReversar').value = '';
    document.getElementById('mensajeCantidadReversar').innerHTML = '';
}

function validarCantidadReversar(input) {
    const valor = input.value.trim();
    const mensaje = document.getElementById('mensajeCantidadReversar');
    
    // Permitir solo números y punto decimal
    const regex = /^[0-9]*\.?[0-9]*$/;
    if (valor && !regex.test(valor)) {
        input.value = valor.slice(0, -1);
        return;
    }
    
    const cantidad = parseFloat(valor) || 0;
    
    if (cantidad > cantidadMaximaReversar) {
        mensaje.innerHTML = '<span class="text-red-600">La cantidad no puede exceder la cantidad procesada</span>';
        input.value = cantidadMaximaReversar.toFixed(4);
    } else if (cantidad <= 0 && valor) {
        mensaje.innerHTML = '<span class="text-red-600">La cantidad debe ser mayor a 0</span>';
    } else if (cantidad > 0) {
        mensaje.innerHTML = `<span class="text-green-600">Cantidad válida. Máximo: ${cantidadMaximaReversar.toFixed(4)}</span>`;
    } else {
        mensaje.innerHTML = '';
    }
}

function confirmarReversar() {
    console.log('confirmarReversar llamada');
    console.log('asignacionReversarId:', asignacionReversarId);
    console.log('cantidadMaximaReversar:', cantidadMaximaReversar);
    
    const inputCantidad = document.getElementById('inputCantidadReversar');
    if (!inputCantidad) {
        console.error('No se encontró el input de cantidad');
        alert('Error: No se encontró el campo de cantidad');
        return;
    }
    
    const cantidad = parseFloat(inputCantidad.value);
    console.log('cantidad ingresada:', cantidad);
    
    if (!cantidad || cantidad <= 0 || isNaN(cantidad)) {
        mostrarNotificacion('Ingrese una cantidad válida', 'error');
        return;
    }
    
    if (cantidad > cantidadMaximaReversar) {
        mostrarNotificacion(`La cantidad no puede exceder ${cantidadMaximaReversar.toFixed(4)}`, 'error');
        return;
    }
    
    if (!asignacionReversarId) {
        console.error('No hay asignacionReversarId');
        mostrarNotificacion('Error: No se encontró la asignación a reversar', 'error');
        return;
    }
    
    if (!confirm(`¿Está seguro de reversar ${cantidad.toFixed(4)} de esta asignación?`)) {
        return;
    }
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]');
    if (!csrfToken) {
        console.error('No se encontró el token CSRF');
        alert('Error: No se encontró el token de seguridad');
        return;
    }
    
    console.log('Enviando petición...');
    fetch('/warehouse-inventory/tcd/reversar-asignacion', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken.getAttribute('content')
        },
        body: JSON.stringify({
            asignacion_id: asignacionReversarId,
            cantidad: cantidad
        })
    })
    .then(res => {
        console.log('Respuesta recibida:', res.status, res.statusText);
        if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
        }
        return res.json();
    })
    .then(data => {
        console.log('Datos recibidos:', data);
        if (data.estado) {
            mostrarNotificacion(data.msj, 'success');
            cerrarModalReversar();
            
            // Recargar el estado de asignaciones
            const modalEstado = document.getElementById('modalEstado');
            if (modalEstado) {
                const ordenId = modalEstado.getAttribute('data-orden-id');
                if (ordenId) {
                    verEstadoAsignaciones(parseInt(ordenId));
                } else {
                    // Si no hay ordenId guardado, recargar las órdenes
                    cargarOrdenes();
                    cerrarModalEstado();
                }
            } else {
                cargarOrdenes();
            }
        } else {
            console.error('Error del servidor:', data.msj);
            mostrarNotificacion('Error: ' + data.msj, 'error');
        }
    })
    .catch(err => {
        console.error('Error completo:', err);
        mostrarNotificacion('Error al reversar la asignación: ' + err.message, 'error');
        alert('Error al reversar la asignación. Revisa la consola para más detalles.');
    });
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

let ordenTransferirId = null;
let sucursalesDisponibles = [];

function abrirModalTransferir(ordenId) {
    ordenTransferirId = ordenId;
    
    // Obtener información de la orden
    fetch(`/warehouse-inventory/tcd/get-ordenes`)
        .then(res => res.json())
        .then(data => {
            if (data.estado) {
                const orden = data.ordenes.find(o => o.id === ordenId);
                if (orden) {
                    document.getElementById('infoTransferir').innerHTML = `
                        <div class="text-sm text-gray-700">
                            <p class="font-semibold mb-1">${orden.numero_orden}</p>
                            <p class="text-xs text-gray-600">${orden.items.length} producto(s) • Estado: ${orden.estado}</p>
                        </div>
                    `;
                }
            }
        })
        .catch(err => console.error('Error al obtener información de la orden:', err));
    
    // Cargar sucursales disponibles
    cargarSucursales();
    
    document.getElementById('modalTransferir').style.display = 'flex';
}

function cerrarModalTransferir() {
    document.getElementById('modalTransferir').style.display = 'none';
    ordenTransferirId = null;
    document.getElementById('selectSucursal').innerHTML = '<option value="">Cargando sucursales...</option>';
    document.getElementById('mensajeTransferir').innerHTML = '';
}

function cargarSucursales() {
    const select = document.getElementById('selectSucursal');
    select.innerHTML = '<option value="">Cargando sucursales...</option>';
    select.disabled = true;
    
    fetch('/warehouse-inventory/tcd/get-sucursales-disponibles')
        .then(res => res.json())
        .then(data => {
            if (data.estado && data.sucursales) {
                sucursalesDisponibles = data.sucursales;
                select.innerHTML = '<option value="">Seleccione una sucursal...</option>';
                
                if (Array.isArray(data.sucursales)) {
                    data.sucursales.forEach(sucursal => {
                        const option = document.createElement('option');
                        option.value = sucursal.id || sucursal.id_sucursal;
                        option.textContent = `${sucursal.nombre || sucursal.nombre_sucursal || 'Sucursal'} - ${sucursal.codigo || ''}`;
                        select.appendChild(option);
                    });
                } else {
                    // Si viene en otro formato, intentar extraer
                    Object.keys(data.sucursales).forEach(key => {
                        const sucursal = data.sucursales[key];
                        const option = document.createElement('option');
                        option.value = sucursal.id || sucursal.id_sucursal || key;
                        option.textContent = `${sucursal.nombre || sucursal.nombre_sucursal || 'Sucursal'} - ${sucursal.codigo || ''}`;
                        select.appendChild(option);
                    });
                }
                
                select.disabled = false;
            } else {
                select.innerHTML = '<option value="">No hay sucursales disponibles</option>';
                document.getElementById('mensajeTransferir').innerHTML = '<span class="text-red-600">Error: ' + (data.msj || 'No se pudieron cargar las sucursales') + '</span>';
            }
        })
        .catch(err => {
            console.error('Error al cargar sucursales:', err);
            select.innerHTML = '<option value="">Error al cargar sucursales</option>';
            document.getElementById('mensajeTransferir').innerHTML = '<span class="text-red-600">Error al cargar las sucursales</span>';
        });
}

function transferirOrden() {
    if (!ordenTransferirId) {
        alert('Error: No se ha seleccionado una orden');
        return;
    }
    
    const selectSucursal = document.getElementById('selectSucursal');
    const idSucursal = selectSucursal.value;
    
    if (!idSucursal) {
        document.getElementById('mensajeTransferir').innerHTML = '<span class="text-red-600">Por favor seleccione una sucursal</span>';
        return;
    }
    
    if (!confirm('¿Está seguro de transferir esta orden a la sucursal seleccionada?')) {
        return;
    }
    
    document.getElementById('mensajeTransferir').innerHTML = '<span class="text-blue-600">Transferiendo orden...</span>';
    selectSucursal.disabled = true;
    
    fetch('/warehouse-inventory/tcd/transferir-orden-sucursal', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            orden_id: ordenTransferirId,
            id_sucursal: parseInt(idSucursal)
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.estado) {
            alert('Orden transferida exitosamente a la sucursal');
            cerrarModalTransferir();
            cerrarModalEstado();
            cargarOrdenes();
        } else {
            document.getElementById('mensajeTransferir').innerHTML = '<span class="text-red-600">Error: ' + (data.msj || 'Error al transferir la orden') + '</span>';
            selectSucursal.disabled = false;
        }
    })
    .catch(err => {
        console.error('Error al transferir orden:', err);
        document.getElementById('mensajeTransferir').innerHTML = '<span class="text-red-600">Error al transferir la orden</span>';
        selectSucursal.disabled = false;
    });
}

// Cerrar modal al hacer click fuera
document.getElementById('modalTransferir').addEventListener('click', function(e) {
    if (e.target === this) {
        cerrarModalTransferir();
    }
});
</script>
@endsection

