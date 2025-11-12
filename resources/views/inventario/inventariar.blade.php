@extends('layouts.app')

@section('content')
@include('warehouse-inventory.partials.nav')
<div class="container-fluid px-2 py-1">
    <div class="mb-1">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-lg font-bold text-gray-800 flex items-center">
                    <i class="fas fa-boxes text-blue-500 mr-1 text-base"></i>
                    Inventariar Productos
                </h1>
                <p class="text-gray-600 text-xs mt-0.5">Escanea producto, ubicación e ingresa cantidad</p>
            </div>
            <button onclick="mostrarModalReporte()" 
                    class="px-2 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition flex items-center text-xs">
                <i class="fas fa-file-pdf mr-1"></i>
                Generar Reporte PDF
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-2">
        <!-- Panel de Escaneo -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow p-2">
                <h2 class="text-lg font-bold text-gray-700 mb-2">Proceso de Inventariado</h2>
                
                <!-- Paso 1: Escanear Producto -->
                <div id="pasoProducto" class="space-y-2">
                    <div class="bg-green-50 border-l-4 border-green-500 p-2 rounded">
                        <h3 class="font-bold text-green-700 mb-1 text-sm">
                            <span class="bg-green-500 text-white rounded-full w-6 h-6 inline-flex items-center justify-center mr-1 text-xs">1</span>
                            Escanear Producto
                        </h3>
                        <p class="text-xs text-gray-600 mb-1">Escanea el código de barras o código de proveedor del producto</p>
                        <div class="relative">
                            <input type="text" 
                                   id="inputProducto" 
                                   class="w-full px-4 py-4 text-xl font-mono border-2 border-green-400 rounded-lg focus:ring-2 focus:ring-green-500 pr-10 text-gray-900"
                                   placeholder="Escanea código de barras o proveedor"
                                   autofocus
                                   onkeypress="if(event.key === 'Enter') buscarProducto()"
                                   oninput="if(this.disabled) { this.value = this.defaultValue; }">
                            <button onclick="limpiarInput('inputProducto')" 
                                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none"
                                    type="button">
                                <i class="fas fa-times text-lg"></i>
                            </button>
                        </div>
                        <div id="mensajeProducto" class="mt-1 text-xs"></div>
                        <button onclick="buscarProducto()" 
                                class="mt-1 w-full px-2 py-1.5 bg-green-500 hover:bg-green-600 text-white rounded-lg transition text-sm">
                            <i class="fas fa-search mr-1"></i>
                            Buscar Producto
                        </button>
                    </div>
                    
                    <!-- Información del Producto -->
                    <div id="infoProducto" class="bg-white border border-gray-200 rounded-lg p-2" style="display: none;">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <h4 class="font-bold text-gray-800 text-base" id="productoDescripcion"></h4>
                                
                                <!-- Cantidad Actual - Destacada -->
                                <div class="mt-1 mb-1 p-2 bg-blue-50 border-2 border-blue-300 rounded-lg">
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-700 font-semibold text-xs">Cantidad Actual:</span>
                                        <span class="font-bold text-blue-700 text-xl" id="productoCantidadActual"></span>
                                    </div>
                                    <div class="mt-0.5 text-xs text-gray-600" id="productoCantidadAnterior"></div>
                                    <!-- Check para usar cantidad absoluta (visible solo si cantidad != 0, automático desde backend) -->
                                    <div id="checkCantidadAbsolutaContainer" class="mt-1" style="display: none;">
                                        <label class="flex items-center">
                                            <input type="checkbox" 
                                                   id="usarCantidadAbsoluta" 
                                                   class="mr-1 w-3.5 h-3.5 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                                   checked="false"
                                                   disabled>
                                            <span class="text-xs text-gray-600 font-medium">
                                                Usar cantidad absoluta (automático)
                                            </span>
                                        </label>
                                    </div>
                                    <!-- Mensaje informativo sobre movimientos anteriores de PLANILLA INVENTARIO -->
                                    <div id="mensajeMovimientosPlanilla" class="mt-1 p-1.5 bg-yellow-50 border-l-4 border-yellow-400 rounded" style="display: none;">
                                        <div class="flex items-start">
                                            <i class="fas fa-info-circle text-yellow-600 mr-1.5 mt-0.5 text-xs"></i>
                                            <div class="flex-1">
                                                <p class="text-xs font-semibold text-yellow-800 mb-0.5">
                                                    Movimientos anteriores detectados
                                                </p>
                                                <p class="text-xs text-yellow-700" id="textoMovimientosPlanilla"></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Información Básica -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-1 mt-1 text-xs">
                                    <div class="flex items-center">
                                        <span class="text-gray-600 w-32">Código Barras:</span>
                                        <span class="font-mono font-semibold" id="productoCodigoBarras"></span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="text-gray-600 w-32">Código Proveedor:</span>
                                        <span class="font-mono font-semibold" id="productoCodigoProveedor"></span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="text-gray-600 w-32">Proveedor:</span>
                                        <span class="font-semibold" id="productoProveedor"></span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="text-gray-600 w-32">Categoría:</span>
                                        <span class="font-semibold" id="productoCategoria"></span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="text-gray-600 w-32">Marca:</span>
                                        <span class="font-semibold" id="productoMarca"></span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="text-gray-600 w-32">Unidad:</span>
                                        <span class="font-semibold" id="productoUnidad"></span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="text-gray-600 w-32">Precio Venta:</span>
                                        <span class="font-semibold text-green-600" id="productoPrecio"></span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="text-gray-600 w-32">Precio Base:</span>
                                        <span class="font-semibold text-gray-600" id="productoPrecioBase"></span>
                                    </div>
                                </div>

                                <!-- Información de Ubicaciones -->
                                <div id="infoUbicaciones" class="mt-1 p-1 bg-gray-50 border border-gray-200 rounded-lg" style="display: none;">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-xs font-semibold text-gray-700">
                                            <i class="fas fa-map-marker-alt mr-0.5"></i>
                                            En Ubicaciones:
                                        </span>
                                        <span class="font-bold text-gray-800 text-xs" id="productoTotalUbicaciones"></span>
                                    </div>
                                    <div class="text-xs text-gray-600" id="productoNumeroUbicaciones"></div>
                                    <div id="productoListaUbicaciones" class="mt-1 text-xs space-y-0.5"></div>
                                </div>

                                <!-- Botones de Acción -->
                                <div class="mt-1 flex gap-1">
                                    <button onclick="imprimirTicket()" 
                                            class="px-2 py-1 bg-green-500 hover:bg-green-600 text-white rounded-lg transition flex items-center text-xs">
                                        <i class="fas fa-print mr-1"></i>
                                        Imprimir Ticket
                                    </button>
                                </div>
                            </div>
                            <button onclick="resetearProducto()" class="ml-4 text-red-500 hover:text-red-700">
                                <i class="fas fa-times-circle text-xl"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Paso 2: Escanear Ubicación -->
                <div id="pasoUbicacion" class="space-y-2 mt-2" style="display: none;">
                    <div class="bg-blue-50 border-l-4 border-blue-500 p-2 rounded">
                        <h3 class="font-bold text-blue-700 mb-1 text-sm">
                            <span class="bg-blue-500 text-white rounded-full w-6 h-6 inline-flex items-center justify-center mr-1 text-xs">2</span>
                            Escanear Ubicación
                        </h3>
                        <p class="text-xs text-gray-600 mb-1">Escanea o ingresa el código de la ubicación</p>
                        <div class="relative">
                            <input type="text" 
                                   id="inputUbicacion" 
                                   class="w-full px-4 py-4 text-xl font-mono border-2 border-blue-400 rounded-lg focus:ring-2 focus:ring-blue-500 pr-10 text-gray-900"
                                   placeholder="Escanea código de ubicación"
                                   autofocus
                                   oninput="if(this.disabled) { this.value = this.defaultValue; }"
                                   onkeypress="if(event.key === 'Enter') buscarUbicacion()">
                            <button onclick="limpiarInput('inputUbicacion')" 
                                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none"
                                    type="button">
                                <i class="fas fa-times text-lg"></i>
                            </button>
                        </div>
                        <div id="mensajeUbicacion" class="mt-1 text-xs"></div>
                        <button onclick="buscarUbicacion()" 
                                class="mt-1 w-full px-2 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition text-sm">
                            <i class="fas fa-search mr-1"></i>
                            Buscar Ubicación
                        </button>
                    </div>
                    
                    <!-- Información de la Ubicación -->
                    <div id="infoUbicacion" class="bg-white border border-gray-200 rounded-lg p-2" style="display: none;">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <h4 class="font-bold text-gray-800 text-base" id="ubicacionNombre"></h4>
                                <div class="mt-1 space-y-0.5 text-xs">
                                    <div class="flex items-center">
                                        <span class="text-gray-600 w-32">Código:</span>
                                        <span class="font-mono font-semibold" id="ubicacionCodigo"></span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="text-gray-600 w-32">Capacidad:</span>
                                        <span class="font-semibold" id="ubicacionCapacidad"></span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="text-gray-600 w-32">Disponible:</span>
                                        <span class="font-bold text-green-600" id="ubicacionDisponible"></span>
                                    </div>
                                </div>
                            </div>
                            <button onclick="resetearUbicacion()" class="ml-4 text-red-500 hover:text-red-700">
                                <i class="fas fa-times-circle text-xl"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Paso 3: Ingresar Cantidad -->
                <div id="pasoCantidad" class="space-y-2 mt-2" style="display: none;">
                    <div class="bg-orange-50 border-l-4 border-orange-500 p-2 rounded">
                        <h3 class="font-bold text-orange-700 mb-1 text-sm">
                            <span class="bg-orange-500 text-white rounded-full w-6 h-6 inline-flex items-center justify-center mr-1 text-xs">3</span>
                            Ingresar Cantidad
                        </h3>
                        <p class="text-xs text-gray-600 mb-1">Ingresa la cantidad a inventariar</p>
                        <div class="relative">
                            <input type="number" 
                                   id="inputCantidad" 
                                   step="0.0001"
                                   min="0.0001"
                                   class="w-full px-4 py-4 text-xl font-mono border-2 border-orange-400 rounded-lg focus:ring-2 focus:ring-orange-500 pr-10 text-gray-900"
                                   placeholder="Ingrese cantidad"
                                   autofocus
                                   onkeypress="if(event.key === 'Enter') guardarInventario()">
                            <button onclick="limpiarInput('inputCantidad')" 
                                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none"
                                    type="button">
                                <i class="fas fa-times text-lg"></i>
                            </button>
                        </div>
                        <div id="mensajeCantidad" class="mt-1 text-xs"></div>
                        <button onclick="guardarInventario()" 
                                class="mt-1 w-full px-2 py-2 bg-orange-500 hover:bg-orange-600 text-white font-bold rounded-lg transition text-sm">
                            <i class="fas fa-save mr-1"></i>
                            Guardar Inventario
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel de Resumen -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow p-2 sticky top-2">
                <h3 class="text-sm font-bold text-gray-700 mb-1">Resumen</h3>
                <div class="space-y-1">
                    <div class="p-1 bg-gray-50 rounded">
                        <div class="text-xs text-gray-600">Producto</div>
                        <div id="resumenProducto" class="font-semibold text-gray-800 text-xs mt-0.5">-</div>
                    </div>
                    <div class="p-1 bg-gray-50 rounded">
                        <div class="text-xs text-gray-600">Ubicación</div>
                        <div id="resumenUbicacion" class="font-semibold text-gray-800 text-xs mt-0.5">-</div>
                    </div>
                    <div class="p-1 bg-gray-50 rounded">
                        <div class="text-xs text-gray-600">Cantidad</div>
                        <div id="resumenCantidad" class="font-bold text-blue-600 text-base mt-0.5">-</div>
                    </div>
                </div>
                <button onclick="resetearTodo()" 
                        class="mt-1 w-full px-2 py-1 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition text-xs">
                    <i class="fas fa-redo mr-1"></i>
                    Reiniciar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Notificaciones -->
