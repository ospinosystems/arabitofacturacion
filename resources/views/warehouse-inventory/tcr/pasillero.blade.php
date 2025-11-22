@extends('layouts.app')
@section('nav')
    @include('warehouse-inventory.partials.nav')
@endsection

@section('content')
<div class="container-fluid px-2 sm:px-4 py-2 sm:py-3">
    <div class="mb-3 sm:mb-4">
        <h1 class="text-lg sm:text-2xl font-bold text-gray-800 flex items-center flex-wrap gap-2">
            <i class="fas fa-barcode text-green-500"></i>
            <span>TCR - Pasillero</span>
        </h1>
        <p class="text-gray-600 mt-1 text-xs sm:text-sm">Escanea ubicación, producto y asigna cantidad</p>
    </div>

    <!-- Tabs para cambiar entre Asignaciones, Novedades y Mis Pedidos -->
    <div class="mb-3 sm:mb-4">
        <div class="flex space-x-2 border-b border-gray-200">
            <button onclick="mostrarTab('asignaciones')" id="tabAsignaciones" class="px-4 py-2 text-sm font-medium border-b-2 border-blue-500 text-blue-600">
                <i class="fas fa-tasks mr-2"></i>Asignaciones
            </button>
            <button onclick="mostrarTab('novedades')" id="tabNovedades" class="px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                <i class="fas fa-exclamation-triangle mr-2"></i>Novedades
            </button>
            <button onclick="mostrarTab('pedidos')" id="tabPedidos" class="px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                <i class="fas fa-list mr-2"></i>Mis Pedidos
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-4">
        <!-- Panel de Escaneo -->
        <div id="panelAsignaciones" class="lg:col-span-2 order-2 lg:order-1">
            <div class="bg-white rounded-lg shadow p-3 sm:p-4 md:p-6">
                <h2 class="text-lg sm:text-xl font-bold text-gray-700 mb-3 sm:mb-4">Procesar Asignación</h2>
                
                <!-- Paso 1: Seleccionar Pedido -->
                <div id="pasoSeleccionarPedido" class="space-y-3 sm:space-y-4">
                    <div>
                        <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1 sm:mb-2">Seleccione un pedido</label>
                        <select id="selectPedido" class="w-full px-3 py-3 sm:py-2 text-sm sm:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-400" onchange="seleccionarPedido()">
                            <option value="">Seleccione un pedido...</option>
                        </select>
                    </div>
                    <button onclick="cargarAsignaciones()" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition text-sm sm:text-sm">
                        <i class="fas fa-sync-alt mr-2"></i>
                        Actualizar Lista
                    </button>
                </div>

                <!-- Paso 2: Escanear Producto -->
                <div id="pasoProducto" class="space-y-3 sm:space-y-4 mt-4 sm:mt-6" style="display: none;">
                    <div class="bg-green-50 border-l-4 border-green-500 p-3 sm:p-4 rounded">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-2">
                            <h3 class="font-bold text-green-700 text-sm sm:text-sm">Pedido #<span id="pedidoSeleccionadoId"></span> - Escanear Producto</h3>
                            <button onclick="volverASeleccionarPedido()" class="text-xs px-3 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded w-full sm:w-auto">
                                <i class="fas fa-arrow-left mr-1"></i> Cambiar Pedido
                            </button>
                        </div>
                        <p class="text-xs sm:text-sm text-gray-600 mb-3">Escanea el código de barras o código de proveedor del producto</p>
                        <div class="flex flex-col sm:flex-row gap-2">
                            <div class="relative flex-1">
                                <input type="text" 
                                       id="inputProducto" 
                                       class="w-full px-4 pr-10 py-4 sm:py-3 text-lg font-mono border-2 border-green-400 rounded-lg focus:ring-2 focus:ring-green-500"
                                       placeholder="Escanea código de barras o proveedor"
                                       autofocus>
                                <button onclick="limpiarInputProducto()" 
                                        id="btnLimpiarProducto"
                                        class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-red-500 transition hidden"
                                        title="Limpiar">
                                    <i class="fas fa-times text-lg sm:text-xl"></i>
                                </button>
                            </div>
                            <button onclick="mostrarBusquedaManual()" class="px-4 py-4 sm:py-3 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition text-sm sm:text-sm w-full sm:w-auto" title="Búsqueda Manual">
                                <i class="fas fa-search mr-2 sm:mr-0"></i>
                                <span class="sm:hidden">Búsqueda Manual</span>
                            </button>
                        </div>
                        <div id="mensajeProducto" class="mt-2 text-xs sm:text-sm"></div>
                        <!-- Mostrar cantidad esperada y campo para ingresar cantidad real -->
                        <div id="cantidadLlego" class="mt-2 hidden">
                            <div class="bg-blue-100 border border-blue-300 rounded-lg p-2 sm:p-3">
                                <div class="mb-2">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-xs sm:text-sm font-medium text-blue-800">Cantidad esperada:</span>
                                        <span id="cantidadEsperadaValor" class="sm:text-lg font-bold text-blue-900"></span>
                                    </div>
                                </div>
                                <div class="border-t border-blue-300 pt-2 mt-2">
                                    <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Cantidad real que llegó:</label>
                                    <div class="flex gap-2">
                                        <input type="number" 
                                               id="inputCantidadRealLlego" 
                                               step="0.0001"
                                               class="flex-1 px-3 py-2 text-lg font-mono border-2 border-blue-400 rounded-lg focus:ring-2 focus:ring-blue-500"
                                               placeholder="Cantidad real (+ para sumar, - para restar)"
                                               autofocus>
                                        <button onclick="verificarCantidadReal()" 
                                                class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition">
                                            <i class="fas fa-check mr-1"></i>Verificar
                                        </button>
                                    </div>
                                    <div id="mensajeCantidadReal" class="mt-2 text-xs"></div>
                                </div>
                            </div>
                        </div>
                        <!-- Modal para ingresar cantidad cuando no se encuentra el producto -->
                        <div id="modalCantidadNovedad" class="mt-3 hidden">
                            <div class="bg-yellow-100 border-l-4 border-orange-500 p-3 sm:p-4 rounded">
                                <h4 class="font-bold text-orange-700 mb-2 text-sm sm:text-sm">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    Producto no encontrado - Registrar como novedad
                                </h4>
                                <p class="text-xs sm:text-sm text-gray-600 mb-3">Ingrese la cantidad que llegó para registrar la novedad:</p>
                                <div class="flex gap-2">
                                    <input type="number" 
                                           id="inputCantidadLlegoNovedad" 
                                           step="0.0001"
                                           class="flex-1 px-3 py-2 text-lg font-mono border-2 border-orange-400 rounded-lg focus:ring-2 focus:ring-orange-500"
                                           placeholder="Cantidad (+ para sumar, - para restar)"
                                           autofocus>
                                    <button onclick="confirmarRegistrarNovedad()" 
                                            class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg transition">
                                        <i class="fas fa-check mr-1"></i>Registrar
                                    </button>
                                    <button onclick="cancelarRegistrarNovedad()" 
                                            class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition">
                                        <i class="fas fa-times mr-1"></i>Cancelar
                                    </button>
                                </div>
                                <div id="mensajeCantidadNovedad" class="mt-2 text-xs"></div>
                            </div>
                        </div>
                        
                        <!-- Buscador Manual (Oculto por defecto) -->
                        <div id="buscadorManual" class="mt-3 sm:mt-4 p-3 sm:p-4 bg-white rounded-lg border border-gray-200 shadow-sm hidden">
                            <h4 class="font-bold text-gray-700 mb-2 sm:mb-3 flex items-center gap-2 text-sm sm:text-sm">
                                <i class="fas fa-search text-blue-500"></i> Búsqueda Manual
                            </h4>
                            <input type="text" 
                                   id="inputBusquedaManual" 
                                   class="w-full px-3 py-3 sm:py-2 text-sm sm:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-400 mb-3"
                                   placeholder="Escribe nombre o código..."
                                   onkeyup="filtrarProductosManual()">
                            
                            <div id="listaProductosManual" class="space-y-2 max-h-48 sm:max-h-60 overflow-y-auto border rounded-lg p-2 bg-gray-50">
                                <!-- Resultados de búsqueda -->
                            </div>
                            
                            <button onclick="ocultarBusquedaManual()" class="mt-3 text-xs sm:text-sm text-gray-500 hover:text-gray-700 underline w-full text-center py-2">
                                Cancelar búsqueda manual
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Paso 3: Escanear Ubicación -->
                <div id="pasoUbicacion" class="space-y-3 sm:space-y-4 mt-4 sm:mt-6" style="display: none;">
                    <div class="bg-blue-50 border-l-4 border-blue-500 p-3 sm:p-4 rounded">
                        <h3 class="font-bold text-blue-700 mb-2 text-sm sm:text-sm">Paso 2: Escanear Ubicación</h3>
                        <input type="text" 
                               id="inputUbicacion" 
                               class="w-full px-4 py-4 sm:py-3 text-lg font-mono border-2 border-blue-400 rounded-lg focus:ring-2 focus:ring-blue-500"
                               placeholder="Escanea o ingresa código de ubicación"
                               autofocus>
                        <div id="mensajeUbicacion" class="mt-2 text-xs sm:text-sm"></div>
                    </div>
                </div>

                <!-- Paso 5: Ingresar Cantidad -->
                <div id="pasoCantidad" class="space-y-3 sm:space-y-4 mt-4 sm:mt-6" style="display: none;">
                    <div class="bg-yellow-100 border-l-4 border-orange-500 p-3 sm:p-4 rounded">
                        <h3 class="font-bold text-orange-700 mb-2 text-sm sm:text-sm">Paso 3: Ingresar Cantidad</h3>
                        <div class="mb-2 sm:mb-3">
                            <span class="text-xs sm:text-sm text-gray-600">Cantidad pendiente: </span>
                            <span id="cantidadPendiente" class="font-bold text-lg sm:text-xl text-orange-600"></span>
                        </div>
                        <input type="number" 
                               id="inputCantidad" 
                               step="0.0001"
                               min="0.0001"
                               class="w-full px-4 py-4 sm:py-3 text-lg font-mono border-2 border-orange-400 rounded-lg focus:ring-2 focus:ring-orange-500"
                               placeholder="Ingrese cantidad"
                               autofocus>
                        <div class="mt-2 sm:mt-3 flex flex-col sm:flex-row gap-2">
                            <button onclick="usarCantidadTotal()" class="flex-1 px-4 py-3 sm:py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg transition text-sm">
                                Usar Cantidad Total
                            </button>
                            <button onclick="usarCantidadParcial()" class="flex-1 px-4 py-3 sm:py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition text-sm">
                                Cantidad Parcial
                            </button>
                        </div>
                        <div id="mensajeCantidad" class="mt-2 text-xs sm:text-sm"></div>
                    </div>
                </div>

                <!-- Botón Finalizar -->
                <div id="pasoFinalizar" class="mt-4 sm:mt-6" style="display: none;">
                    <button onclick="procesarAsignacion()" 
                            class="w-full px-4 sm:px-6 py-4 bg-green-500 hover:bg-green-600 text-white font-bold text-sm sm:text-lg rounded-lg shadow-lg transition">
                        <i class="fas fa-check-circle mr-2"></i>
                        Confirmar y Enviar a Revisión
                    </button>
                </div>
            </div>
        </div>

        <!-- Panel de Novedades (Oculto por defecto) -->
        <div id="panelNovedades" class="lg:col-span-2 order-2 lg:order-1 hidden">
            <div class="bg-white rounded-lg shadow p-3 sm:p-4 md:p-6 mb-3 sm:mb-4">
                <div class="flex items-center justify-between mb-3 sm:mb-4">
                    <h2 class="text-lg sm:text-xl font-bold text-gray-700">Registrar Novedades</h2>
                    <button onclick="cargarNovedades()" 
                            class="px-3 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg transition text-sm flex items-center gap-2"
                            title="Actualizar Novedades">
                        <i class="fas fa-sync-alt"></i>
                        <span class="hidden sm:inline">Actualizar</span>
                    </button>
                </div>
                
                <!-- Escanear Producto para Novedad -->
                <div class="bg-purple-50 border-l-4 border-purple-500 p-3 sm:p-4 rounded mb-4">
                    <h3 class="font-bold text-purple-700 mb-2 text-sm sm:text-sm">Escanear Producto</h3>
                    <p class="text-xs sm:text-sm text-gray-600 mb-3">Escanea el código de barras o código de proveedor del producto</p>
                    <div class="flex flex-col sm:flex-row gap-2">
                        <div class="relative flex-1">
                            <input type="text" 
                                   id="inputProductoNovedad" 
                                   class="w-full px-4 pr-10 py-4 sm:py-3 text-lg font-mono border-2 border-purple-400 rounded-lg focus:ring-2 focus:ring-purple-500"
                                   placeholder="Escanea código de barras o proveedor"
                                   autofocus>
                            <button onclick="limpiarInputProductoNovedad()" 
                                    id="btnLimpiarProductoNovedad"
                                    class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-red-500 transition hidden"
                                    title="Limpiar">
                                <i class="fas fa-times text-lg sm:text-xl"></i>
                            </button>
                        </div>
                    </div>
                    <div id="mensajeProductoNovedad" class="mt-2 text-xs sm:text-sm"></div>
                </div>

                <!-- Información del Producto Escaneado -->
                <div id="infoNovedad" class="bg-white border border-gray-200 rounded-lg p-3 sm:p-4 mb-4 hidden">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex-1">
                            <h4 class="font-bold text-gray-800 text-sm mb-2" id="novedadDescripcion"></h4>
                            <div class="grid grid-cols-2 gap-2 text-xs sm:text-sm mb-3">
                                <div><span class="font-medium text-gray-600">Código Barras:</span> <span class="font-mono" id="novedadCodigoBarras"></span></div>
                                <div><span class="font-medium text-gray-600">Código Proveedor:</span> <span class="font-mono" id="novedadCodigoProveedor"></span></div>
                            </div>
                            
                            <!-- Cantidad que llegó con botón de imprimir -->
                            <div class="bg-blue-100 border border-blue-300 rounded-lg p-2 sm:p-3 mb-3">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs sm:text-sm font-medium text-blue-800">Cantidad que llegó:</span>
                                    <div class="flex items-center gap-2">
                                        <span id="novedadCantidadLlego" class="sm:text-lg font-bold text-blue-900">0</span>
                                        <button onclick="imprimirTicketNovedad()" 
                                                class="px-3 py-1.5 bg-green-500 hover:bg-green-600 text-white rounded-lg transition flex items-center text-xs sm:text-sm">
                                            <i class="fas fa-print mr-1"></i>
                                            Imprimir
                                        </button>
                                    </div>
                                </div>
                                <!-- Campo para ingresar/editar cantidad que llegó (solo si es nueva o no tiene cantidad) -->
                                <div id="campoCantidadLlego" class="hidden">
                                    <label class="block text-xs text-gray-700 mb-1">Ingresar cantidad que llegó:</label>
                                    <div class="flex gap-2">
                                        <input type="number" 
                                               id="inputCantidadLlego" 
                                               step="0.0001"
                                               min="0"
                                               class="flex-1 px-2 py-1.5 text-sm font-mono border border-blue-400 rounded focus:ring-2 focus:ring-blue-500"
                                               placeholder="Cantidad">
                                        <button onclick="guardarCantidadLlego()" 
                                                class="px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded transition text-xs">
                                            <i class="fas fa-save mr-1"></i>Guardar
                                        </button>
                                    </div>
                                </div>
                                <button id="btnEditarCantidadLlego" onclick="mostrarEditarCantidadLlego()" 
                                        class="text-xs text-blue-600 hover:text-blue-800 underline mt-1">
                                    <i class="fas fa-edit mr-1"></i>Editar cantidad que llegó
                                </button>
                            </div>

                            <!-- Cantidad Enviada y Diferencia -->
                            <div class="grid grid-cols-2 gap-2 mb-3">
                                <div class="bg-green-50 border border-green-300 rounded-lg p-2">
                                    <div class="text-xs text-gray-600 mb-1">Cantidad Enviada</div>
                                    <div id="novedadCantidadEnviada" class="text-lg font-bold text-green-700">0</div>
                                </div>
                                <div class="bg-yellow-100 border border-orange-300 rounded-lg p-2">
                                    <div class="text-xs text-gray-600 mb-1">Diferencia</div>
                                    <div id="novedadDiferencia" class="text-lg font-bold text-orange-700">0</div>
                                </div>
                            </div>

                            <!-- Historial de Agregaciones -->
                            <div id="novedadHistorial" class="mb-3 hidden">
                                <h5 class="font-semibold text-gray-700 text-xs sm:text-sm mb-2">Historial de Agregaciones:</h5>
                                <div id="novedadHistorialLista" class="space-y-1 max-h-32 overflow-y-auto text-xs"></div>
                            </div>

                            <!-- Agregar Cantidad -->
                            <div class="border-t pt-3">
                                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Agregar Cantidad</label>
                                <div class="flex gap-2">
                                    <input type="number" 
                                           id="inputCantidadNovedad" 
                                           step="0.0001"
                                           class="flex-1 px-3 py-2 text-lg font-mono border-2 border-purple-400 rounded-lg focus:ring-2 focus:ring-purple-500"
                                           placeholder="Cantidad (+ para sumar, - para restar)">
                                    <button onclick="agregarCantidadNovedad()" 
                                            class="px-4 py-2 bg-purple-500 hover:bg-purple-600 text-white rounded-lg transition">
                                        <i class="fas fa-edit mr-1"></i>Aplicar
                                    </button>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Use números negativos para corregir (máximo: -${novedadActual?.cantidad_llego || 0})
                                </p>
                            </div>
                        </div>
                        <button onclick="limpiarNovedad()" class="ml-4 text-red-500 hover:text-red-700 p-2">
                            <i class="fas fa-times-circle text-2xl"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel de Mis Pedidos (Oculto por defecto) -->
        <div id="panelPedidos" class="lg:col-span-2 order-2 lg:order-1 hidden">
            <div class="bg-white rounded-lg shadow p-3 sm:p-4 md:p-6">
                <h2 class="text-lg sm:text-xl font-bold text-gray-700 mb-3 sm:mb-4">Mis Pedidos</h2>
                <div id="listaPedidos" class="space-y-2">
                    <div class="text-center text-gray-400 py-4">
                        <i class="fas fa-spinner fa-spin text-xl sm:text-2xl"></i>
                        <p class="mt-2 text-xs sm:text-sm">Cargando...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel Lateral (Siempre visible) -->
        <div class="lg:col-span-1 order-1 lg:order-2">
            <!-- Información de Asignación (solo visible en tab de asignaciones) -->
            <div id="infoAsignacionContainer" class="bg-white rounded-lg shadow p-3 sm:p-4 mb-3 sm:mb-4">
                <h3 class="font-bold text-gray-700 mb-3 sm:mb-4 text-sm sm:text-sm">Información de Asignación</h3>
                <div id="infoAsignacion" class="space-y-2 sm:space-y-3 text-xs sm:text-sm">
                    <div class="text-center text-gray-400 py-6 sm:py-8">
                        <i class="fas fa-info-circle text-3xl sm:text-4xl mb-2"></i>
                        <p class="text-xs sm:text-sm">Seleccione un pedido para comenzar</p>
                    </div>
                </div>
            </div>

            <!-- Panel de Reporte de Novedades (Siempre visible, tiempo real) -->
            <div id="panelReporteNovedades" class="bg-white rounded-lg shadow p-3 sm:p-4 sticky top-2">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-bold text-gray-700 text-sm sm:text-sm">
                        <i class="fas fa-exclamation-triangle text-orange-500 mr-1"></i>
                        Novedades (Tiempo Real)
                    </h3>
                    <div class="flex gap-2">
                        <button onclick="cargarNovedades()" 
                                class="px-2 py-1 bg-green-500 hover:bg-green-600 text-white rounded text-xs"
                                title="Actualizar Novedades">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <button onclick="imprimirReporteNovedades()" 
                                class="px-2 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded text-xs"
                                title="Imprimir">
                            <i class="fas fa-print"></i>
                        </button>
                        <button onclick="exportarPDFNovedades()" 
                                class="px-2 py-1 bg-red-500 hover:bg-red-600 text-white rounded text-xs"
                                title="Exportar PDF">
                            <i class="fas fa-file-pdf"></i>
                        </button>
                    </div>
                </div>
                <div id="reporteNovedades" class="space-y-2 max-h-[400px] sm:max-h-[600px] overflow-y-auto text-xs">
                    <div class="text-center text-gray-400 py-4">
                        <i class="fas fa-info-circle text-xl mb-2"></i>
                        <p>No hay novedades registradas</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Contenedor de notificaciones -->
