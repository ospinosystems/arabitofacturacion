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
                
                <!-- Paso 1: Seleccionar Asignación -->
                <div id="pasoSeleccionarAsignacion" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Seleccione una asignación</label>
                        <select id="selectAsignacion" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-400" onchange="seleccionarAsignacion()">
                            <option value="">Seleccione una asignación...</option>
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
                            <h3 class="font-bold text-green-700">Paso 1: Escanear Producto</h3>
                            <button onclick="volverASeleccionarAsignacion()" class="text-xs px-2 py-1 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded">
                                <i class="fas fa-arrow-left mr-1"></i> Cambiar Asignación
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
                        <p>Seleccione una asignación para comenzar</p>
                    </div>
                </div>
            </div>

            <!-- Lista de Asignaciones -->
            <div class="bg-white rounded-lg shadow p-4">
                <h3 class="font-bold text-gray-700 mb-4">Mis Asignaciones</h3>
                <div id="listaAsignaciones" class="space-y-2 max-h-[500px] overflow-y-auto">
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
let asignaciones = [];
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
                asignaciones = data.asignaciones;
                mostrarAsignaciones();
                actualizarSelectAsignaciones();
            } else {
                mostrarNotificacion('Error al cargar asignaciones: ' + data.msj, 'error');
            }
        })
        .catch(err => {
            console.error(err);
            mostrarNotificacion('Error al cargar asignaciones', 'error');
        });
}

function mostrarAsignaciones() {
    const contenedor = document.getElementById('listaAsignaciones');
    
    if (asignaciones.length === 0) {
        contenedor.innerHTML = '<div class="text-center text-gray-400 py-4">No hay asignaciones pendientes</div>';
        return;
    }
    
    contenedor.innerHTML = asignaciones.map(asig => {
        const porcentaje = (asig.cantidad_procesada / asig.cantidad) * 100;
        const estadoColors = {
            'pendiente': 'bg-gray-100 text-gray-800',
            'en_proceso': 'bg-yellow-100 text-yellow-800',
            'completada': 'bg-green-100 text-green-800'
        };
        
        return `
            <div class="border border-gray-200 rounded-lg p-3 hover:bg-gray-50 cursor-pointer" onclick="seleccionarAsignacionPorId(${asig.id})">
                <div class="flex justify-between items-start mb-2">
                    <div class="flex-1">
                        <h4 class="font-semibold text-gray-800 text-sm">${asig.orden_item.descripcion}</h4>
                        <p class="text-xs text-gray-600 mt-1">Orden: ${asig.orden.numero_orden}</p>
                    </div>
                    <span class="px-2 py-1 rounded text-xs font-semibold ${estadoColors[asig.estado] || 'bg-gray-100'}">
                        ${asig.estado}
                    </span>
                </div>
                <div class="text-xs text-gray-600 mb-1">
                    ${asig.cantidad_procesada} / ${asig.cantidad}
                </div>
                <div class="w-full bg-gray-200 rounded-full h-1.5">
                    <div class="bg-blue-500 h-1.5 rounded-full" style="width: ${porcentaje}%"></div>
                </div>
            </div>
        `;
    }).join('');
}

function actualizarSelectAsignaciones() {
    const select = document.getElementById('selectAsignacion');
    select.innerHTML = '<option value="">Seleccione una asignación...</option>';
    
    asignaciones.filter(a => a.estado !== 'completada').forEach(asig => {
        const option = document.createElement('option');
        option.value = asig.id;
        option.textContent = `${asig.orden_item.descripcion} - ${asig.cantidad_procesada}/${asig.cantidad}`;
        select.appendChild(option);
    });
}

function seleccionarAsignacion() {
    const select = document.getElementById('selectAsignacion');
    const asignacionId = select.value;
    
    if (!asignacionId) {
        return;
    }
    
    seleccionarAsignacionPorId(asignacionId);
}