<div id="notificaciones" class="fixed bottom-4 right-4 z-50 space-y-2"></div>

<!-- Modal para Reporte PDF -->
<div id="modalReporte" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden" style="display: none;">
    <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-bold text-gray-800">Generar Reporte PDF</h3>
            <button onclick="cerrarModalReporte()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <form id="formReporte" onsubmit="generarReporte(event)">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Fecha Desde</label>
                    <input type="date" 
                           id="fechaDesde" 
                           name="fecha_desde"
                           value="{{ date('Y-m-d') }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                           required>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Fecha Hasta</label>
                    <input type="date" 
                           id="fechaHasta" 
                           name="fecha_hasta"
                           value="{{ date('Y-m-d') }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                           required>
                </div>
            </div>
            <div class="mt-6 flex gap-3">
                <button type="submit" 
                        class="flex-1 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition">
                    <i class="fas fa-file-pdf mr-2"></i>
                    Generar PDF
                </button>
                <button type="button" 
                        onclick="cerrarModalReporte()"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let productoSeleccionado = null;
let ubicacionSeleccionada = null;

// Función para limpiar inputs y desbloquear campos
function limpiarInput(inputId) {
    const input = document.getElementById(inputId);
    if (input) {
        input.value = '';
        input.disabled = false;
        input.classList.remove('bg-gray-100', 'cursor-not-allowed');
        input.classList.add('border-green-400', 'border-blue-400');
        
        // Limpiar mensajes y reseteos relacionados
        if (inputId === 'inputProducto') {
            productoSeleccionado = null;
            document.getElementById('infoProducto').style.display = 'none';
            document.getElementById('mensajeProducto').innerHTML = '';
            document.getElementById('pasoUbicacion').style.display = 'none';
            // También resetear ubicación si estaba seleccionada
            if (ubicacionSeleccionada) {
                resetearUbicacion();
            }
        } else if (inputId === 'inputUbicacion') {
            ubicacionSeleccionada = null;
            document.getElementById('infoUbicacion').style.display = 'none';
            document.getElementById('mensajeUbicacion').innerHTML = '';
            document.getElementById('pasoCantidad').style.display = 'none';
            document.getElementById('inputCantidad').value = '';
        }
        
        input.focus();
        actualizarResumen();
    }
}