<div id="notificacionesContainer" class="fixed bottom-2 right-2 sm:bottom-4 sm:right-4 z-50 space-y-2 w-[calc(100%-1rem)] sm:w-auto sm:max-w-sm"></div>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
let asignacionesPorPedido = [];
let pedidoSeleccionado = null;
let asignacionActual = null;
let ubicacionActual = null;
let novedadActual = null;
let novedades = [];
let pedidoInfoSeleccionado = null; // Guardar información del pedido seleccionado

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
    notificacion.className = `${colores[tipo]} text-white px-3 sm:px-4 py-2 sm:py-3 rounded-lg shadow-lg flex items-center gap-2 animate-slide-up text-xs sm:text-sm`;
    notificacion.innerHTML = `
        <i class="fas ${iconos[tipo]} text-sm sm:text-sm"></i>
        <span class="flex-1">${mensaje}</span>
        <button onclick="cerrarNotificacion('${id}')" class="text-white hover:text-gray-200 flex-shrink-0">
            <i class="fas fa-times text-sm sm:text-sm"></i>
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

// Mostrar/ocultar botón de limpiar cuando hay texto en el input
document.getElementById('inputProducto').addEventListener('input', function(e) {
    const btnLimpiar = document.getElementById('btnLimpiarProducto');
    if (e.target.value.trim() !== '') {
        btnLimpiar.classList.remove('hidden');
    } else {
        btnLimpiar.classList.add('hidden');
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
            <div class="p-2 sm:p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-blue-50 transition ${pedidoSeleccionado?.pedido_id === pedido.pedido_id ? 'bg-blue-50 border-blue-400' : ''}" 
                 onclick="seleccionarPedidoDesdeLista(${pedido.pedido_id})">
                <div class="font-semibold text-gray-700 mb-1 sm:mb-2 text-sm sm:text-sm">Pedido #${pedido.pedido_id}</div>
                <div class="text-xs text-gray-600 space-y-1">
                    <div class="text-xs sm:text-sm">Total: <span class="font-bold">${total}</span></div>
                    <div class="flex flex-wrap gap-1 sm:gap-2">
                        <span class="px-1.5 sm:px-2 py-0.5 sm:py-1 bg-yellow-200 text-orange-700 rounded text-[10px] sm:text-xs">Pend: ${pendientes}</span>
                        <span class="px-1.5 sm:px-2 py-0.5 sm:py-1 bg-blue-100 text-blue-700 rounded text-[10px] sm:text-xs">Proceso: ${enProceso}</span>
                        <span class="px-1.5 sm:px-2 py-0.5 sm:py-1 bg-green-100 text-green-700 rounded text-[10px] sm:text-xs">Espera: ${completadas}</span>
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
        // Guardar información del pedido para usar en el reporte
        pedidoInfoSeleccionado = pedidoSeleccionado.pedido_info || null;
        mostrarAsignacionesDelPedido();
    }
}

function seleccionarPedidoDesdeLista(pedidoId) {
    document.getElementById('selectPedido').value = pedidoId;
    seleccionarPedido(); // Esta función ya guarda pedidoInfoSeleccionado
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
            <div class="font-semibold text-gray-700 mb-2 text-xs sm:text-sm break-words">${asignacionActual.descripcion}</div>
            <div class="space-y-1 text-gray-600 text-xs sm:text-sm">
                <div class="break-all"><span class="font-medium">Código Barras:</span> <span class="font-mono text-[10px] sm:text-xs">${asignacionActual.codigo_barras || 'N/A'}</span></div>
                <div class="break-all"><span class="font-medium">Código Proveedor:</span> <span class="font-mono text-[10px] sm:text-xs">${asignacionActual.codigo_proveedor || 'N/A'}</span></div>
                <div><span class="font-medium">Cantidad Total:</span> ${asignacionActual.cantidad}</div>
                <div><span class="font-medium">Cantidad Asignada:</span> ${asignacionActual.cantidad_asignada}</div>
                <div><span class="font-medium text-orange-600">Cantidad Pendiente:</span> <span class="font-bold">${cantidadPendiente}</span></div>
                <div><span class="font-medium">Estado:</span> <span class="px-2 py-0.5 sm:py-1 bg-${asignacionActual.estado === 'pendiente' ? 'orange' : 'blue'}-100 text-${asignacionActual.estado === 'pendiente' ? 'orange' : 'blue'}-700 rounded text-[10px] sm:text-xs">${asignacionActual.estado === 'pendiente' ? 'Pendiente' : 'En proceso'}</span></div>
            </div>
            <!-- Información de Ubicaciones -->
            <div id="infoUbicacionesProducto" class="mt-3 sm:mt-4 p-2 sm:p-3 bg-gray-50 border border-gray-200 rounded-lg">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs sm:text-sm font-semibold text-gray-700">
                        <i class="fas fa-map-marker-alt mr-1"></i>
                        Ubicaciones del Producto:
                    </span>
                    <span id="cargandoUbicaciones" class="text-xs text-blue-600">
                        <i class="fas fa-spinner fa-spin"></i> Cargando...
                    </span>
                </div>
                <div id="contenidoUbicaciones" class="text-xs sm:text-sm">
                    <!-- Se llenará dinámicamente -->
                </div>
            </div>
            <!-- Botón de Imprimir Ticket -->
            <div class="mt-3 sm:mt-4">
                <button onclick="imprimirTicket()" 
                        class="w-full px-4 py-3 sm:py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg transition flex items-center justify-center text-sm sm:text-sm font-semibold">
                    <i class="fas fa-print mr-2 sm:mr-1 text-sm sm:text-sm"></i>
                    Imprimir Ticket
                </button>
            </div>
        </div>
    `;
    
    document.getElementById('cantidadPendiente').textContent = cantidadPendiente;
    
    // Cargar ubicaciones del producto
    cargarUbicacionesProducto();
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