function seleccionarAsignacionPorId(asignacionId) {
    asignacionActual = asignaciones.find(a => a.id == asignacionId);
    
    if (!asignacionActual) {
        mostrarNotificacion('Asignación no encontrada', 'error');
        return;
    }
    
    // Actualizar select
    document.getElementById('selectAsignacion').value = asignacionId;
    
    // Mostrar información
    mostrarInfoAsignacion();
    
    // Mostrar paso de producto
    document.getElementById('pasoSeleccionarAsignacion').style.display = 'none';
    document.getElementById('pasoProducto').style.display = 'block';
    document.getElementById('inputProducto').value = '';
    document.getElementById('inputProducto').focus();
    document.getElementById('mensajeProducto').innerHTML = '';
}

function mostrarInfoAsignacion() {
    const contenedor = document.getElementById('infoAsignacion');
    const asig = asignacionActual;
    
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
                <p class="text-orange-600 font-bold">${(asig.cantidad - asig.cantidad_procesada).toFixed(4)}</p>
            </div>
            <div>
                <span class="font-semibold text-gray-700">Estado:</span>
                <p class="text-gray-600">${asig.estado}</p>
            </div>
        </div>
    `;
}

function volverASeleccionarAsignacion() {
    asignacionActual = null;
    ubicacionActual = null;
    
    document.getElementById('pasoSeleccionarAsignacion').style.display = 'block';
    document.getElementById('pasoProducto').style.display = 'none';
    document.getElementById('pasoUbicacion').style.display = 'none';
    document.getElementById('pasoCantidad').style.display = 'none';
    document.getElementById('pasoFinalizar').style.display = 'none';
    
    document.getElementById('inputProducto').value = '';
    document.getElementById('inputUbicacion').value = '';
    document.getElementById('inputCantidad').value = '';
}

function verificarProducto() {
    const codigo = document.getElementById('inputProducto').value.trim().toUpperCase();
    const mensaje = document.getElementById('mensajeProducto');
    
    if (!codigo) {
        mensaje.innerHTML = '<span class="text-red-600">Ingrese un código</span>';
        return;
    }
    
    const codigoBarras = (asignacionActual.orden_item.codigo_barras || '').toUpperCase();
    const codigoProveedor = (asignacionActual.orden_item.codigo_proveedor || '').toUpperCase();
    
    if (codigo === codigoBarras || codigo === codigoProveedor) {
        mensaje.innerHTML = '<span class="text-green-600"><i class="fas fa-check-circle mr-1"></i>Producto correcto</span>';
        setTimeout(() => {
            document.getElementById('pasoProducto').style.display = 'none';
            document.getElementById('pasoUbicacion').style.display = 'block';
            document.getElementById('inputUbicacion').value = '';
            document.getElementById('inputUbicacion').focus();
        }, 500);
    } else {
        mensaje.innerHTML = '<span class="text-red-600"><i class="fas fa-times-circle mr-1"></i>El código no coincide</span>';
    }
}

function verificarUbicacion() {
    const codigo = document.getElementById('inputUbicacion').value.trim();
    const mensaje = document.getElementById('mensajeUbicacion');
    
    if (!codigo) {
        mensaje.innerHTML = '<span class="text-red-600">Ingrese un código de ubicación</span>';
        return;
    }
    
    fetch(`/warehouse-inventory/tcd/buscar-ubicacion?codigo=${encodeURIComponent(codigo)}`)
        .then(res => res.json())
        .then(data => {
            if (data.estado) {
                ubicacionActual = data.ubicacion;
                mensaje.innerHTML = '<span class="text-green-600"><i class="fas fa-check-circle mr-1"></i>Ubicación encontrada: ' + data.ubicacion.codigo + '</span>';
                setTimeout(() => {
                    document.getElementById('pasoUbicacion').style.display = 'none';
                    document.getElementById('pasoCantidad').style.display = 'block';
                    document.getElementById('cantidadPendiente').textContent = (asignacionActual.cantidad - asignacionActual.cantidad_procesada).toFixed(4);
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
            
            // Reiniciar formulario
            volverASeleccionarAsignacion();
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