// Función para bloquear un input
function bloquearInput(inputId) {
    const input = document.getElementById(inputId);
    if (input) {
        input.disabled = true;
        input.classList.add('bg-gray-100', 'cursor-not-allowed', '!text-gray-900');
        input.classList.remove('border-green-400', 'border-blue-400', 'border-orange-400');
    }
}

// Función para desbloquear un input
function desbloquearInput(inputId) {
    const input = document.getElementById(inputId);
    if (input) {
        input.disabled = false;
        input.classList.remove('bg-gray-100', 'cursor-not-allowed');
        if (inputId === 'inputProducto') {
            input.classList.add('border-green-400');
        } else if (inputId === 'inputUbicacion') {
            input.classList.add('border-blue-400');
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
    notificacion.className = `${tipoConfig.bg} text-white px-6 py-4 rounded-lg shadow-lg flex items-center space-x-3 min-w-[300px] max-w-md animate-slide-in`;
    notificacion.innerHTML = `
        <i class="fas ${tipoConfig.icon} text-xl"></i>
        <span class="flex-1">${mensaje}</span>
        <button onclick="this.parentElement.remove()" class="text-white hover:text-gray-200">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    document.getElementById('notificaciones').appendChild(notificacion);
    
    setTimeout(() => {
        notificacion.style.opacity = '0';
        notificacion.style.transition = 'opacity 0.3s';
        setTimeout(() => notificacion.remove(), 300);
    }, 5000);
}

function buscarProducto() {
    const inputProducto = document.getElementById('inputProducto');
    const codigo = inputProducto.value.trim();
    
    // Si el campo está bloqueado, no hacer nada
    if (inputProducto.disabled) {
        mostrarNotificacion('El campo está bloqueado. Use el botón X para limpiar y editar', 'warning');
        return;
    }
    
    if (!codigo) {
        mostrarNotificacion('Ingrese un código de producto', 'warning');
        return;
    }
    
    fetch('/inventario/buscar-producto-inventariar', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ codigo: codigo })
    })
    .then(response => response.json())
    .then(data => {
        if (data.estado) {
            productoSeleccionado = data.producto;
            mostrarInfoProducto(data.producto);
            // Bloquear el campo de producto después de encontrarlo
            bloquearInput('inputProducto');
            document.getElementById('mensajeProducto').innerHTML = '<span class="text-green-600"><i class="fas fa-check-circle mr-1"></i>Producto encontrado - Campo bloqueado</span>';
            document.getElementById('pasoUbicacion').style.display = 'block';
            document.getElementById('inputUbicacion').focus();
            actualizarResumen();
        } else {
            mostrarNotificacion(data.msj || 'Producto no encontrado', 'error');
            document.getElementById('mensajeProducto').innerHTML = `<span class="text-red-600">${data.msj}</span>`;
        }
    })
    .catch(error => {
        mostrarNotificacion('Error al buscar producto', 'error');
        console.error(error);
    });
}

function mostrarInfoProducto(producto) {
    document.getElementById('productoDescripcion').textContent = producto.descripcion;
    document.getElementById('productoCodigoBarras').textContent = producto.codigo_barras || 'N/A';
    document.getElementById('productoCodigoProveedor').textContent = producto.codigo_proveedor || 'N/A';
    document.getElementById('productoProveedor').textContent = producto.proveedor || 'N/A';
    document.getElementById('productoCategoria').textContent = producto.categoria || 'N/A';
    document.getElementById('productoMarca').textContent = producto.marca || 'N/A';
    document.getElementById('productoUnidad').textContent = producto.unidad || 'N/A';
    
    // Formatear precios
    const precio = parseFloat(producto.precio) || 0;
    const precioBase = parseFloat(producto.precio_base) || 0;
    document.getElementById('productoPrecio').textContent = precio > 0 ? moneda(precio) : 'N/A';
    document.getElementById('productoPrecioBase').textContent = precioBase > 0 ? moneda(precioBase) : 'N/A';
    
    // Cantidad actual
    const cantidadActual = parseFloat(producto.cantidad_actual) || 0;
    document.getElementById('productoCantidadActual').textContent = cantidadActual;
    
    // Obtener el valor de usar_cantidad_absoluta del backend (determinado automáticamente)
    const usarCantidadAbsoluta = producto.usar_cantidad_absoluta !== undefined ? producto.usar_cantidad_absoluta : false;
    
    // Mostrar check para cantidad absoluta solo si cantidad != 0
    const checkContainer = document.getElementById('checkCantidadAbsolutaContainer');
    const checkAbsoluta = document.getElementById('usarCantidadAbsoluta');
    if (cantidadActual != 0) {
        // Si tiene cantidad, mostrar el check pero deshabilitado (solo informativo)
        checkContainer.style.display = 'block';
        checkAbsoluta.checked = usarCantidadAbsoluta; // Usar valor del backend
        checkAbsoluta.disabled = true; // Deshabilitar para que sea automático
    } else {
        // Si no tiene cantidad, ocultar el check
        checkContainer.style.display = 'none';
        checkAbsoluta.checked = false;
    }
    
    // Mostrar mensaje sobre movimientos anteriores de PLANILLA INVENTARIO
    const mensajeMovimientos = document.getElementById('mensajeMovimientosPlanilla');
    const textoMovimientos = document.getElementById('textoMovimientosPlanilla');
    const tieneMovimientosPlanilla = producto.tiene_movimientos_planilla || false;
    const cantidadMovimientos = producto.cantidad_movimientos_planilla || 0;
    const movimientosPlanilla = producto.movimientos_planilla || [];
    
    if (tieneMovimientosPlanilla && cantidadMovimientos > 0) {
        // Construir el texto del mensaje
        let texto = `Este producto tiene ${cantidadMovimientos} movimiento${cantidadMovimientos > 1 ? 's' : ''} de PLANILLA INVENTARIO en los últimos 3 meses. `;
        texto += `Las cantidades ingresadas se adicionarán a los movimientos anteriores.`;
        
        // Si hay movimientos, mostrar detalles (máximo 3 más recientes)
        if (movimientosPlanilla.length > 0) {
            texto += '<br><span class="font-semibold mt-1 block">Últimos movimientos:</span>';
            const movimientosMostrar = movimientosPlanilla.slice(0, 3);
            movimientosMostrar.forEach((mov, index) => {
                texto += `<br>• ${mov.fecha} - Cantidad: ${mov.cantidad} → ${mov.cantidad_after}`;
            });
            if (movimientosPlanilla.length > 3) {
                texto += `<br><span class="text-yellow-600">... y ${movimientosPlanilla.length - 3} movimiento${movimientosPlanilla.length - 3 > 1 ? 's' : ''} más</span>`;
            }
        }
        
        textoMovimientos.innerHTML = texto;
        mensajeMovimientos.style.display = 'block';
    } else {
        mensajeMovimientos.style.display = 'none';
    }
    
    // Cantidad anterior (si existe)
    const cantidadAnterior = parseFloat(producto.cantidad_anterior) || 0;
    if (cantidadAnterior > 0 && cantidadAnterior !== cantidadActual) {
        document.getElementById('productoCantidadAnterior').textContent = 
            `Cantidad anterior: ${cantidadAnterior} (Diferencia: ${cantidadActual - cantidadAnterior > 0 ? '+' : ''}${cantidadActual - cantidadAnterior})`;
    } else {
        document.getElementById('productoCantidadAnterior').textContent = '';
    }
    
    // Información de ubicaciones
    const totalEnUbicaciones = parseFloat(producto.total_en_ubicaciones) || 0;
    const numeroUbicaciones = parseInt(producto.numero_ubicaciones) || 0;
    
    if (numeroUbicaciones > 0) {
        document.getElementById('productoTotalUbicaciones').textContent = totalEnUbicaciones;
        document.getElementById('productoNumeroUbicaciones').textContent = 
            `Distribuido en ${numeroUbicaciones} ubicación${numeroUbicaciones > 1 ? 'es' : ''}`;
        
        // Lista de ubicaciones
        const listaUbicaciones = document.getElementById('productoListaUbicaciones');
        listaUbicaciones.innerHTML = '';
        if (producto.ubicaciones && producto.ubicaciones.length > 0) {
            producto.ubicaciones.forEach(ubicacion => {
                const div = document.createElement('div');
                div.className = 'flex justify-between items-center py-1 px-2 bg-white rounded';
                div.innerHTML = `
                    <span class="font-mono text-xs">${ubicacion.codigo}</span>
                    <span class="font-semibold text-xs">${ubicacion.cantidad}</span>
                `;
                listaUbicaciones.appendChild(div);
            });
        }
        document.getElementById('infoUbicaciones').style.display = 'block';
    } else {
        document.getElementById('infoUbicaciones').style.display = 'none';
    }
    
    document.getElementById('infoProducto').style.display = 'block';
}

function moneda(valor) {
    return new Intl.NumberFormat('es-VE', {
        style: 'currency',
        currency: 'VES',
        minimumFractionDigits: 2
    }).format(valor);
}

function imprimirTicket() {
    if (!productoSeleccionado) {
        mostrarNotificacion('No hay producto seleccionado', 'warning');
        return;
    }
    
    // Crear formulario temporal para enviar los datos
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/warehouse-inventory/imprimir-ticket-producto';
    form.target = '_blank';
    
    // Agregar token CSRF
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = '_token';
    csrfInput.value = csrfToken;
    form.appendChild(csrfInput);
    
    // Agregar datos del producto
    const campos = [
        { name: 'codigo_barras', value: productoSeleccionado.codigo_barras || '' },
        { name: 'codigo_proveedor', value: productoSeleccionado.codigo_proveedor || '' },
        { name: 'descripcion', value: productoSeleccionado.descripcion || '' },
        { name: 'marca', value: productoSeleccionado.marca || '' }
    ];
    
    campos.forEach(campo => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = campo.name;
        input.value = campo.value;
        form.appendChild(input);
    });
    
    // Agregar formulario al body y enviarlo
    document.body.appendChild(form);
    form.submit();
    
    // Remover formulario después de enviarlo
    setTimeout(() => {
        document.body.removeChild(form);
    }, 100);
}

function resetearProducto() {
    productoSeleccionado = null;
    limpiarInput('inputProducto');
    document.getElementById('infoProducto').style.display = 'none';
    document.getElementById('pasoUbicacion').style.display = 'none';
    document.getElementById('pasoCantidad').style.display = 'none';
    ubicacionSeleccionada = null;
    limpiarInput('inputUbicacion');
    document.getElementById('infoUbicacion').style.display = 'none';
    document.getElementById('inputCantidad').value = '';
    // Ocultar mensaje de movimientos anteriores
    document.getElementById('mensajeMovimientosPlanilla').style.display = 'none';
    actualizarResumen();
    document.getElementById('inputProducto').focus();
}

function buscarUbicacion() {
    const inputUbicacion = document.getElementById('inputUbicacion');
    const codigo = inputUbicacion.value.trim();
    
    // Si el campo está bloqueado, no hacer nada
    if (inputUbicacion.disabled) {
        mostrarNotificacion('El campo está bloqueado. Use el botón X para limpiar y editar', 'warning');
        return;
    }
    
    if (!codigo) {
        mostrarNotificacion('Ingrese un código de ubicación', 'warning');
        return;
    }
    
    fetch('/inventario/buscar-ubicacion-inventariar', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ codigo: codigo })
    })
    .then(response => response.json())
    .then(data => {
        if (data.estado) {
            ubicacionSeleccionada = data.warehouse;
            mostrarInfoUbicacion(data.warehouse);
            // Bloquear el campo de ubicación después de encontrarlo
            bloquearInput('inputUbicacion');
            document.getElementById('mensajeUbicacion').innerHTML = '<span class="text-green-600"><i class="fas fa-check-circle mr-1"></i>Ubicación encontrada - Campo bloqueado</span>';
            document.getElementById('pasoCantidad').style.display = 'block';
            document.getElementById('inputCantidad').focus();
            actualizarResumen();
        } else {
            mostrarNotificacion(data.msj || 'Ubicación no encontrada', 'error');
            document.getElementById('mensajeUbicacion').innerHTML = `<span class="text-red-600">${data.msj}</span>`;
        }
    })
    .catch(error => {
        mostrarNotificacion('Error al buscar ubicación', 'error');
        console.error(error);
    });
}

function mostrarInfoUbicacion(warehouse) {
    document.getElementById('ubicacionNombre').textContent = warehouse.nombre || warehouse.codigo;
    document.getElementById('ubicacionCodigo').textContent = warehouse.codigo;
    document.getElementById('ubicacionCapacidad').textContent = warehouse.capacidad || 'N/A';
    document.getElementById('ubicacionDisponible').textContent = warehouse.capacidad_disponible || 'N/A';
    document.getElementById('infoUbicacion').style.display = 'block';
}

function resetearUbicacion() {
    ubicacionSeleccionada = null;
    limpiarInput('inputUbicacion');
    document.getElementById('infoUbicacion').style.display = 'none';
    document.getElementById('pasoCantidad').style.display = 'none';
    document.getElementById('inputCantidad').value = '';
    actualizarResumen();
    document.getElementById('inputUbicacion').focus();
}

function guardarInventario() {
    if (!productoSeleccionado || !ubicacionSeleccionada) {
        mostrarNotificacion('Complete todos los pasos antes de guardar', 'warning');
        return;
    }
    
    const cantidad = parseFloat(document.getElementById('inputCantidad').value);
    if (!cantidad || cantidad <= 0) {
        mostrarNotificacion('Ingrese una cantidad válida', 'warning');
        return;
    }
    
    // Obtener el valor del check de cantidad absoluta
    const usarCantidadAbsoluta = document.getElementById('usarCantidadAbsoluta').checked;
    
    fetch('/inventario/guardar-inventario-con-ubicacion', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            producto_id: productoSeleccionado.id,
            warehouse_id: ubicacionSeleccionada.id,
            cantidad: cantidad,
            usar_cantidad_absoluta: usarCantidadAbsoluta
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.estado) {
            mostrarNotificacion(data.msj || 'Producto inventariado exitosamente', 'success');
            resetearTodo();
        } else {
            mostrarNotificacion(data.msj || 'Error al guardar inventario', 'error');
        }
    })
    .catch(error => {
        mostrarNotificacion('Error al guardar inventario', 'error');
        console.error(error);
    });
}

function actualizarResumen() {
    document.getElementById('resumenProducto').textContent = productoSeleccionado 
        ? productoSeleccionado.descripcion 
        : '-';
    document.getElementById('resumenUbicacion').textContent = ubicacionSeleccionada 
        ? ubicacionSeleccionada.codigo 
        : '-';
    const cantidad = document.getElementById('inputCantidad').value;
    document.getElementById('resumenCantidad').textContent = cantidad || '-';
}

function resetearTodo() {
    productoSeleccionado = null;
    ubicacionSeleccionada = null;
    limpiarInput('inputProducto');
    limpiarInput('inputUbicacion');
    document.getElementById('inputCantidad').value = '';
    document.getElementById('infoProducto').style.display = 'none';
    document.getElementById('infoUbicacion').style.display = 'none';
    document.getElementById('pasoUbicacion').style.display = 'none';
    document.getElementById('pasoCantidad').style.display = 'none';
    // Ocultar mensaje de movimientos anteriores
    document.getElementById('mensajeMovimientosPlanilla').style.display = 'none';
    actualizarResumen();
    document.getElementById('inputProducto').focus();
}

// Actualizar resumen cuando cambia la cantidad
document.getElementById('inputCantidad').addEventListener('input', actualizarResumen);

// Funciones para el modal de reporte
function mostrarModalReporte() {
    const modal = document.getElementById('modalReporte');
    modal.classList.remove('hidden');
    modal.style.display = 'flex';
    modal.style.alignItems = 'center';
    modal.style.justifyContent = 'center';
}

function cerrarModalReporte() {
    const modal = document.getElementById('modalReporte');
    modal.classList.add('hidden');
    modal.style.display = 'none';
}

function generarReporte(event) {
    event.preventDefault();
    const fechaDesde = document.getElementById('fechaDesde').value;
    const fechaHasta = document.getElementById('fechaHasta').value;
    
    if (!fechaDesde || !fechaHasta) {
        mostrarNotificacion('Por favor seleccione ambas fechas', 'warning');
        return;
    }
    
    if (fechaDesde > fechaHasta) {
        mostrarNotificacion('La fecha desde no puede ser mayor que la fecha hasta', 'warning');
        return;
    }
    
    // Construir URL con parámetros
    const url = `/inventario/reporte-planilla-pdf?fecha_desde=${fechaDesde}&fecha_hasta=${fechaHasta}`;
    
    // Abrir en nueva pestaña para descargar el PDF
    window.open(url, '_blank');
    
    cerrarModalReporte();
    mostrarNotificacion('Generando reporte PDF...', 'info');
}
</script>

<style>
@keyframes slide-in {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.animate-slide-in {
    animation: slide-in 0.3s ease-out;
}
</style>
@endsection