// Función para limpiar el input del producto
function limpiarInputProducto() {
    document.getElementById('inputProducto').value = '';
    document.getElementById('btnLimpiarProducto').classList.add('hidden');
    document.getElementById('cantidadLlego').classList.add('hidden');
    asignacionActual = null;
    document.getElementById('inputProducto').focus();
    document.getElementById('mensajeProducto').innerHTML = '';
    // Resetear pasos
    document.getElementById('pasoUbicacion').style.display = 'none';
    document.getElementById('pasoCantidad').style.display = 'none';
    document.getElementById('pasoFinalizar').style.display = 'none';
    document.getElementById('infoAsignacion').innerHTML = `
        <div class="text-center text-gray-400 py-6 sm:py-8">
            <i class="fas fa-info-circle text-3xl sm:text-4xl mb-2"></i>
            <p class="text-xs sm:text-sm">Escanea un producto del pedido</p>
        </div>
    `;
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
        // Buscar si el producto ya tiene una novedad registrada
        buscarNovedadProducto(codigo, asignacionActual);
        
        mensaje.innerHTML = `<span class="text-green-600"><i class="fas fa-check-circle"></i> Producto encontrado: ${asignacionActual.descripcion}</span>`;
        // Mostrar cantidad esperada y campo para cantidad real
        document.getElementById('cantidadEsperadaValor').textContent = asignacionActual.cantidad;
        document.getElementById('cantidadLlego').classList.remove('hidden');
        document.getElementById('inputCantidadRealLlego').value = '';
        document.getElementById('inputCantidadRealLlego').focus();
        document.getElementById('mensajeCantidadReal').innerHTML = '';
        // Guardar código para posible registro de novedad
        window.codigoProductoEncontrado = codigo;
        window.cantidadEsperada = asignacionActual.cantidad;
        // Mostrar información de asignación y paso de ubicación inmediatamente
        mostrarInfoAsignacion();
        mostrarPasoUbicacion();
    } else {
        // Producto no encontrado - Mostrar modal para ingresar cantidad
        mensaje.innerHTML = '<span class="text-orange-600"><i class="fas fa-exclamation-triangle"></i> Producto no encontrado en este pedido</span>';
        
        // Guardar código para registrar después
        window.codigoProductoNoEncontrado = codigo;
        
        // Mostrar modal para ingresar cantidad
        document.getElementById('modalCantidadNovedad').classList.remove('hidden');
        document.getElementById('inputCantidadLlegoNovedad').focus();
        
        document.getElementById('cantidadLlego').classList.add('hidden');
        asignacionActual = null;
    }
}

// Buscar si el producto ya tiene una novedad registrada
function buscarNovedadProducto(codigo, asignacion) {
    fetch('/warehouse-inventory/tcr/get-novedades', {
        headers: {
            'X-CSRF-TOKEN': csrfToken
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.estado && data.novedades) {
            // Buscar novedad por código
            const novedad = data.novedades.find(n => {
                const codigoBarras = (n.codigo_barras || '').toUpperCase();
                const codigoProveedor = (n.codigo_proveedor || '').toUpperCase();
                return codigo === codigoBarras || codigo === codigoProveedor;
            });
            
            if (novedad) {
                // Mostrar información de la novedad
                mostrarInfoNovedadProducto(novedad);
            }
        }
    })
    .catch(error => {
        console.error('Error al buscar novedad:', error);
    });
}

