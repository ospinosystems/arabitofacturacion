@extends('layouts.app')

@section('content')
@include('warehouse-inventory.partials.nav')

<div class="container-fluid px-2 sm:px-4">
    <!-- Header -->
    <div class="mb-3 sm:mb-4">
        <div class="flex items-center gap-2">
            <h1 class="text-xl sm:text-2xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-search-location text-blue-500 mr-2"></i>
                <span class="hidden sm:inline">Consultar por Ubicación</span>
                <span class="sm:hidden">Consultar Ubicación</span>
            </h1>
        </div>
    </div>

    <!-- Selector de Ubicación -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-3 sm:mb-4">
        <div class="p-3 sm:p-4">
            <form id="ubicacionForm" onsubmit="event.preventDefault();">
                <div class="grid grid-cols-1 lg:grid-cols-4 gap-3">
                    <!-- Buscar Ubicación -->
                    <div class="lg:col-span-2">
                        <label for="buscar_ubicacion" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-search text-blue-500 mr-1"></i>
                            Buscar Ubicación <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="text" 
                                   id="buscar_ubicacion" 
                                   placeholder="Buscar por código, nombre o tipo..."
                                   autocomplete="off"
                                   value="{{ $warehouse ? $warehouse->codigo . ' - ' . $warehouse->nombre : '' }}"
                                   oninput="buscarUbicaciones(this.value)"
                                   class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                            <input type="hidden" id="warehouse_id" name="warehouse_id" value="{{ request('warehouse_id') }}">
                            <div class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">
                                <i class="fas fa-search"></i>
                            </div>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">
                            <i class="fas fa-info-circle"></i> Escribe para buscar una ubicación
                        </p>

                        <!-- Resultados de búsqueda -->
                        <div id="resultados_ubicaciones"
                             class="hidden mt-2 max-h-60 overflow-y-auto rounded-lg border border-gray-200 shadow-lg bg-white absolute z-20 w-full lg:w-auto"
                             style="max-width: calc(100% - 2rem);">
                        </div>

                        <!-- Ubicación seleccionada -->
                        <div id="ubicacion_seleccionada_info"
                             class="{{ $warehouse ? '' : 'hidden' }} mt-3 rounded-lg bg-blue-50 border border-blue-200 p-3 relative">
                            <button type="button"
                                    onclick="limpiarUbicacion()"
                                    class="absolute top-2 right-2 text-red-500 hover:text-red-700 focus:outline-none">
                                <i class="fas fa-times-circle text-lg"></i>
                            </button>
                            <div class="pr-8">
                                <p class="text-sm font-semibold text-blue-800 mb-1">
                                    <i class="fas fa-check-circle mr-1"></i> Ubicación seleccionada
                                </p>
                                <div id="ubicacion_info_detalle" class="text-xs text-gray-700">
                                    @if($warehouse)
                                        <strong>{{ $warehouse->codigo }}</strong> - {{ $warehouse->nombre }} 
                                        <span class="inline-flex px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-800 ml-1">{{ $warehouse->tipo }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filtro de Estado -->
                    <div>
                        <label for="estado" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-tag text-blue-500 mr-1"></i>
                            Estado
                        </label>
                        <select id="estado" 
                                name="estado" 
                                onchange="aplicarFiltros()"
                                class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                            <option value="">Todos</option>
                            <option value="disponible" {{ request('estado') == 'disponible' ? 'selected' : '' }}>Disponible</option>
                            <option value="reservado" {{ request('estado') == 'reservado' ? 'selected' : '' }}>Reservado</option>
                            <option value="cuarentena" {{ request('estado') == 'cuarentena' ? 'selected' : '' }}>Cuarentena</option>
                        </select>
                    </div>
                    
                    <!-- Buscar Producto -->
                    <div>
                        <label for="buscar" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-search text-blue-500 mr-1"></i>
                            Buscar Producto
                        </label>
                        <input type="text" 
                               id="buscar" 
                               name="buscar" 
                               value="{{ request('buscar') }}"
                               placeholder="Producto..."
                               onkeyup="aplicarFiltrosConDelay()"
                               class="block w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Información de la Ubicación -->
    <div id="infoUbicacion" class="{{ $warehouse ? '' : 'hidden' }}">
        @if($warehouse)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <!-- Card 1: Información General -->
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow-md p-4 ">
                <div class="flex items-center justify-between mb-2">
                    <i class="fas fa-map-marker-alt text-2xl opacity-80"></i>
                    <span class="text-xs font-semibold bg-white bg-opacity-20 px-2 py-1 rounded">{{ $warehouse->tipo }}</span>
                </div>
                <h3 class="text-lg font-bold mb-1">{{ $warehouse->codigo }}</h3>
                <p class="text-sm opacity-90">{{ $warehouse->nombre }}</p>
            </div>

            <!-- Card 2: Capacidad -->
            <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-lg shadow-md p-4 ">
                <div class="flex items-center justify-between mb-2">
                    <i class="fas fa-box text-2xl opacity-80"></i>
                    <span class="text-xs font-semibold">Capacidad</span>
                </div>
                <h3 class="text-2xl font-bold mb-1">{{ number_format($warehouse->capacidad_maxima ?? 0, 0) }}</h3>
                <p class="text-sm opacity-90">Unidades máximas</p>
            </div>

            <!-- Card 3: Ocupación -->
            <div class="bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-lg shadow-md p-4 ">
                <div class="flex items-center justify-between mb-2">
                    <i class="fas fa-chart-pie text-2xl opacity-80"></i>
                    <span class="text-xs font-semibold">Ocupación</span>
                </div>
                @php
                    $ocupacion = $warehouse->capacidad_maxima > 0 
                        ? ($warehouse->inventarios()->conStock()->sum('cantidad') / $warehouse->capacidad_maxima) * 100 
                        : 0;
                @endphp
                <h3 class="text-2xl font-bold mb-1">{{ number_format($ocupacion, 1) }}%</h3>
                <p class="text-sm opacity-90">Utilizado</p>
            </div>

            <!-- Card 4: Total Productos -->
            <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg shadow-md p-4 ">
                <div class="flex items-center justify-between mb-2">
                    <i class="fas fa-boxes text-2xl opacity-80"></i>
                    <span class="text-xs font-semibold">Productos</span>
                </div>
                <h3 class="text-2xl font-bold mb-1">{{ $inventarios->total() }}</h3>
                <p class="text-sm opacity-90">Diferentes</p>
            </div>
        </div>
        @endif
    </div>

    <!-- Tabla de Productos -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="relative min-h-[200px]">
            <!-- Loading Overlay -->
            <div id="loadingOverlay" class="absolute inset-0 bg-white bg-opacity-90 hidden z-10 flex items-center justify-center">
                <div class="text-center">
                    <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mb-3"></div>
                    <p class="text-sm text-gray-600">Consultando inventario...</p>
                </div>
            </div>
            
            @if(!$warehouse)
            <!-- Mensaje inicial -->
            <div class="p-8 sm:p-12 text-center">
                <i class="fas fa-search-location text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-lg font-semibold text-gray-700 mb-2">Seleccione una ubicación</h3>
                <p class="text-sm text-gray-500">Escoja una ubicación del almacén para consultar su inventario disponible</p>
            </div>
            @else
            
            <!-- Vista Desktop -->
            <div class="hidden lg:block overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Producto</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Proveedor</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Lote</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Cantidad</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Estado</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">F. Entrada</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">F. Vencimiento</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tablaBodyDesktop" class="divide-y divide-gray-200">
                        @forelse($inventarios as $inv)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-4 py-3">
                                    <div class="text-sm font-semibold text-gray-900">{{ $inv->inventario->descripcion }}</div>
                                    <div class="text-xs text-gray-500">{{ $inv->inventario->codigo_barras }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm text-gray-900">{{ $inv->inventario->proveedor->razonsocial ?? 'N/A' }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ $inv->lote ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                        {{ number_format($inv->cantidad, 2) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    @if($inv->estado == 'disponible')
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Disponible</span>
                                    @elseif($inv->estado == 'reservado')
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Reservado</span>
                                    @else
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Cuarentena</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    {{ $inv->fecha_entrada ? \Carbon\Carbon::parse($inv->fecha_entrada)->format('d/m/Y') : '-' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    @if($inv->fecha_vencimiento)
                                        {{ \Carbon\Carbon::parse($inv->fecha_vencimiento)->format('d/m/Y') }}
                                        @if(\Carbon\Carbon::parse($inv->fecha_vencimiento)->lt(now()->addDays(30)))
                                            <span class="inline-flex px-1.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-800 ml-1">Próximo</span>
                                        @endif
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <button onclick="verDetallesProducto({{ $inv->id }})" 
                                            class="p-1.5 text-blue-600 hover:bg-blue-50 rounded transition"
                                            title="Ver detalles">
                                        <i class="fas fa-eye text-sm"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                    <i class="fas fa-inbox text-4xl mb-3 block"></i>
                                    <p>No hay productos en esta ubicación</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <!-- Vista Mobile (Cards) -->
            <div id="tablaBodyMobile" class="lg:hidden divide-y divide-gray-200">
                @forelse($inventarios as $inv)
                    <div class="p-3 hover:bg-gray-50 transition">
                        <!-- Header Card -->
                        <div class="flex justify-between items-start mb-2">
                            <div class="flex-1">
                                <div class="text-sm font-semibold text-gray-900">{{ $inv->inventario->descripcion }}</div>
                                <div class="text-xs text-gray-500 mt-0.5">{{ $inv->inventario->codigo_barras }}</div>
                            </div>
                            <div class="ml-2">
                                @if($inv->estado == 'disponible')
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Disponible</span>
                                @elseif($inv->estado == 'reservado')
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Reservado</span>
                                @else
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Cuarentena</span>
                                @endif
                            </div>
                        </div>
                        
                        <!-- Info Grid -->
                        <div class="grid grid-cols-2 gap-2 text-xs mb-2">
                            <div>
                                <span class="text-gray-500">Proveedor:</span>
                                <span class="font-medium text-gray-900 ml-1">{{ $inv->inventario->proveedor->razonsocial ?? 'N/A' }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500">Cantidad:</span>
                                <span class="inline-flex px-1.5 py-0.5 ml-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                    {{ number_format($inv->cantidad, 2) }}
                                </span>
                            </div>
                            @if($inv->lote)
                            <div>
                                <span class="text-gray-500">Lote:</span>
                                <span class="font-medium text-gray-900 ml-1">{{ $inv->lote }}</span>
                            </div>
                            @endif
                            @if($inv->fecha_vencimiento)
                            <div>
                                <span class="text-gray-500">Vence:</span>
                                <span class="font-medium text-gray-900 ml-1">{{ \Carbon\Carbon::parse($inv->fecha_vencimiento)->format('d/m/Y') }}</span>
                            </div>
                            @endif
                        </div>
                        
                        <!-- Acciones -->
                        <button onclick="verDetallesProducto({{ $inv->id }})" 
                                class="w-full px-3 py-2 text-xs font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition">
                            <i class="fas fa-eye mr-1"></i> Ver Detalles
                        </button>
                    </div>
                @empty
                    <div class="p-8 text-center text-gray-500">
                        <i class="fas fa-inbox text-4xl mb-3 block"></i>
                        <p class="text-sm">No hay productos en esta ubicación</p>
                    </div>
                @endforelse
            </div>
            
            <!-- Paginación -->
            @if($inventarios->hasPages())
            <div class="p-3 sm:p-4 border-t border-gray-200 flex justify-center">
                <div id="paginacionContainer">
                    {{ $inventarios->appends(request()->query())->links() }}
                </div>
            </div>
            @endif
            @endif
        </div>
    </div>
</div>

<script>
let searchTimeout;
let searchUbicacionTimeout;

// Buscar ubicaciones
function buscarUbicaciones(valor) {
    clearTimeout(searchUbicacionTimeout);
    
    const resultados = document.getElementById('resultados_ubicaciones');
    
    if (!valor || valor.length < 1) {
        resultados.classList.add('hidden');
        resultados.innerHTML = '';
        return;
    }
    
    searchUbicacionTimeout = setTimeout(() => {
        fetch('{{ route("warehouses.buscar") }}?buscar=' + encodeURIComponent(valor))
            .then(response => response.json())
            .then(data => {
                if (data.ubicaciones && data.ubicaciones.length > 0) {
                    let html = '<div class="divide-y divide-gray-200">';
                    
                    data.ubicaciones.forEach(ubicacion => {
                        html += `
                            <div class="p-3 hover:bg-blue-50 cursor-pointer transition"
                                 onclick="seleccionarUbicacion(${ubicacion.id}, '${ubicacion.codigo}', '${ubicacion.nombre}', '${ubicacion.tipo}')">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <div class="text-sm font-semibold text-gray-900">
                                            <i class="fas fa-map-marker-alt text-blue-500 mr-1"></i>
                                            ${ubicacion.codigo}
                                        </div>
                                        <div class="text-xs text-gray-600 mt-0.5">${ubicacion.nombre}</div>
                                    </div>
                                    <div class="ml-3">
                                        <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">
                                            ${ubicacion.tipo}
                                        </span>
                                    </div>
                                </div>
                                ${ubicacion.ubicacion ? `<div class="text-xs text-gray-500 mt-1"><i class="fas fa-location-arrow mr-1"></i>${ubicacion.ubicacion}</div>` : ''}
                            </div>
                        `;
                    });
                    
                    html += '</div>';
                    resultados.innerHTML = html;
                    resultados.classList.remove('hidden');
                } else {
                    resultados.innerHTML = `
                        <div class="p-4 text-center text-gray-500 text-sm">
                            <i class="fas fa-search text-2xl mb-2 block"></i>
                            No se encontraron ubicaciones
                        </div>
                    `;
                    resultados.classList.remove('hidden');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                resultados.classList.add('hidden');
            });
    }, 300);
}

// Seleccionar ubicación
function seleccionarUbicacion(id, codigo, nombre, tipo) {
    document.getElementById('warehouse_id').value = id;
    document.getElementById('buscar_ubicacion').value = codigo + ' - ' + nombre;
    document.getElementById('resultados_ubicaciones').classList.add('hidden');
    
    // Mostrar info de ubicación seleccionada
    const infoDiv = document.getElementById('ubicacion_seleccionada_info');
    const detalleDiv = document.getElementById('ubicacion_info_detalle');
    
    detalleDiv.innerHTML = `
        <strong>${codigo}</strong> - ${nombre} 
        <span class="inline-flex px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-800 ml-1">${tipo}</span>
    `;
    
    infoDiv.classList.remove('hidden');
    
    // Consultar inventario de esta ubicación
    consultarUbicacion();
}

// Limpiar ubicación seleccionada
function limpiarUbicacion() {
    document.getElementById('warehouse_id').value = '';
    document.getElementById('buscar_ubicacion').value = '';
    document.getElementById('ubicacion_seleccionada_info').classList.add('hidden');
    document.getElementById('resultados_ubicaciones').classList.add('hidden');
    
    // Ocultar la información y tabla
    document.getElementById('infoUbicacion').classList.add('hidden');
    
    // Redirigir a la página sin ubicación
    window.location.href = '{{ route("warehouse-inventory.por-ubicacion") }}';
}

// Consultar ubicación
function consultarUbicacion() {
    const warehouseId = document.getElementById('warehouse_id').value;
    
    if (!warehouseId) {
        return;
    }
    
    // Construir URL con parámetros
    const params = new URLSearchParams({
        warehouse_id: warehouseId,
        estado: document.getElementById('estado').value,
        buscar: document.getElementById('buscar').value
    });
    
    // Redirigir con los parámetros
    window.location.href = '{{ route("warehouse-inventory.por-ubicacion") }}?' + params.toString();
}

// Aplicar filtros
function aplicarFiltros() {
    const warehouseId = document.getElementById('warehouse_id').value;
    
    if (!warehouseId) {
        alert('Por favor selecciona una ubicación primero');
        return;
    }
    
    consultarUbicacion();
}

function aplicarFiltrosConDelay() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        aplicarFiltros();
    }, 500);
}

function verDetallesProducto(id) {
    // Aquí puedes implementar un modal con detalles del producto
    alert('Ver detalles del producto ID: ' + id);
}

// Cerrar resultados al hacer clic fuera
document.addEventListener('click', function(event) {
    const buscarInput = document.getElementById('buscar_ubicacion');
    const resultados = document.getElementById('resultados_ubicaciones');
    
    if (buscarInput && resultados && !buscarInput.contains(event.target) && !resultados.contains(event.target)) {
        resultados.classList.add('hidden');
    }
});
</script>
@endsection
