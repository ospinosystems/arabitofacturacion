@extends('layouts.app')

@section('content')
@include('warehouse-inventory.partials.nav')

<div class="container-fluid px-2 sm:px-4">
    <!-- Header -->
    <div class="mb-3 sm:mb-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h1 class="text-xl sm:text-2xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-boxes text-blue-500 mr-2"></i>
                <span class="hidden sm:inline">Inventario por Ubicaciones</span>
                <span class="sm:hidden">Inventario</span>
            </h1>
            <button type="button" 
                    onclick="abrirModalAsignar()"
                    class="w-full sm:w-auto inline-flex justify-center items-center px-4 py-2.5 bg-green-600 hover:bg-green-700  font-medium rounded-lg shadow-sm transition">
                <i class="fas fa-plus mr-2"></i>
                <span class="hidden sm:inline">Asignar Producto</span>
                <span class="sm:hidden">Asignar</span>
            </button>
        </div>
    </div>

    <!-- Filtros Compactos para Móvil -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-3 sm:mb-4">
        <div class="p-3 sm:p-4">
            <!-- Toggle de Filtros en Móvil -->
            <div class="mb-3 sm:hidden">
                <button type="button" 
                        onclick="toggleFiltros()" 
                        class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 rounded-lg transition">
                    <span class="text-sm font-medium text-gray-700">
                        <i class="fas fa-filter mr-2"></i>
                        Filtros
                    </span>
                    <i id="filtrosIcon" class="fas fa-chevron-down text-gray-500 transition-transform"></i>
                </button>
            </div>
            
            <form id="filtrosForm" onsubmit="event.preventDefault(); aplicarFiltros();">
                <div id="filtrosContainer" class="hidden sm:block space-y-3 sm:space-y-0">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                        <!-- Ubicación -->
                        <div>
                            <label for="warehouse_id" class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-map-marker-alt text-blue-500 mr-1"></i>
                                <span class="hidden sm:inline">Ubicación</span>
                                <span class="sm:hidden">Ubicación</span>
                            </label>
                            <select id="warehouse_id" 
                                    name="warehouse_id" 
                                    onchange="aplicarFiltros()"
                                    class="block w-full px-2.5 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                                <option value="">Todas</option>
                                @foreach($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" {{ request('warehouse_id') == $warehouse->id ? 'selected' : '' }}>
                                        {{ $warehouse->codigo }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <!-- Estado -->
                        <div>
                            <label for="estado" class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-tag text-blue-500 mr-1"></i>
                                Estado
                            </label>
                            <select id="estado" 
                                    name="estado" 
                                    onchange="aplicarFiltros()"
                                    class="block w-full px-2.5 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                                <option value="">Todos</option>
                                <option value="disponible" {{ request('estado') == 'disponible' ? 'selected' : '' }}>Disponible</option>
                                <option value="reservado" {{ request('estado') == 'reservado' ? 'selected' : '' }}>Reservado</option>
                                <option value="cuarentena" {{ request('estado') == 'cuarentena' ? 'selected' : '' }}>Cuarentena</option>
                            </select>
                        </div>
                        
                        <!-- Lote -->
                        <div>
                            <label for="lote" class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-barcode text-blue-500 mr-1"></i>
                                Lote
                            </label>
                            <input type="text" 
                                   id="lote" 
                                   name="lote" 
                                   value="{{ request('lote') }}" 
                                   placeholder="Ej: L001"
                                   onkeyup="aplicarFiltrosConDelay()"
                                   class="block w-full px-2.5 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                        </div>
                        
                        <!-- Buscar Producto -->
                        <div class="sm:col-span-1 lg:col-span-1">
                            <label for="buscar" class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-search text-blue-500 mr-1"></i>
                                Producto
                            </label>
                            <input type="text" 
                                   id="buscar" 
                                   name="buscar" 
                                   value="{{ request('buscar') }}" 
                                   placeholder="Buscar..."
                                   onkeyup="aplicarFiltrosConDelay()"
                                   class="block w-full px-2.5 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                        </div>
                        
                        <!-- Botón Limpiar -->
                        <div class="flex items-end">
                            <button type="button" 
                                    onclick="limpiarFiltros()" 
                                    class="w-full px-3 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition flex items-center justify-center"
                                    title="Limpiar filtros">
                                <i class="fas fa-redo mr-1 sm:mr-2"></i>
                                <span class="hidden sm:inline">Limpiar</span>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla de Inventarios Responsive -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="relative min-h-[200px]">
            <!-- Loading Overlay -->
            <div id="loadingOverlay" class="absolute inset-0 bg-white bg-opacity-90 hidden z-10 flex items-center justify-center">
                <div class="text-center">
                    <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mb-3"></div>
                    <p class="text-sm text-gray-600">Cargando inventarios...</p>
                </div>
            </div>
            
            <!-- Vista Desktop -->
            <div class="hidden lg:block overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Ubicación</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Producto</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Lote</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Cantidad</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Estado</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">F. Entrada</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">F. Vencimiento</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tablaBodyDesktop" class="divide-y divide-gray-200">
                        @forelse($inventarios as $inventario)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-4 py-3">
                                    <div class="text-sm font-semibold text-gray-900">{{ $inventario->warehouse->codigo }}</div>
                                    <div class="text-xs text-gray-500">{{ $inventario->warehouse->nombre }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm font-semibold text-gray-900">{{ $inventario->inventario->descripcion }}</div>
                                    <div class="text-xs text-gray-500">{{ $inventario->inventario->codigo_barras }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ $inventario->lote ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                        {{ number_format($inventario->cantidad, 2) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    @if($inventario->estado == 'disponible')
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Disponible</span>
                                    @elseif($inventario->estado == 'reservado')
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Reservado</span>
                                    @else
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Cuarentena</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    {{ $inventario->fecha_entrada ? \Carbon\Carbon::parse($inventario->fecha_entrada)->format('d/m/Y') : '-' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    @if($inventario->fecha_vencimiento)
                                        {{ \Carbon\Carbon::parse($inventario->fecha_vencimiento)->format('d/m/Y') }}
                                        @if(\Carbon\Carbon::parse($inventario->fecha_vencimiento)->lt(now()->addDays(30)))
                                            <span class="inline-flex px-1.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-800 ml-1">Próximo</span>
                                        @endif
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex gap-1">
                                        <button onclick="verDetalles({{ $inventario->id }})" 
                                                class="p-1.5 text-blue-600 hover:bg-blue-50 rounded transition"
                                                title="Ver detalles">
                                            <i class="fas fa-eye text-sm"></i>
                                        </button>
                                        <button onclick="moverInventario({{ $inventario->id }})" 
                                                class="p-1.5 text-yellow-600 hover:bg-yellow-50 rounded transition"
                                                title="Mover">
                                            <i class="fas fa-exchange-alt text-sm"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                    <i class="fas fa-inbox text-4xl mb-3 block"></i>
                                    <p>No se encontraron registros de inventario</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <!-- Vista Mobile (Cards) -->
            <div id="tablaBodyMobile" class="lg:hidden divide-y divide-gray-200">
                @forelse($inventarios as $inventario)
                    <div class="p-3 hover:bg-gray-50 transition">
                        <!-- Header Card -->
                        <div class="flex justify-between items-start mb-2">
                            <div class="flex-1">
                                <div class="text-sm font-semibold text-gray-900">{{ $inventario->inventario->descripcion }}</div>
                                <div class="text-xs text-gray-500 mt-0.5">{{ $inventario->inventario->codigo_barras }}</div>
                            </div>
                            <div class="ml-2">
                                @if($inventario->estado == 'disponible')
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Disponible</span>
                                @elseif($inventario->estado == 'reservado')
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Reservado</span>
                                @else
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Cuarentena</span>
                                @endif
                            </div>
                        </div>
                        
                        <!-- Info Grid -->
                        <div class="grid grid-cols-2 gap-2 text-xs mb-2">
                            <div>
                                <span class="text-gray-500">Ubicación:</span>
                                <span class="font-semibold text-gray-900 ml-1">{{ $inventario->warehouse->codigo }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500">Cantidad:</span>
                                <span class="inline-flex px-1.5 py-0.5 ml-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                    {{ number_format($inventario->cantidad, 2) }}
                                </span>
                            </div>
                            @if($inventario->lote)
                            <div>
                                <span class="text-gray-500">Lote:</span>
                                <span class="font-medium text-gray-900 ml-1">{{ $inventario->lote }}</span>
                            </div>
                            @endif
                            @if($inventario->fecha_vencimiento)
                            <div>
                                <span class="text-gray-500">Vence:</span>
                                <span class="font-medium text-gray-900 ml-1">{{ \Carbon\Carbon::parse($inventario->fecha_vencimiento)->format('d/m/Y') }}</span>
                            </div>
                            @endif
                        </div>
                        
                        <!-- Acciones -->
                        <div class="flex gap-2 mt-3">
                            <button onclick="verDetalles({{ $inventario->id }})" 
                                    class="flex-1 px-3 py-2 text-xs font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition">
                                <i class="fas fa-eye mr-1"></i> Ver
                            </button>
                            <button onclick="moverInventario({{ $inventario->id }})" 
                                    class="flex-1 px-3 py-2 text-xs font-medium text-yellow-600 bg-yellow-50 hover:bg-yellow-100 rounded-lg transition">
                                <i class="fas fa-exchange-alt mr-1"></i> Mover
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-gray-500">
                        <i class="fas fa-inbox text-4xl mb-3 block"></i>
                        <p class="text-sm">No se encontraron registros</p>
                    </div>
                @endforelse
            </div>
            
            <!-- Paginación -->
            <div id="paginacionContainer" class="p-3 sm:p-4 border-t border-gray-200 flex justify-center">
                {{ $inventarios->appends(request()->query())->links() }}
            </div>
        </div>
    </div>
</div>

<!-- Modal para asignar producto a ubicación con Tailwind -->
<div id="asignarProductoModal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="cerrarModal()"></div>
    
    <!-- Modal Container -->
    <div class="flex min-h-screen items-center justify-center p-2 sm:p-4">
        <div class="relative w-full max-w-4xl transform overflow-hidden rounded-lg bg-white shadow-xl transition-all">
            
            <!-- Header -->
            <div class="bg-gradient-to-r from-green-500 to-green-600 px-4 py-4 sm:px-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg sm:text-xl font-semibold  flex items-center">
                        <i class="fas fa-plus mr-2"></i>
                        <span class="hidden sm:inline">Asignar Producto a Ubicación</span>
                        <span class="sm:hidden">Asignar Producto</span>
                    </h3>
                    <button type="button" onclick="cerrarModal()" class=" hover:text-gray-200 focus:outline-none">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>
            
            <!-- Form -->
            <form id="asignarProductoForm" onsubmit="asignarProducto(event)" class="max-h-[calc(100vh-200px)] overflow-y-auto">
                <div class="px-4 py-5 sm:p-6 space-y-4 sm:space-y-6">
                    
                    <!-- Ubicación y Búsqueda de Producto -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
                        <!-- Búsqueda de Ubicación -->
                        <div>
                            <label for="buscar_ubicacion" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-map-marker-alt text-blue-500 mr-1"></i>
                                Buscar Ubicación <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input type="text" 
                                       id="buscar_ubicacion" 
                                       placeholder="Ej: A1-01-1, Pasillo A..."
                                       autocomplete="off"
                                       oninput="buscarUbicaciones(this.value)"
                                       class="block w-full rounded-lg border border-gray-300 px-3 py-2 sm:py-2.5 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition">
                                <input type="hidden" id="modal_warehouse_id" name="warehouse_id" required>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">
                                <i class="fas fa-info-circle"></i> Escribe el código de la ubicación
                            </p>
                            
                            <!-- Resultados de búsqueda ubicación -->
                            <div id="resultados_ubicacion" 
                                 class="hidden mt-2 max-h-48 overflow-y-auto rounded-lg border border-gray-200 shadow-lg bg-white z-10">
                            </div>
                            
                            <!-- Ubicación seleccionada -->
                            <div id="ubicacion_seleccionada" 
                                 class="hidden mt-3 rounded-lg bg-blue-50 border border-blue-200 p-3 sm:p-4 relative">
                                <button type="button" 
                                        onclick="limpiarUbicacionSeleccionada()"
                                        class="absolute top-2 right-2 text-red-500 hover:text-red-700 focus:outline-none">
                                    <i class="fas fa-times-circle text-lg sm:text-xl"></i>
                                </button>
                                <div class="pr-8">
                                    <p class="text-sm font-semibold text-blue-800 mb-1">
                                        <i class="fas fa-check-circle mr-1"></i> Ubicación seleccionada
                                    </p>
                                    <div id="ubicacion_info" class="text-xs sm:text-sm text-gray-700"></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Búsqueda de Producto -->
                        <div>
                            <label for="buscar_producto" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-search text-blue-500 mr-1"></i>
                                Buscar Producto <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input type="text" 
                                       id="buscar_producto" 
                                       placeholder="Código, descripción..."
                                       autocomplete="off"
                                       oninput="buscarProductos(this.value)"
                                       class="block w-full rounded-lg border border-gray-300 px-3 py-2 sm:py-2.5 text-sm focus:border-green-500 focus:ring-2 focus:ring-green-200 transition">
                                <input type="hidden" id="modal_inventario_id" name="inventario_id" required>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">
                                <i class="fas fa-info-circle"></i> Mínimo 2 caracteres
                            </p>
                            
                            <!-- Resultados de búsqueda -->
                            <div id="resultados_busqueda" 
                                 class="hidden mt-2 max-h-60 sm:max-h-80 overflow-y-auto rounded-lg border border-gray-200 shadow-lg bg-white">
                            </div>
                            
                            <!-- Producto seleccionado -->
                            <div id="producto_seleccionado" 
                                 class="hidden mt-3 rounded-lg bg-green-50 border border-green-200 p-3 sm:p-4 relative">
                                <button type="button" 
                                        onclick="limpiarProductoSeleccionado()"
                                        class="absolute top-2 right-2 text-red-500 hover:text-red-700 focus:outline-none">
                                    <i class="fas fa-times-circle text-lg sm:text-xl"></i>
                                </button>
                                <div class="pr-8">
                                    <p class="text-sm font-semibold text-green-800 mb-1">
                                        <i class="fas fa-check-circle mr-1"></i> Producto seleccionado
                                    </p>
                                    <div id="producto_info" class="text-xs sm:text-sm text-gray-700"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Cantidad y Fecha de Entrada -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="modal_cantidad" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-boxes text-blue-500 mr-1"></i>
                                Cantidad <span class="text-red-500">*</span>
                            </label>
                            <input type="number" 
                                   step="0.01" 
                                   id="modal_cantidad" 
                                   name="cantidad" 
                                   required 
                                   min="0.01"
                                   class="block w-full rounded-lg border border-gray-300 px-3 py-2 sm:py-2.5 text-sm focus:border-green-500 focus:ring-2 focus:ring-green-200 transition">
                        </div>
                        
                        <div>
                            <label for="modal_fecha_entrada" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-calendar text-blue-500 mr-1"></i>
                                Fecha de Entrada <span class="text-red-500">*</span>
                            </label>
                            <input type="date" 
                                   id="modal_fecha_entrada" 
                                   name="fecha_entrada" 
                                   required 
                                   value="{{ date('Y-m-d') }}"
                                   class="block w-full rounded-lg border border-gray-300 px-3 py-2 sm:py-2.5 text-sm focus:border-green-500 focus:ring-2 focus:ring-green-200 transition">
                        </div>
                    </div>
                    
                    <!-- Lote y Fecha de Vencimiento -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="modal_lote" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-barcode text-blue-500 mr-1"></i>
                                Lote
                            </label>
                            <input type="text" 
                                   id="modal_lote" 
                                   name="lote" 
                                   placeholder="Número de lote"
                                   class="block w-full rounded-lg border border-gray-300 px-3 py-2 sm:py-2.5 text-sm focus:border-green-500 focus:ring-2 focus:ring-green-200 transition">
                        </div>
                        
                        <div>
                            <label for="modal_fecha_vencimiento" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-calendar-times text-blue-500 mr-1"></i>
                                Fecha de Vencimiento
                            </label>
                            <input type="date" 
                                   id="modal_fecha_vencimiento" 
                                   name="fecha_vencimiento"
                                   class="block w-full rounded-lg border border-gray-300 px-3 py-2 sm:py-2.5 text-sm focus:border-green-500 focus:ring-2 focus:ring-green-200 transition">
                        </div>
                    </div>
                    
                    <!-- Documento de Referencia -->
                    <div>
                        <label for="modal_documento_referencia" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-file-alt text-blue-500 mr-1"></i>
                            Documento de Referencia
                        </label>
                        <input type="text" 
                               id="modal_documento_referencia" 
                               name="documento_referencia" 
                               placeholder="Ej: Orden de compra, factura..."
                               class="block w-full rounded-lg border border-gray-300 px-3 py-2 sm:py-2.5 text-sm focus:border-green-500 focus:ring-2 focus:ring-green-200 transition">
                    </div>
                    
                    <!-- Observaciones -->
                    <div>
                        <label for="modal_observaciones" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-comment-alt text-blue-500 mr-1"></i>
                            Observaciones
                        </label>
                        <textarea id="modal_observaciones" 
                                  name="observaciones" 
                                  rows="3"
                                  class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-2 focus:ring-green-200 transition resize-none"></textarea>
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="bg-gray-50 px-4 py-3 sm:px-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-2 sm:gap-3">
                    <button type="button" 
                            onclick="cerrarModal()"
                            class="w-full sm:w-auto inline-flex justify-center items-center px-4 py-2 sm:py-2.5 border border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition">
                        <i class="fas fa-times mr-2"></i>
                        Cancelar
                    </button>
                    <button type="submit"
                            class="w-full sm:w-auto inline-flex justify-center items-center px-4 py-2 sm:py-2.5 border border-transparent rounded-lg shadow-sm text-sm font-medium  bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition">
                        <i class="fas fa-save mr-2"></i>
                        Asignar Producto
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let searchTimeout;
    let searchUbicacionTimeout;
    let filtrosTimeout;
    let paginaActual = 1;

    // Toggle de filtros en móvil
    function toggleFiltros() {
        const container = document.getElementById('filtrosContainer');
        const icon = document.getElementById('filtrosIcon');
        
        container.classList.toggle('hidden');
        icon.classList.toggle('rotate-180');
    }

    // Cargar datos iniciales al cargar la página
    document.addEventListener('DOMContentLoaded', function() {
        cargarInventarios();
    });

function aplicarFiltros() {
    paginaActual = 1;
    cargarInventarios();
}

function aplicarFiltrosConDelay() {
    clearTimeout(filtrosTimeout);
    filtrosTimeout = setTimeout(() => {
        aplicarFiltros();
    }, 500);
}

function limpiarFiltros() {
    document.getElementById('warehouse_id').value = '';
    document.getElementById('estado').value = '';
    document.getElementById('lote').value = '';
    document.getElementById('buscar').value = '';
    aplicarFiltros();
}

    function cargarInventarios(pagina = 1) {
        paginaActual = pagina;

        // Mostrar loading
        const loadingOverlay = document.getElementById('loadingOverlay');
        if (loadingOverlay) {
            loadingOverlay.classList.remove('hidden');
        }

        // Obtener valores de filtros
        const params = new URLSearchParams({
            warehouse_id: document.getElementById('warehouse_id').value,
            estado: document.getElementById('estado').value,
            lote: document.getElementById('lote').value,
            buscar: document.getElementById('buscar').value,
            page: pagina
        });

        fetch('{{ route("warehouse-inventory.index") }}?' + params.toString(), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            renderizarTabla(data.data);
            renderizarPaginacion(data);
            if (loadingOverlay) {
                loadingOverlay.classList.add('hidden');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (loadingOverlay) {
                loadingOverlay.classList.add('hidden');
            }
            
            // Mostrar mensaje de error en ambas vistas
            const errorMessage = `
                <div class="p-8 text-center text-red-500">
                    <i class="fas fa-exclamation-triangle text-4xl mb-3 block"></i>
                    <p class="text-sm">Error al cargar los datos</p>
                </div>
            `;
            
            const tbodyDesktop = document.getElementById('tablaBodyDesktop');
            const tbodyMobile = document.getElementById('tablaBodyMobile');
            
            if (tbodyDesktop) {
                tbodyDesktop.innerHTML = `
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-red-500">
                            <i class="fas fa-exclamation-triangle text-4xl mb-3 block"></i>
                            <p>Error al cargar los datos</p>
                        </td>
                    </tr>
                `;
            }
            
            if (tbodyMobile) {
                tbodyMobile.innerHTML = errorMessage;
            }
        });
    }

    function renderizarTabla(inventarios) {
        const tbodyDesktop = document.getElementById('tablaBodyDesktop');
        const tbodyMobile = document.getElementById('tablaBodyMobile');

        if (inventarios.length === 0) {
            // Desktop empty state
            tbodyDesktop.innerHTML = `
                <tr>
                    <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                        <i class="fas fa-inbox text-4xl mb-3 block"></i>
                        <p>No se encontraron registros de inventario</p>
                    </td>
                </tr>
            `;
            
            // Mobile empty state
            tbodyMobile.innerHTML = `
                <div class="p-8 text-center text-gray-500">
                    <i class="fas fa-inbox text-4xl mb-3 block"></i>
                    <p class="text-sm">No se encontraron registros</p>
                </div>
            `;
            return;
        }

        // Renderizar Desktop
        let htmlDesktop = '';
        inventarios.forEach(inventario => {
            let vencimientoHtml = '-';
            if (inventario.fecha_vencimiento) {
                const fechaVenc = new Date(inventario.fecha_vencimiento);
                const hoy = new Date();
                const diasRestantes = Math.ceil((fechaVenc - hoy) / (1000 * 60 * 60 * 24));

                vencimientoHtml = formatearFecha(inventario.fecha_vencimiento);
                if (diasRestantes < 30 && diasRestantes > 0) {
                    vencimientoHtml += '<span class="inline-flex px-1.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-800 ml-1">Próximo</span>';
                }
            }

            htmlDesktop += `
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-4 py-3">
                        <div class="text-sm font-semibold text-gray-900">${inventario.warehouse.codigo}</div>
                        <div class="text-xs text-gray-500">${inventario.warehouse.nombre}</div>
                    </td>
                    <td class="px-4 py-3">
                        <div class="text-sm font-semibold text-gray-900">${inventario.inventario.descripcion}</div>
                        <div class="text-xs text-gray-500">${inventario.inventario.codigo_barras}</div>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-900">${inventario.lote || '-'}</td>
                    <td class="px-4 py-3">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                            ${parseFloat(inventario.cantidad).toFixed(2)}
                        </span>
                    </td>
                    <td class="px-4 py-3">${getEstadoBadge(inventario.estado)}</td>
                    <td class="px-4 py-3 text-sm text-gray-900">${inventario.fecha_entrada ? formatearFecha(inventario.fecha_entrada) : '-'}</td>
                    <td class="px-4 py-3 text-sm text-gray-900">${vencimientoHtml}</td>
                    <td class="px-4 py-3">
                        <div class="flex gap-1">
                            <button onclick="verDetalles(${inventario.id})" 
                                    class="p-1.5 text-blue-600 hover:bg-blue-50 rounded transition"
                                    title="Ver detalles">
                                <i class="fas fa-eye text-sm"></i>
                            </button>
                            <button onclick="moverInventario(${inventario.id})" 
                                    class="p-1.5 text-yellow-600 hover:bg-yellow-50 rounded transition"
                                    title="Mover">
                                <i class="fas fa-exchange-alt text-sm"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        // Renderizar Mobile (Cards)
        let htmlMobile = '';
        inventarios.forEach(inventario => {
            htmlMobile += `
                <div class="p-3 hover:bg-gray-50 transition">
                    <div class="flex justify-between items-start mb-2">
                        <div class="flex-1">
                            <div class="text-sm font-semibold text-gray-900">${inventario.inventario.descripcion}</div>
                            <div class="text-xs text-gray-500 mt-0.5">${inventario.inventario.codigo_barras}</div>
                        </div>
                        <div class="ml-2">${getEstadoBadge(inventario.estado)}</div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-2 text-xs mb-2">
                        <div>
                            <span class="text-gray-500">Ubicación:</span>
                            <span class="font-semibold text-gray-900 ml-1">${inventario.warehouse.codigo}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Cantidad:</span>
                            <span class="inline-flex px-1.5 py-0.5 ml-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                ${parseFloat(inventario.cantidad).toFixed(2)}
                            </span>
                        </div>
                        ${inventario.lote ? `
                        <div>
                            <span class="text-gray-500">Lote:</span>
                            <span class="font-medium text-gray-900 ml-1">${inventario.lote}</span>
                        </div>
                        ` : ''}
                        ${inventario.fecha_vencimiento ? `
                        <div>
                            <span class="text-gray-500">Vence:</span>
                            <span class="font-medium text-gray-900 ml-1">${formatearFecha(inventario.fecha_vencimiento)}</span>
                        </div>
                        ` : ''}
                    </div>
                    
                    <div class="flex gap-2 mt-3">
                        <button onclick="verDetalles(${inventario.id})" 
                                class="flex-1 px-3 py-2 text-xs font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition">
                            <i class="fas fa-eye mr-1"></i> Ver
                        </button>
                        <button onclick="moverInventario(${inventario.id})" 
                                class="flex-1 px-3 py-2 text-xs font-medium text-yellow-600 bg-yellow-50 hover:bg-yellow-100 rounded-lg transition">
                            <i class="fas fa-exchange-alt mr-1"></i> Mover
                        </button>
                    </div>
                </div>
            `;
        });

        tbodyDesktop.innerHTML = htmlDesktop;
        tbodyMobile.innerHTML = htmlMobile;
    }

    function renderizarPaginacion(data) {
        const container = document.getElementById('paginacionContainer');

        if (data.last_page <= 1) {
            container.innerHTML = '';
            return;
        }

        let html = '<nav class="flex justify-center" aria-label="Paginación">';
        html += '<ul class="flex items-center gap-1">';

        // Botón anterior
        if (data.current_page > 1) {
            html += `
                <li>
                    <a href="#" 
                       onclick="event.preventDefault(); cargarInventarios(${data.current_page - 1})"
                       class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        <i class="fas fa-chevron-left"></i>
                        <span class="hidden sm:inline ml-1">Anterior</span>
                    </a>
                </li>
            `;
        } else {
            html += `
                <li>
                    <span class="px-3 py-2 text-sm font-medium text-gray-400 bg-gray-100 border border-gray-300 rounded-lg cursor-not-allowed">
                        <i class="fas fa-chevron-left"></i>
                        <span class="hidden sm:inline ml-1">Anterior</span>
                    </span>
                </li>
            `;
        }

        // Números de página (solo mostrar algunos en móvil)
        for (let i = 1; i <= data.last_page; i++) {
            if (i === 1 || i === data.last_page || (i >= data.current_page - 1 && i <= data.current_page + 1)) {
                const isActive = i === data.current_page;
                html += `
                    <li class="${i > 1 && i < data.last_page ? 'hidden sm:block' : ''}">
                        <a href="#" 
                           onclick="event.preventDefault(); cargarInventarios(${i})"
                           class="px-3 py-2 text-sm font-medium ${isActive 
                                ? ' bg-blue-600 border border-blue-600' 
                                : 'text-gray-700 bg-white border border-gray-300 hover:bg-gray-50'
                            } rounded-lg transition">
                            ${i}
                        </a>
                    </li>
                `;
            } else if (i === data.current_page - 2 || i === data.current_page + 2) {
                html += `<li class="hidden sm:block"><span class="px-2 py-2 text-sm text-gray-400">...</span></li>`;
            }
        }

        // Mostrar página actual en móvil
        html += `
            <li class="sm:hidden">
                <span class="px-3 py-2 text-sm font-medium text-gray-700">
                    ${data.current_page} / ${data.last_page}
                </span>
            </li>
        `;

        // Botón siguiente
        if (data.current_page < data.last_page) {
            html += `
                <li>
                    <a href="#" 
                       onclick="event.preventDefault(); cargarInventarios(${data.current_page + 1})"
                       class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        <span class="hidden sm:inline mr-1">Siguiente</span>
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            `;
        } else {
            html += `
                <li>
                    <span class="px-3 py-2 text-sm font-medium text-gray-400 bg-gray-100 border border-gray-300 rounded-lg cursor-not-allowed">
                        <span class="hidden sm:inline mr-1">Siguiente</span>
                        <i class="fas fa-chevron-right"></i>
                    </span>
                </li>
            `;
        }

        html += '</ul></nav>';
        container.innerHTML = html;
    }

    function getEstadoBadge(estado) {
        switch(estado) {
            case 'disponible':
                return '<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Disponible</span>';
            case 'reservado':
                return '<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Reservado</span>';
            case 'cuarentena':
                return '<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Cuarentena</span>';
            default:
                return '<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">' + estado + '</span>';
        }
    }

function formatearFecha(fecha) {
    const date = new Date(fecha);
    return date.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function abrirModalAsignar() {
    // Limpiar el formulario
    document.getElementById('asignarProductoForm').reset();
    document.getElementById('modal_fecha_entrada').value = '{{ date("Y-m-d") }}';
    limpiarProductoSeleccionado();
    limpiarUbicacionSeleccionada();
    
    // Mostrar modal con Tailwind
    const modal = document.getElementById('asignarProductoModal');
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden'; // Prevenir scroll en el fondo
}

function buscarUbicaciones(texto) {
    clearTimeout(searchUbicacionTimeout);
    
    const resultadosDiv = document.getElementById('resultados_ubicacion');
    
    if (texto.length < 1) {
        resultadosDiv.classList.add('hidden');
        resultadosDiv.innerHTML = '';
        return;
    }
    
    // Mostrar indicador de carga
    resultadosDiv.classList.remove('hidden');
    resultadosDiv.innerHTML = `
        <div class="p-3 text-center text-gray-500">
            <i class="fas fa-spinner fa-spin text-lg"></i>
            <p class="text-xs mt-1">Buscando ubicaciones...</p>
        </div>
    `;
    
    searchUbicacionTimeout = setTimeout(() => {
        fetch('{{ route("warehouses.buscar") }}?buscar=' + encodeURIComponent(texto))
            .then(response => response.json())
            .then(data => {
                if (data.ubicaciones.length === 0) {
                    resultadosDiv.innerHTML = `
                        <div class="p-3 text-center text-gray-500">
                            <i class="fas fa-map-marker-slash text-2xl mb-1"></i>
                            <p class="text-xs">No se encontraron ubicaciones</p>
                        </div>
                    `;
                    return;
                }
                
                let html = '';
                data.ubicaciones.forEach(ubicacion => {
                    html += `
                        <button type="button" 
                                onclick='seleccionarUbicacion(${JSON.stringify(ubicacion)})'
                                class="w-full text-left px-3 py-2.5 hover:bg-blue-50 focus:bg-blue-50 focus:outline-none border-b border-gray-100 transition">
                            <div class="flex justify-between items-center mb-1">
                                <p class="font-semibold text-sm text-gray-900">
                                    <i class="fas fa-map-marker-alt text-blue-500 mr-1"></i>
                                    ${ubicacion.codigo}
                                </p>
                                <span class="text-xs px-2 py-0.5 rounded ${ubicacion.tipo === 'pasillo' ? 'bg-purple-100 text-purple-700' : ubicacion.tipo === 'estante' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'}">
                                    ${ubicacion.tipo}
                                </span>
                            </div>
                            <p class="text-xs text-gray-600">
                                ${ubicacion.nombre || '-'}
                            </p>
                            ${ubicacion.ubicacion ? `<p class="text-xs text-gray-500 mt-0.5"><i class="fas fa-location-dot mr-1"></i>${ubicacion.ubicacion}</p>` : ''}
                        </button>
                    `;
                });
                
                resultadosDiv.innerHTML = html;
            })
            .catch(error => {
                console.error('Error:', error);
                resultadosDiv.innerHTML = `
                    <div class="p-3 text-center text-red-500">
                        <i class="fas fa-exclamation-triangle text-2xl"></i>
                        <p class="text-xs mt-1">Error al buscar ubicaciones</p>
                    </div>
                `;
            });
    }, 200);
}

function seleccionarUbicacion(ubicacion) {
    // Guardar el ID en el campo oculto
    document.getElementById('modal_warehouse_id').value = ubicacion.id;
    
    // Mostrar información de la ubicación seleccionada
    const ubicacionInfo = `
        <p class="font-semibold text-gray-900">
            <i class="fas fa-map-marker-alt text-blue-500 mr-1"></i>
            ${ubicacion.codigo}
        </p>
        <p class="text-xs mt-1">${ubicacion.nombre || '-'}</p>
        ${ubicacion.ubicacion ? `<p class="text-xs mt-1"><i class="fas fa-location-dot mr-1"></i>${ubicacion.ubicacion}</p>` : ''}
        ${ubicacion.capacidad_maxima ? `<p class="text-xs mt-1"><i class="fas fa-box mr-1"></i>Capacidad: ${ubicacion.capacidad_maxima}</p>` : ''}
    `;
    document.getElementById('ubicacion_info').innerHTML = ubicacionInfo;
    document.getElementById('ubicacion_seleccionada').classList.remove('hidden');
    
    // Ocultar resultados y limpiar búsqueda
    document.getElementById('resultados_ubicacion').classList.add('hidden');
    document.getElementById('buscar_ubicacion').value = ubicacion.codigo;
}

function limpiarUbicacionSeleccionada() {
    document.getElementById('modal_warehouse_id').value = '';
    document.getElementById('ubicacion_seleccionada').classList.add('hidden');
    document.getElementById('ubicacion_info').innerHTML = '';
    document.getElementById('buscar_ubicacion').value = '';
    document.getElementById('resultados_ubicacion').classList.add('hidden');
    document.getElementById('resultados_ubicacion').innerHTML = '';
}

function buscarProductos(texto) {
    clearTimeout(searchTimeout);
    
    const resultadosDiv = document.getElementById('resultados_busqueda');
    
    if (texto.length < 2) {
        resultadosDiv.classList.add('hidden');
        resultadosDiv.innerHTML = '';
        return;
    }
    
    // Mostrar indicador de carga
    resultadosDiv.classList.remove('hidden');
    resultadosDiv.innerHTML = `
        <div class="p-4 text-center text-gray-500">
            <i class="fas fa-spinner fa-spin text-xl mb-2"></i>
            <p class="text-sm">Buscando productos...</p>
        </div>
    `;
    
    searchTimeout = setTimeout(() => {
        fetch('{{ route("warehouse-inventory.buscar-productos") }}?buscar=' + encodeURIComponent(texto))
            .then(response => response.json())
            .then(data => {
                if (data.productos.length === 0) {
                    resultadosDiv.innerHTML = `
                        <div class="p-4 text-center text-gray-500">
                            <i class="fas fa-inbox text-3xl mb-2"></i>
                            <p class="text-sm">No se encontraron productos</p>
                        </div>
                    `;
                    return;
                }
                
                let html = '';
                data.productos.forEach(producto => {
                    html += `
                        <button type="button" 
                                onclick='seleccionarProducto(${JSON.stringify(producto)})'
                                class="w-full text-left px-4 py-3 hover:bg-green-50 focus:bg-green-50 focus:outline-none border-b border-gray-100 transition">
                            <div class="flex justify-between items-start mb-1">
                                <h6 class="font-semibold text-sm sm:text-base text-gray-900 flex-1 pr-2">
                                    ${producto.descripcion}
                                </h6>
                                <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">
                                    ID: ${producto.id}
                                </span>
                            </div>
                            <p class="text-xs sm:text-sm text-gray-600 mb-1">
                                <span class="font-medium">CB:</span> ${producto.codigo_barras || 'N/A'} | 
                                <span class="font-medium">CP:</span> ${producto.codigo_proveedor || 'N/A'}
                            </p>
                            <p class="text-xs text-gray-500">
                                <i class="fas fa-tag mr-1"></i>${producto.categoria} • 
                                <i class="fas fa-truck mr-1"></i>${producto.proveedor}
                            </p>
                        </button>
                    `;
                });
                
                resultadosDiv.innerHTML = html;
            })
            .catch(error => {
                console.error('Error:', error);
                resultadosDiv.innerHTML = `
                    <div class="p-4 text-center text-red-500">
                        <i class="fas fa-exclamation-triangle text-3xl mb-2"></i>
                        <p class="text-sm">Error al buscar productos</p>
                    </div>
                `;
            });
    }, 300);
}

function seleccionarProducto(producto) {
    // Guardar el ID en el campo oculto
    document.getElementById('modal_inventario_id').value = producto.id;
    
    // Mostrar información del producto seleccionado
    const productoInfo = `
        <p class="font-semibold text-gray-900">${producto.descripcion}</p>
        <p class="text-xs mt-1">
            <span class="font-medium">CB:</span> ${producto.codigo_barras || 'N/A'} | 
            <span class="font-medium">CP:</span> ${producto.codigo_proveedor || 'N/A'}
        </p>
        <p class="text-xs mt-1">
            <i class="fas fa-tag mr-1"></i>${producto.categoria} • 
            <i class="fas fa-truck mr-1"></i>${producto.proveedor}
        </p>
    `;
    document.getElementById('producto_info').innerHTML = productoInfo;
    document.getElementById('producto_seleccionado').classList.remove('hidden');
    
    // Ocultar resultados y limpiar búsqueda
    document.getElementById('resultados_busqueda').classList.add('hidden');
    document.getElementById('buscar_producto').value = '';
}

function limpiarProductoSeleccionado() {
    document.getElementById('modal_inventario_id').value = '';
    document.getElementById('producto_seleccionado').classList.add('hidden');
    document.getElementById('producto_info').innerHTML = '';
    document.getElementById('buscar_producto').value = '';
    document.getElementById('resultados_busqueda').classList.add('hidden');
    document.getElementById('resultados_busqueda').innerHTML = '';
}

function cerrarModal() {
    const modal = document.getElementById('asignarProductoModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto'; // Restaurar scroll
}

function asignarProducto(event) {
    event.preventDefault();
    const form = document.getElementById('asignarProductoForm');
    const formData = new FormData(form);
    
    // Validar que se haya seleccionado una ubicación
    if (!document.getElementById('modal_warehouse_id').value) {
        alert('Por favor, seleccione una ubicación de la lista de búsqueda');
        return;
    }
    
    // Validar que se haya seleccionado un producto
    if (!document.getElementById('modal_inventario_id').value) {
        alert('Por favor, seleccione un producto de la lista de búsqueda');
        return;
    }
    
    // Deshabilitar botón submit
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
    
    fetch('{{ route("warehouse-inventory.asignar") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
        
        if (data.estado) {
            alert(data.msj);
            window.location.reload();
        } else {
            alert(data.msj || 'Error al asignar el producto');
        }
    })
    .catch(error => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
        console.error('Error:', error);
        alert('Error al procesar la solicitud: ' + error.message);
    });
}

function verDetalles(id) {
    // Implementar modal o redirección a vista de detalles
    console.log('Ver detalles de inventario:', id);
}

function moverInventario(id) {
    // Implementar modal o redirección para mover inventario
    console.log('Mover inventario:', id);
}
</script>
@endsection