// Mostrar información de novedad cuando se escanea un producto
function mostrarInfoNovedadProducto(novedad) {
    const mensaje = document.getElementById('mensajeProducto');
    
    // Obtener la última agregación del historial
    let ultimaAgregacion = null;
    if (novedad.historial && novedad.historial.length > 0) {
        // El historial ya viene ordenado desc, así que el primero es el más reciente
        ultimaAgregacion = novedad.historial[0];
    }
    
    let infoHtml = `
        <div class="bg-purple-50 border-l-4 border-purple-500 p-2 mt-2 rounded">
            <div class="flex items-center mb-1">
                <i class="fas fa-info-circle text-purple-600 mr-2"></i>
                <span class="font-semibold text-purple-700 text-xs">Producto ya registrado en novedades</span>
            </div>
    `;
    
    if (ultimaAgregacion) {
        const fechaAgregacion = new Date(ultimaAgregacion.created_at).toLocaleString('es-VE', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
        const cantidad = parseFloat(ultimaAgregacion.cantidad_agregada) || 0;
        const signo = cantidad >= 0 ? '+' : '';
        const color = cantidad < 0 ? 'text-red-600' : 'text-green-600';
        
        infoHtml += `
            <div class="text-xs text-gray-700">
                <div><span class="font-medium">Última agregación:</span> <span class="${color} font-semibold">${signo}${ultimaAgregacion.cantidad_agregada}</span></div>
                <div><span class="font-medium">Total acumulado:</span> <span class="font-semibold">${ultimaAgregacion.cantidad_total || novedad.cantidad_llego || 0}</span></div>
                <div><span class="font-medium">Fecha/Hora:</span> ${fechaAgregacion}</div>
            </div>
        `;
    } else {
        // Si no hay historial, mostrar la cantidad total
        infoHtml += `
            <div class="text-xs text-gray-700">
                <div><span class="font-medium">Cantidad total registrada:</span> <span class="font-semibold">${novedad.cantidad_llego || 0}</span></div>
            </div>
        `;
    }
    
    infoHtml += `</div>`;
    
    mensaje.innerHTML += infoHtml;
}

// Confirmar registrar novedad con cantidad
function confirmarRegistrarNovedad() {
    const cantidad = parseFloat(document.getElementById('inputCantidadLlegoNovedad').value);
    const mensaje = document.getElementById('mensajeCantidadNovedad');
    
    if (isNaN(cantidad) || cantidad === 0) {
        mensaje.innerHTML = '<span class="text-red-600">Ingrese una cantidad válida (diferente de cero)</span>';
        return;
    }
    
    if (!window.codigoProductoNoEncontrado) {
        mensaje.innerHTML = '<span class="text-red-600">Error: No hay código de producto</span>';
        return;
    }
    
    mensaje.innerHTML = '<span class="text-blue-600"><i class="fas fa-spinner fa-spin"></i> Registrando novedad...</span>';
    
    fetch('/warehouse-inventory/tcr/buscar-o-registrar-novedad', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            codigo: window.codigoProductoNoEncontrado,
            pedido_id: pedidoSeleccionado ? pedidoSeleccionado.pedido_id : null,
            cantidad_llego: cantidad
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.estado) {
            mostrarNotificacion('✅ Novedad registrada exitosamente', 'success');
            // Limpiar todo
            cancelarRegistrarNovedad();
            // Actualizar reporte de novedades inmediatamente (tiempo real)
            setTimeout(() => {
                cargarNovedades();
            }, 500);
        } else {
            mensaje.innerHTML = `<span class="text-red-600">${data.msj || 'Error al registrar novedad'}</span>`;
        }
    })
    .catch(error => {
        console.error('Error al registrar novedad:', error);
        mensaje.innerHTML = '<span class="text-red-600">Error al registrar novedad</span>';
    });
}

// Cancelar registro de novedad
function cancelarRegistrarNovedad() {
    document.getElementById('modalCantidadNovedad').classList.add('hidden');
    document.getElementById('inputCantidadLlegoNovedad').value = '';
    document.getElementById('mensajeCantidadNovedad').innerHTML = '';
    window.codigoProductoNoEncontrado = null;
    document.getElementById('inputProducto').value = '';
    document.getElementById('btnLimpiarProducto').classList.add('hidden');
    document.getElementById('inputProducto').focus();
}

// Event listener para Enter en el campo de cantidad de novedad
document.getElementById('inputCantidadLlegoNovedad')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        confirmarRegistrarNovedad();
    }
});

// Event listener para Enter en el campo de cantidad real
document.getElementById('inputCantidadRealLlego')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        verificarCantidadReal();
    }
});

// Verificar cantidad real vs esperada
function verificarCantidadReal() {
    const cantidadReal = parseFloat(document.getElementById('inputCantidadRealLlego').value);
    const mensaje = document.getElementById('mensajeCantidadReal');
    
    if (isNaN(cantidadReal) || cantidadReal === 0) {
        mensaje.innerHTML = '<span class="text-red-600">Ingrese una cantidad válida (diferente de cero)</span>';
        return;
    }
    
    if (!asignacionActual || !window.cantidadEsperada) {
        mensaje.innerHTML = '<span class="text-red-600">Error: No hay producto seleccionado</span>';
        return;
    }
    
    const cantidadEsperada = parseFloat(window.cantidadEsperada);
    const diferencia = cantidadReal - cantidadEsperada;
    
    // Siempre mostrar la información de la asignación y el botón de imprimir
    mostrarInfoAsignacion();
    
    if (diferencia === 0) {
        // Cantidad correcta - registrar como novedad con diferencia cero
        mensaje.innerHTML = `
            <div class="bg-green-50 border border-green-300 rounded-lg p-3 mt-2">
                <div class="flex items-center mb-2">
                    <i class="fas fa-check-circle text-green-600 mr-2"></i>
                    <span class="font-semibold text-green-700">Cantidad correcta</span>
                </div>
                <p class="text-xs text-gray-600 mb-3">Registrando como novedad correcta...</p>
                <div class="flex gap-2">
                    <button onclick="continuarConAsignacion()" 
                            class="flex-1 px-3 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg transition text-sm">
                        <i class="fas fa-map-marker-alt mr-1"></i>Asignar Ubicación
                    </button>
                    <button onclick="omitirUbicacionYContinuar()" 
                            class="flex-1 px-3 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition text-sm">
                        <i class="fas fa-arrow-right mr-1"></i>Omitir y Continuar
                    </button>
                </div>
            </div>
        `;
        
        // Registrar como novedad con diferencia cero
        registrarNovedadCorrecta(cantidadReal, cantidadEsperada);
    } else {
        // Hay diferencia - OBLIGATORIO registrar novedad, no se puede omitir
        const tipoDiferencia = diferencia > 0 ? 'sobrante' : 'faltante';
        const mensajeDiferencia = diferencia > 0 
            ? `Sobrante de ${Math.abs(diferencia)}` 
            : `Faltante de ${Math.abs(diferencia)}`;
        
        mensaje.innerHTML = `
            <div class="bg-yellow-100 border border-orange-300 rounded-lg p-3 mt-2">
                <div class="flex items-center mb-2">
                    <i class="fas fa-exclamation-triangle text-orange-600 mr-2"></i>
                    <span class="font-semibold text-orange-700">${mensajeDiferencia} detectado</span>
                </div>
                <p class="text-xs text-gray-600 mb-3">Debe registrar la novedad antes de continuar</p>
                <button onclick="registrarDiferenciaYContinuar(${cantidadReal}, ${diferencia}, '${tipoDiferencia}')" 
                        class="w-full px-4 py-3 bg-orange-500 hover:bg-orange-600 text-white rounded-lg transition text-sm font-semibold">
                    <i class="fas fa-save mr-2"></i>Registrar Novedad y Continuar
                </button>
            </div>
        `;
    }
}

// Continuar con asignación normal (procesar ubicación)
function continuarConAsignacion() {
    mostrarInfoAsignacion();
    mostrarPasoUbicacion();
    const mensaje = document.getElementById('mensajeCantidadReal');
    if (mensaje) {
        mensaje.innerHTML = '<span class="text-blue-600"><i class="fas fa-info-circle"></i> Continuando con asignación...</span>';
    }
}

// Omitir ubicación y continuar con otro producto
function omitirUbicacionYContinuar() {
    mostrarNotificacion('Ubicación omitida. Puede continuar escaneando otro producto.', 'info');
    resetearFormularioProducto();
}

// Registrar novedad correcta (diferencia cero)
function registrarNovedadCorrecta(cantidadReal, cantidadEsperada) {
    if (!window.codigoProductoEncontrado || !asignacionActual) {
        return; // No hay información suficiente
    }
    
    fetch('/warehouse-inventory/tcr/buscar-o-registrar-novedad', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            codigo: window.codigoProductoEncontrado,
            pedido_id: pedidoSeleccionado ? pedidoSeleccionado.pedido_id : null,
            cantidad_llego: cantidadReal,
            observaciones: `Cantidad correcta. Cantidad esperada: ${cantidadEsperada}, Cantidad real: ${cantidadReal}`
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.estado) {
            // Actualizar reporte de novedades
            setTimeout(() => {
                cargarNovedades();
            }, 500);
        }
    })
    .catch(error => {
        console.error('Error al registrar novedad correcta:', error);
    });
}

// Registrar diferencia y continuar con otro producto
function registrarDiferenciaYContinuar(cantidadReal, diferencia, tipoDiferencia) {
    if (!window.codigoProductoEncontrado || !asignacionActual) {
        mostrarNotificacion('Error: No hay información del producto', 'error');
        return;
    }
    
    const mensaje = document.getElementById('mensajeCantidadReal');
    mensaje.innerHTML = '<span class="text-blue-600"><i class="fas fa-spinner fa-spin"></i> Registrando novedad...</span>';
    
    fetch('/warehouse-inventory/tcr/buscar-o-registrar-novedad', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            codigo: window.codigoProductoEncontrado,
            pedido_id: pedidoSeleccionado ? pedidoSeleccionado.pedido_id : null,
            cantidad_llego: cantidadReal,
            observaciones: `Diferencia detectada: ${tipoDiferencia} de ${Math.abs(diferencia)}. Cantidad esperada: ${window.cantidadEsperada}, Cantidad real: ${cantidadReal}`
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.estado) {
            const mensajeTipo = tipoDiferencia === 'sobrante' 
                ? `✅ Sobrante de ${Math.abs(diferencia)} registrado como novedad`
                : `⚠️ Faltante de ${Math.abs(diferencia)} registrado como novedad`;
            mostrarNotificacion(mensajeTipo, tipoDiferencia === 'sobrante' ? 'success' : 'warning');
            
            // Limpiar formulario y continuar con otro producto
            resetearFormularioProducto();
            
            // Actualizar reporte de novedades
            setTimeout(() => {
                cargarNovedades();
            }, 500);
        } else {
            mensaje.innerHTML = `<span class="text-red-600">Error: ${data.msj || 'Error desconocido'}</span>`;
        }
    })
    .catch(error => {
        console.error('Error al registrar diferencia:', error);
        mensaje.innerHTML = '<span class="text-red-600">Error al registrar diferencia como novedad</span>';
    });
}

// Resetear formulario de producto para continuar con otro
function resetearFormularioProducto() {
    asignacionActual = null;
    ubicacionActual = null;
    window.codigoProductoEncontrado = null;
    window.cantidadEsperada = null;
    
    // Limpiar inputs
    document.getElementById('inputProducto').value = '';
    document.getElementById('inputUbicacion').value = '';
    document.getElementById('inputCantidad').value = '';
    document.getElementById('inputCantidadRealLlego').value = '';
    
    // Ocultar elementos
    document.getElementById('btnLimpiarProducto').classList.add('hidden');
    document.getElementById('cantidadLlego').classList.add('hidden');
    document.getElementById('pasoUbicacion').style.display = 'none';
    document.getElementById('pasoCantidad').style.display = 'none';
    document.getElementById('pasoFinalizar').style.display = 'none';
    document.getElementById('mensajeProducto').innerHTML = '';
    document.getElementById('mensajeCantidadReal').innerHTML = '';
    
    // Resetear info de asignación
    document.getElementById('infoAsignacion').innerHTML = `
        <div class="text-center text-gray-400 py-6 sm:py-8">
            <i class="fas fa-info-circle text-3xl sm:text-4xl mb-2"></i>
            <p class="text-xs sm:text-sm">Escanea un producto del pedido</p>
        </div>
    `;
    
    // Volver al paso de escanear producto
    document.getElementById('pasoProducto').style.display = 'block';
    document.getElementById('inputProducto').focus();
}

// Registrar diferencia como novedad
function registrarDiferenciaComoNovedad(cantidadReal, diferencia, tipoDiferencia) {
    if (!window.codigoProductoEncontrado || !asignacionActual) {
        mostrarNotificacion('Error: No hay información del producto', 'error');
        return;
    }
    
    fetch('/warehouse-inventory/tcr/buscar-o-registrar-novedad', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            codigo: window.codigoProductoEncontrado,
            pedido_id: pedidoSeleccionado ? pedidoSeleccionado.pedido_id : null,
            cantidad_llego: cantidadReal,
            observaciones: `Diferencia detectada: ${tipoDiferencia} de ${Math.abs(diferencia)}. Cantidad esperada: ${window.cantidadEsperada}, Cantidad real: ${cantidadReal}`
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.estado) {
            const mensajeTipo = tipoDiferencia === 'sobrante' 
                ? `✅ Sobrante de ${Math.abs(diferencia)} registrado como novedad`
                : `⚠️ Faltante de ${Math.abs(diferencia)} registrado como novedad`;
            mostrarNotificacion(mensajeTipo, tipoDiferencia === 'sobrante' ? 'success' : 'warning');
            
            // Continuar con el flujo normal usando la cantidad real
            mostrarInfoAsignacion();
            mostrarPasoUbicacion();
            
            // Actualizar reporte de novedades
            setTimeout(() => {
                cargarNovedades();
            }, 500);
        } else {
            mostrarNotificacion('Error al registrar diferencia: ' + (data.msj || 'Error desconocido'), 'error');
        }
    })
    .catch(error => {
        console.error('Error al registrar diferencia:', error);
        mostrarNotificacion('Error al registrar diferencia como novedad', 'error');
    });
}

// Función para cargar las ubicaciones del producto
function cargarUbicacionesProducto() {
    if (!asignacionActual) return;
    
    const codigo = asignacionActual.codigo_barras || asignacionActual.codigo_proveedor;
    if (!codigo) {
        mostrarUbicacionesError('No se puede buscar ubicaciones sin código de producto');
        return;
    }
    
    // Buscar el producto por código para obtener su inventario_id
    fetch('/inventario/buscar-producto-inventariar', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({ codigo: codigo })
    })
    .then(response => response.json())
    .then(data => {
        const cargando = document.getElementById('cargandoUbicaciones');
        const contenido = document.getElementById('contenidoUbicaciones');
        
        if (cargando) cargando.style.display = 'none';
        
        if (data.estado && data.producto) {
            const producto = data.producto;
            const numeroUbicaciones = producto.numero_ubicaciones || 0;
            const totalEnUbicaciones = producto.total_en_ubicaciones || 0;
            const ubicaciones = producto.ubicaciones || [];
            
            if (numeroUbicaciones === 0) {
                contenido.innerHTML = `
                    <div class="p-2 bg-yellow-50 border border-yellow-300 rounded text-yellow-800">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        <span class="font-semibold">Este producto no tiene ubicaciones asignadas</span>
                    </div>
                `;
            } else {
                let htmlUbicaciones = `
                    <div class="mb-2">
                        <span class="font-semibold text-gray-700">Total en ubicaciones: </span>
                        <span class="font-bold text-blue-600">${totalEnUbicaciones}</span>
                        <span class="text-gray-600 ml-2">(${numeroUbicaciones} ubicación${numeroUbicaciones > 1 ? 'es' : ''})</span>
                    </div>
                    <div class="space-y-1 max-h-32 sm:max-h-40 overflow-y-auto">
                `;
                
                ubicaciones.forEach(ubicacion => {
                    htmlUbicaciones += `
                        <div class="flex justify-between items-center p-1.5 bg-white border border-gray-200 rounded text-xs">
                            <span class="font-mono font-semibold text-gray-800">${ubicacion.codigo}</span>
                            <span class="font-bold text-green-600">${ubicacion.cantidad}</span>
                        </div>
                    `;
                });
                
                htmlUbicaciones += '</div>';
                contenido.innerHTML = htmlUbicaciones;
            }
        } else {
            mostrarUbicacionesError('No se pudo obtener información de ubicaciones');
        }
    })
    .catch(error => {
        console.error('Error al cargar ubicaciones:', error);
        mostrarUbicacionesError('Error al cargar ubicaciones del producto');
    });
}

function mostrarUbicacionesError(mensaje) {
    const cargando = document.getElementById('cargandoUbicaciones');
    const contenido = document.getElementById('contenidoUbicaciones');
    
    if (cargando) cargando.style.display = 'none';
    if (contenido) {
        contenido.innerHTML = `
            <div class="p-2 bg-red-50 border border-red-300 rounded text-red-800 text-xs">
                <i class="fas fa-exclamation-circle mr-1"></i>
                ${mensaje}
            </div>
        `;
    }
}

function mostrarPasoCantidad() {
    document.getElementById('pasoCantidad').style.display = 'block';
    document.getElementById('pasoFinalizar').style.display = 'block';
    document.getElementById('inputCantidad').focus();
    const cantidadPendiente = asignacionActual.cantidad - asignacionActual.cantidad_asignada;
    document.getElementById('inputCantidad').max = cantidadPendiente;
}

function mostrarBusquedaManual() {
    document.getElementById('buscadorManual').classList.remove('hidden');
    document.getElementById('inputBusquedaManual').value = '';
    document.getElementById('inputBusquedaManual').focus();
    filtrarProductosManual(); // Mostrar todos inicialmente
}

function ocultarBusquedaManual() {
    document.getElementById('buscadorManual').classList.add('hidden');
    document.getElementById('inputProducto').focus();
}

function filtrarProductosManual() {
    const termino = document.getElementById('inputBusquedaManual').value.toLowerCase();
    const lista = document.getElementById('listaProductosManual');
    
    if (!pedidoSeleccionado) return;
    
    // Filtrar asignaciones pendientes
    const productosFiltrados = pedidoSeleccionado.asignaciones.filter(a => {
        const pendiente = (a.cantidad - a.cantidad_asignada) > 0;
        const coincide = a.descripcion.toLowerCase().includes(termino) || 
                        (a.codigo_barras || '').toLowerCase().includes(termino) || 
                        (a.codigo_proveedor || '').toLowerCase().includes(termino);
        return pendiente && coincide;
    });
    
    if (productosFiltrados.length === 0) {
        lista.innerHTML = '<div class="text-center text-gray-400 py-4 text-sm">No se encontraron productos pendientes</div>';
        return;
    }
    
    lista.innerHTML = productosFiltrados.map(p => `
        <div onclick="seleccionarProductoManual(${p.id})" class="p-2 sm:p-3 bg-white border border-gray-200 rounded hover:bg-blue-50 cursor-pointer transition flex flex-col gap-1">
            <div class="font-semibold text-xs sm:text-sm text-gray-800 break-words">${p.descripcion}</div>
            <div class="flex flex-col sm:flex-row sm:justify-between gap-1 text-[10px] sm:text-xs text-gray-500">
                <span class="break-all">Cod: ${p.codigo_barras || p.codigo_proveedor || 'N/A'}</span>
                <span class="font-bold text-orange-600">Pend: ${(p.cantidad - p.cantidad_asignada)}</span>
            </div>
        </div>
    `).join('');
}

function seleccionarProductoManual(asignacionId) {
    asignacionActual = pedidoSeleccionado.asignaciones.find(a => a.id === asignacionId);
    if (asignacionActual) {
        const mensaje = document.getElementById('mensajeProducto');
        mensaje.innerHTML = `<span class="text-green-600"><i class="fas fa-check-circle"></i> Producto seleccionado manualmente: ${asignacionActual.descripcion}</span>`;
        
        // Mostrar cantidad que llegó
        document.getElementById('cantidadLlegoValor').textContent = asignacionActual.cantidad;
        document.getElementById('cantidadLlego').classList.remove('hidden');
        
        // Poner el código en el input para que se muestre el botón X
        const codigo = asignacionActual.codigo_barras || asignacionActual.codigo_proveedor || '';
        document.getElementById('inputProducto').value = codigo;
        document.getElementById('btnLimpiarProducto').classList.remove('hidden');
        
        ocultarBusquedaManual();
        mostrarInfoAsignacion();
        mostrarPasoUbicacion();
    }
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
                if (data.completado) {
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
    document.getElementById('btnLimpiarProducto').classList.add('hidden');
    document.getElementById('cantidadLlego').classList.add('hidden');
    document.getElementById('pasoUbicacion').style.display = 'none';
    document.getElementById('pasoProducto').style.display = 'block'; // Volver al paso de escanear producto
    document.getElementById('pasoCantidad').style.display = 'none';
    document.getElementById('pasoFinalizar').style.display = 'none';
    document.getElementById('inputProducto').focus();
    document.getElementById('infoAsignacion').innerHTML = `
        <div class="text-center text-gray-400 py-6 sm:py-8">
            <i class="fas fa-info-circle text-3xl sm:text-4xl mb-2"></i>
            <p class="text-xs sm:text-sm">Escanea un producto del pedido</p>
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
    document.getElementById('btnLimpiarProducto').classList.add('hidden');
    document.getElementById('cantidadLlego').classList.add('hidden');
    document.getElementById('pasoSeleccionarPedido').style.display = 'block';
    document.getElementById('pasoUbicacion').style.display = 'none';
    document.getElementById('pasoProducto').style.display = 'none';
    document.getElementById('pasoCantidad').style.display = 'none';
    document.getElementById('pasoFinalizar').style.display = 'none';
    document.getElementById('infoAsignacion').innerHTML = `
        <div class="text-center text-gray-400 py-6 sm:py-8">
            <i class="fas fa-info-circle text-3xl sm:text-4xl mb-2"></i>
            <p class="text-xs sm:text-sm">Seleccione un pedido para comenzar</p>
        </div>
    `;
}

function imprimirTicket() {
    if (!asignacionActual) {
        mostrarNotificacion('No hay producto seleccionado', 'warning');
        return;
    }
    
    // Crear formulario temporal para enviar los datos
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/warehouse-inventory/imprimir-ticket-producto';
    form.target = '_blank';
    
    // Agregar token CSRF
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = '_token';
    csrfInput.value = csrfToken;
    form.appendChild(csrfInput);
    
    // Agregar datos del producto
    const campos = [
        { name: 'codigo_barras', value: asignacionActual.codigo_barras || '' },
        { name: 'codigo_proveedor', value: asignacionActual.codigo_proveedor || '' },
        { name: 'descripcion', value: asignacionActual.descripcion || '' },
        { name: 'marca', value: asignacionActual.marca || '' }
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

// ============ FUNCIONES PARA NOVEDADES ============

// Cambiar entre tabs
function mostrarTab(tab) {
    // Resetear todos los tabs
    const tabAsignaciones = document.getElementById('tabAsignaciones');
    const tabNovedades = document.getElementById('tabNovedades');
    const tabPedidos = document.getElementById('tabPedidos');
    const panelAsignaciones = document.getElementById('panelAsignaciones');
    const panelNovedades = document.getElementById('panelNovedades');
    const panelPedidos = document.getElementById('panelPedidos');
    
    // Resetear estilos de todos los tabs
    [tabAsignaciones, tabNovedades, tabPedidos].forEach(t => {
        t.classList.remove('border-blue-500', 'text-blue-600');
        t.classList.add('border-transparent', 'text-gray-500');
    });
    
    // Ocultar todos los panels
    [panelAsignaciones, panelNovedades, panelPedidos].forEach(p => {
        if (p) p.classList.add('hidden');
    });
    
    // Activar el tab seleccionado
    if (tab === 'asignaciones') {
        tabAsignaciones.classList.add('border-blue-500', 'text-blue-600');
        tabAsignaciones.classList.remove('border-transparent', 'text-gray-500');
        if (panelAsignaciones) panelAsignaciones.classList.remove('hidden');
        if (document.getElementById('infoAsignacionContainer')) {
            document.getElementById('infoAsignacionContainer').classList.remove('hidden');
        }
    } else if (tab === 'novedades') {
        tabNovedades.classList.add('border-blue-500', 'text-blue-600');
        tabNovedades.classList.remove('border-transparent', 'text-gray-500');
        if (panelNovedades) panelNovedades.classList.remove('hidden');
        if (document.getElementById('infoAsignacionContainer')) {
            document.getElementById('infoAsignacionContainer').classList.add('hidden');
        }
        cargarNovedades();
    } else if (tab === 'pedidos') {
        tabPedidos.classList.add('border-blue-500', 'text-blue-600');
        tabPedidos.classList.remove('border-transparent', 'text-gray-500');
        if (panelPedidos) panelPedidos.classList.remove('hidden');
        if (document.getElementById('infoAsignacionContainer')) {
            document.getElementById('infoAsignacionContainer').classList.add('hidden');
        }
        cargarAsignaciones();
    }
    
    // El panel de reporte de novedades siempre está visible
}

// Event listener para escanear producto en novedades
document.getElementById('inputProductoNovedad')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        buscarORegistrarNovedad();
    }
});

// Mostrar/ocultar botón de limpiar cuando hay texto
document.getElementById('inputProductoNovedad')?.addEventListener('input', function(e) {
    const btnLimpiar = document.getElementById('btnLimpiarProductoNovedad');
    if (e.target.value.trim() !== '') {
        btnLimpiar.classList.remove('hidden');
    } else {
        btnLimpiar.classList.add('hidden');
    }
});

// Buscar o registrar novedad
function buscarORegistrarNovedad() {
    const codigo = document.getElementById('inputProductoNovedad').value.trim();
    const mensaje = document.getElementById('mensajeProductoNovedad');
    
    if (!codigo) {
        mensaje.innerHTML = '<span class="text-red-600">Ingrese un código de producto</span>';
        return;
    }
    
    mensaje.innerHTML = '<span class="text-blue-600"><i class="fas fa-spinner fa-spin"></i> Buscando producto...</span>';
    
    fetch('/warehouse-inventory/tcr/buscar-o-registrar-novedad', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            codigo: codigo
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.estado) {
            novedadActual = data.novedad;
            mostrarInfoNovedad(data.novedad, data.existe);
            if (data.existe) {
                mensaje.innerHTML = '<span class="text-yellow-600"><i class="fas fa-info-circle"></i> Producto ya registrado. Puede agregar más cantidad.</span>';
            } else {
                mensaje.innerHTML = '<span class="text-green-600"><i class="fas fa-check-circle"></i> Novedad registrada exitosamente</span>';
            }
            cargarNovedades();
        } else {
            mensaje.innerHTML = `<span class="text-red-600"><i class="fas fa-times-circle"></i> ${data.msj}</span>`;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mensaje.innerHTML = '<span class="text-red-600">Error al buscar producto</span>';
    });
}

// Mostrar información de la novedad
function mostrarInfoNovedad(novedad, existe) {
    document.getElementById('novedadDescripcion').textContent = novedad.descripcion;
    document.getElementById('novedadCodigoBarras').textContent = novedad.codigo_barras || 'N/A';
    document.getElementById('novedadCodigoProveedor').textContent = novedad.codigo_proveedor || 'N/A';
    document.getElementById('novedadCantidadLlego').textContent = novedad.cantidad_llego || 0;
    document.getElementById('novedadCantidadEnviada').textContent = novedad.cantidad_enviada || 0;
    document.getElementById('novedadDiferencia').textContent = novedad.diferencia || 0;
    
    // Si no tiene cantidad que llegó o es nueva, mostrar campo para ingresarla
    if (!existe || !novedad.cantidad_llego || novedad.cantidad_llego == 0) {
        document.getElementById('campoCantidadLlego').classList.remove('hidden');
        document.getElementById('btnEditarCantidadLlego').classList.add('hidden');
        document.getElementById('inputCantidadLlego').value = novedad.cantidad_llego || '';
    } else {
        document.getElementById('campoCantidadLlego').classList.add('hidden');
        document.getElementById('btnEditarCantidadLlego').classList.remove('hidden');
    }
    
    // Mostrar historial si existe
    if (novedad.historial && novedad.historial.length > 0) {
        mostrarHistorialNovedad(novedad.historial);
    } else {
        document.getElementById('novedadHistorial').classList.add('hidden');
    }
    
    // Actualizar mensaje de corrección con el máximo permitido
    const mensajeCorreccion = document.getElementById('mensajeCorreccion');
    if (mensajeCorreccion) {
        const cantidadTotal = parseFloat(novedad.cantidad_llego) || 0;
        mensajeCorreccion.innerHTML = `
            <i class="fas fa-info-circle mr-1"></i>
            Use números negativos para corregir (máximo: -${cantidadTotal})
        `;
    }
    
    document.getElementById('infoNovedad').classList.remove('hidden');
    document.getElementById('inputCantidadNovedad').focus();
}

// Mostrar campo para editar cantidad que llegó
function mostrarEditarCantidadLlego() {
    document.getElementById('campoCantidadLlego').classList.remove('hidden');
    document.getElementById('btnEditarCantidadLlego').classList.add('hidden');
    document.getElementById('inputCantidadLlego').value = novedadActual.cantidad_llego || '';
    document.getElementById('inputCantidadLlego').focus();
}

// Guardar cantidad que llegó
function guardarCantidadLlego() {
    if (!novedadActual) {
        mostrarNotificacion('No hay novedad seleccionada', 'warning');
        return;
    }
    
    const cantidad = parseFloat(document.getElementById('inputCantidadLlego').value);
    if (isNaN(cantidad) || cantidad < 0) {
        mostrarNotificacion('Ingrese una cantidad válida', 'warning');
        return;
    }
    
    fetch('/warehouse-inventory/tcr/actualizar-cantidad-llego-novedad', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            novedad_id: novedadActual.id,
            cantidad_llego: cantidad
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.estado) {
            novedadActual = data.novedad;
            document.getElementById('novedadCantidadLlego').textContent = data.novedad.cantidad_llego;
            document.getElementById('novedadDiferencia').textContent = data.novedad.diferencia;
            document.getElementById('campoCantidadLlego').classList.add('hidden');
            document.getElementById('btnEditarCantidadLlego').classList.remove('hidden');
            mostrarNotificacion('Cantidad que llegó actualizada', 'success');
            cargarNovedades();
        } else {
            mostrarNotificacion(data.msj || 'Error al actualizar', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarNotificacion('Error al actualizar cantidad', 'error');
    });
}

// Mostrar historial de agregaciones
function mostrarHistorialNovedad(historial) {
    const lista = document.getElementById('novedadHistorialLista');
    if (!historial || historial.length === 0) {
        lista.innerHTML = '<div class="text-xs text-gray-400 italic text-center py-2">No hay historial de agregaciones</div>';
        return;
    }
    
    lista.innerHTML = `
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="bg-gray-100 border-b border-gray-300">
                        <th class="px-2 py-1.5 text-left font-semibold text-gray-700">Fecha/Hora</th>
                        <th class="px-2 py-1.5 text-center font-semibold text-gray-700">Cantidad</th>
                        <th class="px-2 py-1.5 text-center font-semibold text-gray-700">Total</th>
                    </tr>
                </thead>
                <tbody>
                    ${historial.map((h, index) => {
                        const fecha = new Date(h.created_at).toLocaleString('es-VE', {
                            day: '2-digit',
                            month: '2-digit',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                        const cantidad = parseFloat(h.cantidad_agregada) || 0;
                        const esNegativo = cantidad < 0;
                        const signo = cantidad >= 0 ? '+' : '';
                        const color = esNegativo ? 'text-red-600' : 'text-green-600';
                        const bgColor = index % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                        return `
                            <tr class="${bgColor} border-b border-gray-200 hover:bg-gray-100">
                                <td class="px-2 py-1.5 text-gray-600">${fecha}</td>
                                <td class="px-2 py-1.5 text-center ${color} font-semibold">${signo}${h.cantidad_agregada}</td>
                                <td class="px-2 py-1.5 text-center font-semibold text-gray-700">${h.cantidad_total || h.cantidad_despues || 0}</td>
                            </tr>
                        `;
                    }).join('')}
                </tbody>
            </table>
        </div>
    `;
    document.getElementById('novedadHistorial').classList.remove('hidden');
}

// Agregar cantidad a novedad
function agregarCantidadNovedad() {
    if (!novedadActual) {
        mostrarNotificacion('No hay novedad seleccionada', 'warning');
        return;
    }
    
    const cantidad = parseFloat(document.getElementById('inputCantidadNovedad').value);
    if (isNaN(cantidad) || cantidad === 0) {
        mostrarNotificacion('Ingrese una cantidad válida (diferente de cero)', 'warning');
        return;
    }
    
    // Si es negativo, validar que no exceda el total cargado
    if (cantidad < 0) {
        const cantidadTotalCargada = parseFloat(novedadActual.cantidad_llego) || 0;
        const valorAbsoluto = Math.abs(cantidad);
        
        if (valorAbsoluto > cantidadTotalCargada) {
            mostrarNotificacion(`No puede restar más de ${cantidadTotalCargada} (cantidad total cargada)`, 'error');
            return;
        }
    }
    
    fetch('/warehouse-inventory/tcr/agregar-cantidad-novedad', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            novedad_id: novedadActual.id,
            cantidad: cantidad
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.estado) {
            novedadActual = data.novedad;
            mostrarInfoNovedad(data.novedad, true);
            document.getElementById('inputCantidadNovedad').value = '';
            const mensaje = cantidad > 0 
                ? `Cantidad agregada: +${cantidad}` 
                : `Cantidad corregida: ${cantidad}`;
            mostrarNotificacion(mensaje, 'success');
            // Recargar novedades para actualizar el badge en el reporte
            cargarNovedades();
        } else {
            mostrarNotificacion(data.msj || 'Error al agregar cantidad', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarNotificacion('Error al agregar cantidad', 'error');
    });
}

// Cargar todas las novedades para el reporte
function cargarNovedades() {
    fetch('/warehouse-inventory/tcr/get-novedades', {
        headers: {
            'X-CSRF-TOKEN': csrfToken
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.estado) {
            novedades = data.novedades;
            mostrarReporteNovedades(data.novedades);
        }
    })
    .catch(error => {
        console.error('Error al cargar novedades:', error);
    });
}

// Mostrar reporte de novedades
function mostrarReporteNovedades(novedades) {
    const reporte = document.getElementById('reporteNovedades');
    
    if (novedades.length === 0) {
        reporte.innerHTML = `
            <div class="text-center text-gray-400 py-4">
                <i class="fas fa-info-circle text-xl mb-2"></i>
                <p>No hay novedades registradas</p>
            </div>
        `;
        return;
    }
    
    reporte.innerHTML = novedades.map(n => {
        const fecha = new Date(n.created_at).toLocaleString('es-VE', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
        
        // Determinar tipo de novedad basado en observaciones
        let tipoNovedad = 'normal';
        let badgeColor = 'bg-gray-100 text-gray-700';
        let badgeText = 'Novedad';
        let badgeIcon = 'fa-info-circle';
        
        if (n.observaciones && typeof n.observaciones === 'string') {
            if (n.observaciones.includes('Sobrante')) {
                tipoNovedad = 'sobrante';
                badgeColor = 'bg-blue-100 text-blue-700';
                badgeText = 'Sobrante';
                badgeIcon = 'fa-plus-circle';
            } else if (n.observaciones.includes('Faltante')) {
                tipoNovedad = 'faltante';
                badgeColor = 'bg-red-100 text-red-700';
                badgeText = 'Faltante';
                badgeIcon = 'fa-minus-circle';
            } else if (n.observaciones.includes('Diferencia detectada')) {
                if (n.observaciones.includes('sobrante')) {
                    tipoNovedad = 'sobrante';
                    badgeColor = 'bg-blue-100 text-blue-700';
                    badgeText = 'Sobrante';
                    badgeIcon = 'fa-plus-circle';
                } else if (n.observaciones.includes('faltante')) {
                    tipoNovedad = 'faltante';
                    badgeColor = 'bg-red-100 text-red-700';
                    badgeText = 'Faltante';
                    badgeIcon = 'fa-minus-circle';
                }
            }
        }
        
        // Determinar tipo de novedad basado en la diferencia actual (no solo observaciones)
        if (n.diferencia == 0 && n.cantidad_llego > 0) {
            // Si diferencia es 0, es correcto
            tipoNovedad = 'correcto';
            badgeColor = 'bg-green-100 text-green-700';
            badgeText = 'Correcto';
            badgeIcon = 'fa-check-circle';
        } else if (n.diferencia > 0 && n.cantidad_enviada > 0) {
            // Si diferencia es positiva y hay cantidad enviada, es sobrante
            tipoNovedad = 'sobrante';
            badgeColor = 'bg-blue-100 text-blue-700';
            badgeText = 'Sobrante';
            badgeIcon = 'fa-plus-circle';
        } else if (n.diferencia < 0 && n.cantidad_enviada > 0) {
            // Si diferencia es negativa y hay cantidad enviada, es faltante
            tipoNovedad = 'faltante';
            badgeColor = 'bg-red-100 text-red-700';
            badgeText = 'Faltante';
            badgeIcon = 'fa-minus-circle';
        }
        
        // Si las observaciones tienen información más específica, usarla como respaldo
        if ((!tipoNovedad || tipoNovedad === 'normal') && n.observaciones && typeof n.observaciones === 'string') {
            if (n.observaciones.includes('Sobrante')) {
                tipoNovedad = 'sobrante';
                badgeColor = 'bg-blue-100 text-blue-700';
                badgeText = 'Sobrante';
                badgeIcon = 'fa-plus-circle';
            } else if (n.observaciones.includes('Faltante')) {
                tipoNovedad = 'faltante';
                badgeColor = 'bg-red-100 text-red-700';
                badgeText = 'Faltante';
                badgeIcon = 'fa-minus-circle';
            } else if (n.observaciones.includes('Diferencia detectada')) {
                if (n.observaciones.includes('sobrante')) {
                    tipoNovedad = 'sobrante';
                    badgeColor = 'bg-blue-100 text-blue-700';
                    badgeText = 'Sobrante';
                    badgeIcon = 'fa-plus-circle';
                } else if (n.observaciones.includes('faltante')) {
                    tipoNovedad = 'faltante';
                    badgeColor = 'bg-red-100 text-red-700';
                    badgeText = 'Faltante';
                    badgeIcon = 'fa-minus-circle';
                }
            }
        }
        
        const historialHtml = n.historial && n.historial.length > 0 ? `
            <div class="mt-2">
                <div class="font-semibold mb-1 text-[10px] text-gray-700">Historial de agregaciones:</div>
                <div class="overflow-x-auto">
                    <table class="w-full text-[10px] border border-gray-200 rounded">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="px-1 py-0.5 text-left border-b border-gray-300">Hora</th>
                                <th class="px-1 py-0.5 text-center border-b border-gray-300">Cantidad</th>
                                <th class="px-1 py-0.5 text-center border-b border-gray-300">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${n.historial.map((h, idx) => {
                                const hFecha = new Date(h.created_at).toLocaleTimeString('es-VE', { hour: '2-digit', minute: '2-digit' });
                                const cantidad = parseFloat(h.cantidad_agregada) || 0;
                                const signo = cantidad >= 0 ? '+' : '';
                                const color = cantidad < 0 ? 'text-red-600' : 'text-green-600';
                                const bgColor = idx % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                                return `
                                    <tr class="${bgColor}">
                                        <td class="px-1 py-0.5 text-gray-600">${hFecha}</td>
                                        <td class="px-1 py-0.5 text-center ${color} font-semibold">${signo}${h.cantidad_agregada}</td>
                                        <td class="px-1 py-0.5 text-center font-semibold">${h.cantidad_total || h.cantidad_despues || 0}</td>
                                    </tr>
                                `;
                            }).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        ` : '';
        
        const observacionesHtml = n.observaciones ? `
            
        ` : '';
        
        return `
            <div class="border border-gray-200 rounded-lg p-2 mb-2">
                <div class="flex items-start justify-between mb-1">
                    <div class="font-semibold text-xs flex-1">${n.descripcion}</div>
                    <span class="px-2 py-0.5 rounded text-[10px] ${badgeColor}">
                        <i class="fas ${badgeIcon} mr-1"></i>${badgeText}
                    </span>
                </div>
                <div class="grid grid-cols-3 gap-1 text-[10px] mb-1">
                    <div><span class="text-gray-600">Cód. Barras:</span> <span class="font-mono">${n.codigo_barras || 'N/A'}</span></div>
                    <div><span class="text-gray-600">Cód. Proveedor:</span> <span class="font-mono">${n.codigo_proveedor || 'N/A'}</span></div>
                    <div><span class="text-gray-600">Fecha:</span> ${fecha}</div>
                </div>
                <div class="grid grid-cols-3 gap-1 text-[10px]">
                    <div class="bg-blue-50 p-1 rounded">
                        <div class="text-gray-600">Llegó</div>
                        <div class="font-bold text-blue-700">${n.cantidad_llego}</div>
                    </div>
                    <div class="bg-green-50 p-1 rounded">
                        <div class="text-gray-600">Enviada</div>
                        <div class="font-bold text-green-700">${n.cantidad_enviada}</div>
                    </div>
                    <div class="bg-yellow-100 p-1 rounded">
                        <div class="text-gray-600">Diferencia</div>
                        <div class="font-bold text-orange-700">${n.diferencia}</div>
                    </div>
                </div>
                ${observacionesHtml}
                ${historialHtml}
            </div>
        `;
    }).join('');
}

// Limpiar input de producto novedad
function limpiarInputProductoNovedad() {
    document.getElementById('inputProductoNovedad').value = '';
    document.getElementById('btnLimpiarProductoNovedad').classList.add('hidden');
    document.getElementById('mensajeProductoNovedad').innerHTML = '';
    limpiarNovedad();
}

// Limpiar novedad
function limpiarNovedad() {
    novedadActual = null;
    document.getElementById('infoNovedad').classList.add('hidden');
    document.getElementById('inputCantidadNovedad').value = '';
    document.getElementById('inputProductoNovedad').focus();
}

// Imprimir ticket de novedad
function imprimirTicketNovedad() {
    if (!novedadActual) {
        mostrarNotificacion('No hay novedad seleccionada', 'warning');
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/warehouse-inventory/imprimir-ticket-producto';
    form.target = '_blank';
    
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = '_token';
    csrfInput.value = csrfToken;
    form.appendChild(csrfInput);
    
    const campos = [
        { name: 'codigo_barras', value: novedadActual.codigo_barras || '' },
        { name: 'codigo_proveedor', value: novedadActual.codigo_proveedor || '' },
        { name: 'descripcion', value: novedadActual.descripcion || '' },
        { name: 'marca', value: '' }
    ];
    
    campos.forEach(campo => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = campo.name;
        input.value = campo.value;
        form.appendChild(input);
    });
    
    document.body.appendChild(form);
    form.submit();
    
    setTimeout(() => {
        document.body.removeChild(form);
    }, 100);
}

// Imprimir reporte de novedades
function imprimirReporteNovedades() {
    // Obtener las novedades actuales
    fetch('/warehouse-inventory/tcr/get-novedades', {
        headers: {
            'X-CSRF-TOKEN': csrfToken
        }
    })
    .then(response => response.json())
    .then(data => {
        if (!data.estado || !data.novedades || data.novedades.length === 0) {
            alert('No hay novedades para imprimir');
            return;
        }
        
        const novedades = data.novedades;
        const ventana = window.open('', '_blank');
        
        // Usar la información del pedido guardada cuando se seleccionó (más eficiente)
        let pedidoInfo = pedidoInfoSeleccionado;
        let usuarioReviso = null;
        
        // Obtener usuario que revisó de las novedades
        for (let n of novedades) {
            if (n.pasillero && n.pasillero.nombre) {
                usuarioReviso = n.pasillero.nombre;
                break; // Usar el primer usuario encontrado
            }
        }
        
        // Generar encabezado con información del pedido
        let encabezadoHTML = '';
        if (pedidoInfo) {
            // Obtener información de origen
            let sucursalOrigen = 'N/A';
            if (pedidoInfo.origen) {
                if (typeof pedidoInfo.origen === 'object') {
                    sucursalOrigen = pedidoInfo.origen.nombre || pedidoInfo.origen.codigo || pedidoInfo.origen.descripcion || 'N/A';
                } else {
                    sucursalOrigen = pedidoInfo.origen;
                }
            }
            
            // Obtener información de destino
            let sucursalDestino = 'N/A';
            if (pedidoInfo.destino) {
                if (typeof pedidoInfo.destino === 'object') {
                    sucursalDestino = pedidoInfo.destino.nombre || pedidoInfo.destino.codigo || pedidoInfo.destino.descripcion || 'N/A';
                } else {
                    sucursalDestino = pedidoInfo.destino;
                }
            }
            
            // Obtener número de pedido, ID e ID en sucursal
            const numeroPedido = pedidoInfo.numero || pedidoInfo.numero_pedido || pedidoInfo.id || 'N/A';
            const idPedido = pedidoInfo.id || 'N/A';
            const idinsucursal = pedidoInfo.idinsucursal || pedidoInfo.id_sucursal || pedidoInfo.idinsucursal_pedido || 'N/A';
            
            encabezadoHTML = `
                <div style="background-color: #f9fafb; border: 1px solid #d1d5db; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
                    <h2 style="margin: 0 0 15px 0; font-size: 16px; color: #1f2937; border-bottom: 2px solid #3b82f6; padding-bottom: 8px;">Información del Pedido</h2>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 12px;">
                        <div><strong>Sucursal Origen:</strong> ${sucursalOrigen}</div>
                        <div><strong>Sucursal Destino:</strong> ${sucursalDestino}</div>
                        <div><strong>Número de Pedido:</strong> ${numeroPedido}</div>
                        <div><strong>ID Pedido:</strong> ${idPedido}</div>
                        <div><strong>ID en Sucursal:</strong> ${idinsucursal}</div>
                        ${usuarioReviso ? `<div><strong>Usuario que Revisó:</strong> ${usuarioReviso}</div>` : ''}
                    </div>
                </div>
            `;
        } else if (usuarioReviso) {
            encabezadoHTML = `
                <div style="background-color: #f9fafb; border: 1px solid #d1d5db; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
                    <div style="font-size: 12px;"><strong>Usuario que Revisó:</strong> ${usuarioReviso}</div>
                </div>
            `;
        }
        
        // Generar tabla HTML
        let tablaHTML = `
            <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
                <thead>
                    <tr style="background-color: #f3f4f6; border-bottom: 2px solid #d1d5db;">
                        <th style="padding: 12px; text-align: center; border: 1px solid #d1d5db; font-weight: bold; width: 50px;">#</th>
                        <th style="padding: 12px; text-align: left; border: 1px solid #d1d5db; font-weight: bold;">Código</th>
                        <th style="padding: 12px; text-align: left; border: 1px solid #d1d5db; font-weight: bold;">Descripción</th>
                        <th style="padding: 12px; text-align: center; border: 1px solid #d1d5db; font-weight: bold;">Cantidad Enviada</th>
                        <th style="padding: 12px; text-align: center; border: 1px solid #d1d5db; font-weight: bold;">Cantidad Recibida</th>
                        <th style="padding: 12px; text-align: center; border: 1px solid #d1d5db; font-weight: bold;">Diferencia</th>
                        <th style="padding: 12px; text-align: center; border: 1px solid #d1d5db; font-weight: bold;">Estatus</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        novedades.forEach((n, index) => {
            // Determinar estatus basado en la diferencia
            let estatus = 'Novedad';
            let estatusColor = '#6b7280';
            
            if (n.diferencia == 0 && n.cantidad_llego > 0) {
                estatus = 'Correcto';
                estatusColor = '#10b981';
            } else if (n.diferencia > 0 && n.cantidad_enviada > 0) {
                estatus = 'Sobrante';
                estatusColor = '#3b82f6';
            } else if (n.diferencia < 0 && n.cantidad_enviada > 0) {
                estatus = 'Faltante';
                estatusColor = '#ef4444';
            }
            
            // Usar código de barras o código de proveedor como código principal
            const codigo = n.codigo_barras || n.codigo_proveedor || 'N/A';
            
            const bgColor = index % 2 === 0 ? '#ffffff' : '#f9fafb';
            const diferenciaColor = n.diferencia > 0 ? '#3b82f6' : n.diferencia < 0 ? '#ef4444' : '#10b981';
            const diferenciaSigno = n.diferencia > 0 ? '+' : '';
            
            tablaHTML += `
                <tr style="background-color: ${bgColor};">
                    <td style="padding: 10px; border: 1px solid #d1d5db; text-align: center; font-size: 12px; font-weight: bold;">${index + 1}</td>
                    <td style="padding: 10px; border: 1px solid #d1d5db; font-family: monospace; font-size: 11px;">${codigo}</td>
                    <td style="padding: 10px; border: 1px solid #d1d5db; font-size: 12px;">${n.descripcion || 'N/A'}</td>
                    <td style="padding: 10px; border: 1px solid #d1d5db; text-align: center; font-size: 12px; font-weight: bold;">${n.cantidad_enviada || 0}</td>
                    <td style="padding: 10px; border: 1px solid #d1d5db; text-align: center; font-size: 12px; font-weight: bold;">${n.cantidad_llego || 0}</td>
                    <td style="padding: 10px; border: 1px solid #d1d5db; text-align: center; font-size: 12px; font-weight: bold; color: ${diferenciaColor};">${diferenciaSigno}${n.diferencia || 0}</td>
                    <td style="padding: 10px; border: 1px solid #d1d5db; text-align: center; font-size: 12px; font-weight: bold; color: ${estatusColor};">${estatus}</td>
                </tr>
            `;
        });
        
        // Agregar total de productos al final
        tablaHTML += `
                </tbody>
                <tfoot>
                    <tr style="background-color: #f3f4f6; border-top: 2px solid #d1d5db; font-weight: bold;">
                        <td style="padding: 10px; border: 1px solid #d1d5db; text-align: center; font-size: 12px;" colspan="3">Total de Productos:</td>
                        <td style="padding: 10px; border: 1px solid #d1d5db; text-align: center; font-size: 12px;">${novedades.length}</td>
                        <td style="padding: 10px; border: 1px solid #d1d5db; text-align: center; font-size: 12px;" colspan="3"></td>
                    </tr>
                </tfoot>
            </table>
        `;
        
        ventana.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Reporte de Novedades TCR</title>
                <style>
                    @media print {
                        body { margin: 0; padding: 15px; }
                        @page { margin: 1cm; }
                    }
                    body { 
                        font-family: Arial, sans-serif; 
                        padding: 20px; 
                        font-size: 12px;
                    }
                    h1 { 
                        text-align: center; 
                        margin-bottom: 10px;
                        font-size: 20px;
                        color: #1f2937;
                    }
                    .fecha {
                        text-align: center;
                        margin-bottom: 20px;
                        color: #6b7280;
                        font-size: 11px;
                    }
                    table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-top: 10px;
                    }
                    th {
                        background-color: #f3f4f6;
                        font-weight: bold;
                        padding: 12px 8px;
                        text-align: left;
                        border: 1px solid #d1d5db;
                        font-size: 11px;
                    }
                    td {
                        padding: 10px 8px;
                        border: 1px solid #d1d5db;
                        font-size: 11px;
                    }
                    tr:nth-child(even) {
                        background-color: #f9fafb;
                    }
                </style>
            </head>
            <body>
                <h1>Reporte de Novedades TCR</h1>
                <div class="fecha">Fecha: ${new Date().toLocaleString('es-VE', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                })}</div>
                ${encabezadoHTML}
                ${tablaHTML}
            </body>
            </html>
        `);
        ventana.document.close();
        ventana.print();
    })
    .catch(error => {
        console.error('Error al cargar novedades para imprimir:', error);
        alert('Error al cargar las novedades para imprimir');
    });
}

// Exportar PDF de novedades (usando window.print por ahora)
function exportarPDFNovedades() {
    imprimirReporteNovedades();
}

// Las novedades se cargarán solo cuando se registre una o cuando se cambie a la pestaña de novedades
</script>
@endsection
