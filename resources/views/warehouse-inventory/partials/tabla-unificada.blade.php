<!-- Filtros de Búsqueda -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-4 p-4">
    <form method="GET" action="{{ route('warehouse-inventory.index') }}" id="formBusqueda" class="space-y-4">
        <!-- Primera fila de filtros -->
        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
            <!-- Campo de búsqueda de producto -->
            <div class="md:col-span-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Buscar Producto</label>
                <input type="text" 
                       name="buscar" 
                       id="buscar_producto"
                       value="{{ request('buscar') }}" 
                       placeholder="Buscar producto..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
            </div>
            
            <!-- Tipo de búsqueda -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Buscar por</label>
                <select name="campo_busqueda" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    <option value="todos" {{ request('campo_busqueda', 'todos') === 'todos' ? 'selected' : '' }}>Todos</option>
                    <option value="codigo_barras" {{ request('campo_busqueda') === 'codigo_barras' ? 'selected' : '' }}>Código Barras</option>
                    <option value="codigo_proveedor" {{ request('campo_busqueda') === 'codigo_proveedor' ? 'selected' : '' }}>Código Proveedor</option>
                    <option value="descripcion" {{ request('campo_busqueda') === 'descripcion' ? 'selected' : '' }}>Descripción</option>
                </select>
            </div>
            
            <!-- NUEVO: Buscar por ubicación -->
            <div class="md:col-span-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    <i class="fas fa-map-marker-alt text-green-500 mr-1"></i>
                    Escanear Ubicación
                </label>
                <div class="relative">
                    <input type="text" 
                           id="buscar_ubicacion_input"
                           placeholder="Escanee código de ubicación..."
                           value="{{ request('ubicacion_codigo') }}"
                           class="w-full px-3 py-2 pr-20 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500 {{ request('ubicacion_id') ? 'border-green-500 bg-green-50' : '' }}">
                    <input type="hidden" name="ubicacion_id" id="ubicacion_id_hidden" value="{{ request('ubicacion_id') }}">
                    <input type="hidden" name="ubicacion_codigo" id="ubicacion_codigo_hidden" value="{{ request('ubicacion_codigo') }}">
                    @if(request('ubicacion_id'))
                        <span class="absolute right-2 top-2 text-xs bg-green-100 text-green-800 px-2 py-1 rounded">
                            <i class="fas fa-check mr-1"></i>Activo
                        </span>
                    @endif
                </div>
            </div>
            
            <!-- Filtro por Estado de Ubicación -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    <i class="fas fa-warehouse text-blue-500 mr-1"></i>
                    Estado Ubicación
                </label>
                <select name="filtro_warehouse" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    <option value="" {{ request('filtro_warehouse', '') === '' ? 'selected' : '' }}>Todos (Ambos)</option>
                    <option value="con_warehouse" {{ request('filtro_warehouse') === 'con_warehouse' ? 'selected' : '' }}>✓ Con ubicación</option>
                    <option value="sin_warehouse" {{ request('filtro_warehouse') === 'sin_warehouse' ? 'selected' : '' }}>✗ Sin ubicación</option>
                </select>
            </div>
            
            <!-- Botón de búsqueda -->
            <div class="md:col-span-2 flex items-end gap-2">
                <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md transition">
                    <i class="fas fa-search"></i> Buscar
                </button>
                @if(request('ubicacion_id') || request('buscar') || request('categoria_id') || request('proveedor_id'))
                    <a href="{{ route('warehouse-inventory.index') }}" class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white font-medium rounded-md transition">
                        <i class="fas fa-times"></i>
                    </a>
                @endif
            </div>
        </div>
        
        <!-- Segunda fila de filtros -->
        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
            <!-- Categoría -->
            <div class="md:col-span-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Categoría</label>
                <select name="categoria_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todas</option>
                    @foreach($categorias ?? [] as $categoria)
                        <option value="{{ $categoria->id }}" {{ request('categoria_id') == $categoria->id ? 'selected' : '' }}>
                            {{ $categoria->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            
            <!-- Proveedor -->
            <div class="md:col-span-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Proveedor</label>
                <select name="proveedor_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos</option>
                    @foreach($proveedores ?? [] as $proveedor)
                        <option value="{{ $proveedor->id }}" {{ request('proveedor_id') == $proveedor->id ? 'selected' : '' }}>
                            {{ $proveedor->razonsocial }}
                        </option>
                    @endforeach
                </select>
            </div>
            
            <!-- Marca -->
            <div class="md:col-span-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Marca</label>
                <select name="marca_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todas</option>
                    @foreach($marcas ?? [] as $marca)
                        <option value="{{ $marca->id }}" {{ request('marca_id') == $marca->id ? 'selected' : '' }}>
                            {{ $marca->descripcion }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        
        <!-- Mostrar filtro activo de ubicación -->
        @if(request('ubicacion_id'))
            <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fas fa-filter text-green-600 mr-2"></i>
                        <span class="text-sm font-medium text-green-800">
                            Filtrando por ubicación: <strong>{{ request('ubicacion_codigo') }}</strong>
                        </span>
                    </div>
                    <button type="button" onclick="limpiarFiltroUbicacion()" class="text-green-600 hover:text-green-800">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        @endif
    </form>
</div>

<!-- Tabla de Productos Unificada -->
<style>
    /* Estilos específicos para tablets */
    @media (min-width: 768px) and (max-width: 1024px) {
        .table-container {
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
        }
        
        .table-container::-webkit-scrollbar {
            height: 8px;
        }
        
        .table-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        .table-container::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }
        
        .table-container::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        /* Mejorar legibilidad de ubicaciones en tablets */
        .ubicacion-item {
            min-width: 100%;
            padding: 0.5rem;
        }
        
        .ubicacion-item .ubicacion-codigo {
            word-break: break-word;
            hyphens: auto;
        }
    }
</style>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto table-container">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-2 md:px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Código</th>
                    <th class="px-2 md:px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[150px]">Descripción</th>
                    <th class="px-2 md:px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider hidden lg:table-cell">Stock General</th>
                    <th class="px-2 md:px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider hidden lg:table-cell">En Ubicaciones</th>
                    <th class="px-2 md:px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">Estado</th>
                    <th class="px-2 md:px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[280px] md:min-w-[320px] lg:min-w-[380px]">Ubicaciones Asignadas</th>
                    <th class="px-2 md:px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($productos ?? [] as $producto)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-2 md:px-4 py-3">
                            <div class="text-sm font-medium text-gray-900 break-words">{{ $producto->codigo_barras }}</div>
                            @if($producto->codigo_proveedor)
                                <div class="text-xs text-gray-500 break-words">Prov: {{ $producto->codigo_proveedor }}</div>
                            @endif
                            {{-- Mostrar stock general en móvil/tablet --}}
                            <div class="lg:hidden mt-1">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $producto->existencia > 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    Stock: {{ number_format($producto->cantidad, 2) }}
                                </span>
                            </div>
                        </td>
                        <td class="px-2 md:px-4 py-3">
                            <div class="text-sm font-medium text-gray-900 break-words">{{ $producto->descripcion }}</div>
                            <div class="text-xs text-gray-500 break-words">
                                @if($producto->proveedor)
                                    {{ $producto->proveedor->razonsocial }}
                                @endif
                                @if($producto->categoria)
                                    <span class="mx-1">•</span>
                                    {{ $producto->categoria->nombre }}
                                @endif
                            </div>
                            {{-- Mostrar estado en móvil/tablet --}}
                            <div class="md:hidden mt-2">
                                @if($producto->tiene_warehouse ?? false)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                        <i class="fas fa-check-circle mr-1"></i>Ubicado
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                                        <i class="fas fa-times-circle mr-1"></i>Sin Ubicar
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td class="px-2 md:px-4 py-3 text-center hidden lg:table-cell">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium {{ $producto->existencia > 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ number_format($producto->cantidad, 2) }}
                            </span>
                        </td>
                        <td class="px-2 md:px-4 py-3 text-center hidden lg:table-cell">
                            @if($producto->tiene_warehouse ?? false)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                    {{ number_format($producto->total_en_warehouses ?? 0, 2) }}
                                </span>
                            @else
                                <span class="text-sm text-gray-400">0.00</span>
                            @endif
                        </td>
                        <td class="px-2 md:px-4 py-3 text-center hidden md:table-cell">
                            @if($producto->tiene_warehouse ?? false)
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800 border border-green-200">
                                    <i class="fas fa-check-circle mr-1"></i>
                                    Ubicado
                                </span>
                            @else
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800 border border-red-200">
                                    <i class="fas fa-times-circle mr-1"></i>
                                    Sin Ubicar
                                </span>
                            @endif
                        </td>
                        <td class="px-2 md:px-4 py-3">
                            @if($producto->tiene_warehouse ?? false)
                                <div class="space-y-1.5 min-w-[250px]">
                                    @foreach($producto->ubicaciones_detalle ?? [] as $ubicacion)
                                        @php
                                            // Verificar si esta ubicación coincide con el filtro
                                            $esUbicacionFiltrada = request('ubicacion_id') && $ubicacion['warehouse_id'] == request('ubicacion_id');
                                        @endphp
                                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1.5 sm:gap-2 rounded px-2.5 py-1.5 {{ $esUbicacionFiltrada ? 'bg-green-100 border-2 border-green-400 shadow-md' : 'bg-gray-50 border border-gray-200' }}">
                                            <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-2 flex-1 min-w-0">
                                                <div class="flex items-center gap-1.5 min-w-0">
                                                @if($esUbicacionFiltrada)
                                                        <i class="fas fa-map-marker-alt text-green-600 text-xs animate-pulse flex-shrink-0"></i>
                                                @endif
                                                    <span class="text-xs sm:text-sm font-semibold {{ $esUbicacionFiltrada ? 'text-green-700' : 'text-blue-600' }} break-all">
                                                    {{ $ubicacion['warehouse'] }}
                                                </span>
                                                </div>
                                                @if($ubicacion['lote'])
                                                    <span class="text-xs text-gray-500 whitespace-nowrap">Lote: {{ $ubicacion['lote'] }}</span>
                                                @endif
                                            </div>
                                            <div class="flex items-center gap-2 flex-shrink-0">
                                                <span class="text-xs sm:text-sm font-medium {{ $esUbicacionFiltrada ? 'text-green-900' : 'text-gray-900' }} whitespace-nowrap">
                                                    {{ number_format($ubicacion['cantidad'], 2) }}
                                                </span>
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium whitespace-nowrap
                                                    {{ $ubicacion['estado'] === 'disponible' ? 'bg-green-100 text-green-800' : ($ubicacion['estado'] === 'reservado' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                                    {{ ucfirst($ubicacion['estado']) }}
                                                </span>
                                            </div>
                                        </div>
                                    @endforeach
                                    
                                    {{-- Mostrar cantidad sin ubicar si existe --}}
                                    @if(($producto->sin_ubicar ?? 0) > 0)
                                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1.5 sm:gap-2 bg-orange-50 border border-orange-200 rounded px-2.5 py-1.5 mt-1">
                                            <div class="flex items-center gap-1.5">
                                                <i class="fas fa-box text-orange-600 text-xs flex-shrink-0"></i>
                                                <span class="text-xs sm:text-sm font-semibold text-orange-700">Sin Ubicar</span>
                                            </div>
                                            <div class="flex items-center gap-2 flex-shrink-0">
                                                <span class="text-xs sm:text-sm font-medium text-orange-900 whitespace-nowrap">{{ number_format($producto->sin_ubicar, 2) }}</span>
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800 whitespace-nowrap">
                                                    Disponible
                                                </span>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @else
                                {{-- Si no tiene ubicaciones, todo el stock está sin ubicar --}}
                                @if(($producto->cantidad ?? 0) > 0)
                                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1.5 sm:gap-2 bg-orange-50 border border-orange-200 rounded px-2.5 py-1.5 min-w-[200px]">
                                        <div class="flex items-center gap-1.5">
                                            <i class="fas fa-box text-orange-600 text-xs flex-shrink-0"></i>
                                            <span class="text-xs sm:text-sm font-semibold text-orange-700">Sin Ubicar</span>
                                        </div>
                                        <div class="flex items-center gap-2 flex-shrink-0">
                                            <span class="text-xs sm:text-sm font-medium text-orange-900 whitespace-nowrap">{{ number_format($producto->cantidad, 2) }}</span>
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800 whitespace-nowrap">
                                                Disponible
                                            </span>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-xs text-gray-400 italic">Sin stock disponible</span>
                                @endif
                            @endif
                        </td>
                        <td class="px-2 md:px-4 py-3">
                            <div class="flex items-center justify-center space-x-1 sm:space-x-2">
                                @if($producto->tiene_warehouse ?? false)
                                    <button type="button" 
                                            onclick="verDetallesUbicaciones({{ $producto->id }})"
                                            class="p-1.5 sm:p-2 text-blue-600 hover:bg-blue-50 rounded transition"
                                            title="Ver detalles completos">
                                        <i class="fas fa-eye text-sm sm:text-base"></i>
                                    </button>
                                @endif
                                <button type="button" 
                                        onclick="asignarProducto({{ $producto->id }})"
                                        class="p-1.5 sm:p-2 text-green-600 hover:bg-green-50 rounded transition"
                                        title="Asignar a ubicación">
                                    <i class="fas fa-plus-circle text-sm sm:text-base"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center">
                            <div class="flex flex-col items-center justify-center text-gray-400">
                                <i class="fas fa-search fa-3x mb-3"></i>
                                <p class="text-lg font-medium">No se encontraron productos</p>
                                <p class="text-sm">Intenta con otros criterios de búsqueda</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    <!-- Paginación -->
    @if(isset($productos) && $productos->hasPages())
        <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
            {{ $productos->appends(request()->query())->links() }}
        </div>
    @endif
</div>

<!-- Modal para ver detalles completos -->
<div id="modalDetalles" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-2/3 shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Detalles Completos de Ubicaciones</h3>
            <button onclick="cerrarModal('modalDetalles')" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times fa-lg"></i>
            </button>
        </div>
        <div id="contenidoDetalles" class="mt-4">
            <!-- Se cargará dinámicamente -->
        </div>
    </div>
</div>

<script>
// Buscar ubicación por código escaneado
document.getElementById('buscar_ubicacion_input')?.addEventListener('change', function() {
    const codigo = this.value.trim();
    if (!codigo) return;
    
    fetch(`/warehouses/buscar?buscar=${encodeURIComponent(codigo)}`)
        .then(response => response.json())
        .then(data => {
            if (data.ubicaciones && data.ubicaciones.length > 0) {
                const warehouse = data.ubicaciones[0];
                // Guardar valores en campos ocultos
                document.getElementById('ubicacion_id_hidden').value = warehouse.id;
                document.getElementById('ubicacion_codigo_hidden').value = warehouse.codigo;
                // Mostrar confirmación visual
                this.value = warehouse.codigo;
                this.classList.add('border-green-500', 'bg-green-50');
                mostrarNotificacion(`Ubicación encontrada: ${warehouse.codigo}`, 'success');
                
                // Esperar a que los valores estén asignados antes de hacer submit
                setTimeout(() => {
                    // Verificar que los valores estén asignados
                    const idAsignado = document.getElementById('ubicacion_id_hidden').value;
                    const codigoAsignado = document.getElementById('ubicacion_codigo_hidden').value;
                    
                    if (idAsignado && codigoAsignado) {
                        document.getElementById('formBusqueda').submit();
                    } else {
                        console.error('Error: valores no asignados correctamente');
                        mostrarNotificacion('Error al procesar la búsqueda', 'error');
                    }
                }, 100);
            } else {
                mostrarNotificacion('Ubicación no encontrada', 'warning');
                this.value = '';
                this.focus();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarNotificacion('Error al buscar ubicación', 'error');
        });
});

// Limpiar filtro de ubicación
function limpiarFiltroUbicacion() {
    document.getElementById('ubicacion_id_hidden').value = '';
    document.getElementById('ubicacion_codigo_hidden').value = '';
    document.getElementById('buscar_ubicacion_input').value = '';
    document.getElementById('buscar_ubicacion_input').classList.remove('border-green-500', 'bg-green-50');
    document.getElementById('formBusqueda').submit();
}

// Al cargar la página, si el input tiene valor, permitir escanearlo de nuevo
document.addEventListener('DOMContentLoaded', function() {
    const inputUbicacion = document.getElementById('buscar_ubicacion_input');
    if (inputUbicacion) {
        // Al hacer focus, preparar para nuevo escaneo si hay filtro activo
        inputUbicacion.addEventListener('focus', function() {
            if (this.value && document.getElementById('ubicacion_id_hidden').value) {
                // Si ya hay un filtro activo, seleccionar todo para reemplazar al escanear
                this.select();
            }
        });
        
        // Al hacer click en el input cuando hay filtro activo, permitir limpiar
        inputUbicacion.addEventListener('click', function() {
            if (this.value && document.getElementById('ubicacion_id_hidden').value) {
                this.select();
            }
        });
    }
});

// Sistema de Notificaciones Toast
function mostrarNotificacion(mensaje, tipo = 'info') {
    const notificacion = document.createElement('div');
    const iconos = {
        success: '<i class="fas fa-check-circle"></i>',
        error: '<i class="fas fa-exclamation-circle"></i>',
        warning: '<i class="fas fa-exclamation-triangle"></i>',
        info: '<i class="fas fa-info-circle"></i>'
    };
    const colores = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        warning: 'bg-yellow-500',
        info: 'bg-blue-500'
    };
    
    notificacion.className = `fixed top-4 right-4 ${colores[tipo]} text-white px-6 py-4 rounded-lg shadow-lg flex items-center space-x-3 z-[9999] transform transition-all duration-300 translate-x-0`;
    notificacion.innerHTML = `
        ${iconos[tipo]}
        <span class="font-medium">${mensaje}</span>
    `;
    
    document.body.appendChild(notificacion);
    
    // Animación de entrada
    setTimeout(() => {
        notificacion.style.transform = 'translateX(0)';
    }, 10);
    
    // Auto-remover después de 3 segundos
    setTimeout(() => {
        notificacion.style.transform = 'translateX(400px)';
        setTimeout(() => {
            document.body.removeChild(notificacion);
        }, 300);
    }, 3000);
}

function verDetallesUbicaciones(productoId) {
    fetch(`/warehouse-inventory/producto/${productoId}`)
        .then(response => response.json())
        .then(data => {
            if (data.estado) {
                const ubicaciones = data.data;
                let html = '<div class="space-y-3">';
                
                if (ubicaciones.length > 0) {
                    ubicaciones.forEach(ubi => {
                        const vencimiento = ubi.fecha_vencimiento ? new Date(ubi.fecha_vencimiento).toLocaleDateString('es-ES') : '-';
                        const entrada = ubi.fecha_entrada ? new Date(ubi.fecha_entrada).toLocaleDateString('es-ES') : '-';
                        const estadoClass = ubi.estado === 'disponible' ? 'bg-green-100 text-green-800' : (ubi.estado === 'reservado' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');
                        
                        html += `
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <span class="text-lg font-semibold text-blue-600">${ubi.warehouse.codigo}</span>
                                        <span class="text-sm text-gray-500 ml-2">${ubi.warehouse.nombre || ''}</span>
                                    </div>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ${estadoClass}">
                                        ${ubi.estado.charAt(0).toUpperCase() + ubi.estado.slice(1)}
                                    </span>
                                </div>
                                <div class="grid grid-cols-2 gap-3 text-sm">
                                    <div><span class="font-medium">Cantidad:</span> ${parseFloat(ubi.cantidad).toFixed(2)} unidades</div>
                                    <div><span class="font-medium">Lote:</span> ${ubi.lote || '-'}</div>
                                    <div><span class="font-medium">Fecha Entrada:</span> ${entrada}</div>
                                    <div><span class="font-medium">Vencimiento:</span> ${vencimiento}</div>
                                </div>
                                <div class="mt-2 flex justify-end space-x-2">
                                    <a href="/warehouse-inventory/${ubi.id}/ticket" target="_blank" class="px-3 py-1 text-xs bg-blue-100 text-blue-700 rounded hover:bg-blue-200">
                                        <i class="fas fa-print mr-1"></i> Imprimir
                                    </a>
                                </div>
                            </div>
                        `;
                    });
                } else {
                    html += '<p class="text-gray-500 text-center py-4">No hay ubicaciones asignadas</p>';
                }
                
                html += '</div>';
                document.getElementById('contenidoDetalles').innerHTML = html;
                document.getElementById('modalDetalles').classList.remove('hidden');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarNotificacion('Error al cargar ubicaciones', 'error');
        });
}

function asignarProducto(productoId, productoData = null) {
    // Si se pasa el producto completo desde la tabla
    if (productoData) {
        document.getElementById('asignar_inventario_id').value = productoData.id;
        document.getElementById('asignar_producto_codigo').value = productoData.codigo_barras;
        document.getElementById('asignar_producto_info').innerHTML = 
            `✓ <strong>${productoData.codigo_barras}</strong> - ${productoData.descripcion}`;
        document.getElementById('asignar_producto_info').classList.remove('hidden');
        
        abrirModal('modalAsignarProducto');
        // Focus directo en el campo de ubicación
        setTimeout(() => {
            document.getElementById('asignar_warehouse_codigo').focus();
        }, 200);
        return;
    }
    
    // Si solo se pasa ID, buscar el producto
    if (productoId) {
        fetch(`/inventarios/buscar?id=${productoId}`)
            .then(response => response.json())
            .then(data => {
                if (data.estado && data.data) {
                    const producto = data.data;
                    document.getElementById('asignar_inventario_id').value = producto.id;
                    document.getElementById('asignar_producto_codigo').value = producto.codigo_barras;
                    document.getElementById('asignar_producto_info').innerHTML = 
                        `✓ <strong>${producto.codigo_barras}</strong> - ${producto.descripcion}`;
                    document.getElementById('asignar_producto_info').classList.remove('hidden');
                    
                    abrirModal('modalAsignarProducto');
                    // Focus en el campo de destino
                    setTimeout(() => {
                        document.getElementById('asignar_warehouse_codigo').focus();
                    }, 200);
                } else {
                    mostrarNotificacion('Producto no encontrado', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarNotificacion('Error al buscar producto', 'error');
            });
        return;
    }
    
    // Si no se pasa nada, abrir modal vacío
    abrirModal('modalAsignarProducto');
}

function abrirModalTraslado() {
    abrirModal('modalTrasladoProducto');
}

function abrirModal(modalId) {
    document.getElementById(modalId).classList.remove('hidden');
    // Autofocus en el primer campo
    const modal = document.getElementById(modalId);
    const primerInput = modal.querySelector('input:not([type="hidden"])');
    if (primerInput) {
        setTimeout(() => primerInput.focus(), 100);
    }
}

function cerrarModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
    // Limpiar formularios
    if (modalId === 'modalAsignarProducto') {
        limpiarFormAsignar();
    } else if (modalId === 'modalTrasladoProducto') {
        limpiarFormTraslado();
    }
}

function limpiarFormAsignar() {
    document.getElementById('formAsignar').reset();
    document.getElementById('asignar_inventario_id').value = '';
    document.getElementById('asignar_warehouse_id').value = '';
    document.getElementById('asignar_producto_info').textContent = '';
    document.getElementById('asignar_producto_info').classList.add('hidden');
    document.getElementById('asignar_warehouse_info').textContent = '';
    document.getElementById('asignar_warehouse_info').classList.add('hidden');
}

function limpiarFormTraslado() {
    document.getElementById('formTraslado').reset();
    document.getElementById('traslado_inventario_id').value = '';
    document.getElementById('traslado_warehouse_origen_id').value = '';
    document.getElementById('traslado_warehouse_destino_id').value = '';
    document.getElementById('traslado_producto_info').textContent = '';
    document.getElementById('traslado_producto_info').classList.add('hidden');
    document.getElementById('traslado_ubicaciones_producto').classList.add('hidden');
    document.getElementById('traslado_lista_ubicaciones').innerHTML = '';
    document.getElementById('traslado_origen_info').textContent = '';
    document.getElementById('traslado_origen_info').classList.add('hidden');
    document.getElementById('traslado_destino_info').textContent = '';
    document.getElementById('traslado_destino_info').classList.add('hidden');
}

// MODAL ASIGNAR: Buscar producto por código de barras
function buscarProductoPorCodigo() {
    const codigo = document.getElementById('asignar_producto_codigo').value.trim();
    if (!codigo) return;
    
    fetch(`/inventarios/buscar?codigo=${encodeURIComponent(codigo)}`)
        .then(response => response.json())
        .then(data => {
            if (data.estado && data.data) {
                const producto = data.data;
                document.getElementById('asignar_inventario_id').value = producto.id;
                document.getElementById('asignar_producto_info').textContent = 
                    `✓ ${producto.codigo_barras} - ${producto.descripcion}`;
                document.getElementById('asignar_producto_info').classList.remove('hidden');
                // Auto-focus al siguiente campo
                document.getElementById('asignar_warehouse_codigo').focus();
            } else {
                mostrarNotificacion('Producto no encontrado', 'warning');
                document.getElementById('asignar_producto_codigo').value = '';
                document.getElementById('asignar_producto_codigo').focus();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarNotificacion('Error al buscar producto', 'error');
        });
}

// MODAL ASIGNAR: Buscar ubicación por código
function buscarWarehousePorCodigo() {
    const codigo = document.getElementById('asignar_warehouse_codigo').value.trim();
    if (!codigo) return;
    
    fetch(`/warehouses/buscar?buscar=${encodeURIComponent(codigo)}`)
        .then(response => response.json())
        .then(data => {
            if (data.ubicaciones && data.ubicaciones.length > 0) {
                const warehouse = data.ubicaciones[0]; // Tomar el primer resultado
                document.getElementById('asignar_warehouse_id').value = warehouse.id;
                document.getElementById('asignar_warehouse_info').textContent = 
                    `✓ ${warehouse.codigo} - ${warehouse.nombre || 'Sin nombre'}`;
                document.getElementById('asignar_warehouse_info').classList.remove('hidden');
                // Auto-focus al campo cantidad
                document.getElementById('asignar_cantidad').focus();
            } else {
                mostrarNotificacion('Ubicación no encontrada', 'warning');
                document.getElementById('asignar_warehouse_codigo').value = '';
                document.getElementById('asignar_warehouse_codigo').focus();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarNotificacion('Error al buscar ubicación', 'error');
        });
}

// MODAL ASIGNAR: Guardar asignación
function guardarAsignacion(event) {
    event.preventDefault();
    
    const inventarioId = document.getElementById('asignar_inventario_id').value;
    const warehouseId = document.getElementById('asignar_warehouse_id').value;
    const cantidad = document.getElementById('asignar_cantidad').value;
    
    if (!inventarioId || !warehouseId || !cantidad) {
        mostrarNotificacion('Por favor complete todos los campos', 'warning');
        return;
    }
    
    const btnGuardar = event.target.querySelector('button[type="submit"]');
    btnGuardar.disabled = true;
    btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Guardando...';
    
    fetch('/warehouse-inventory/asignar', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            inventario_id: inventarioId,
            warehouse_id: warehouseId,
            cantidad: parseFloat(cantidad),
            fecha_entrada: new Date().toISOString().split('T')[0],
            lote: document.getElementById('asignar_lote').value || null,
            observaciones: document.getElementById('asignar_observaciones').value || null
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.estado) {
            mostrarNotificacion(data.msj, 'success');
            cerrarModal('modalAsignarProducto');
            location.reload(); // Recargar para ver cambios
        } else {
            mostrarNotificacion(data.msj, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarNotificacion('Error al guardar la asignación', 'error');
    })
    .finally(() => {
        btnGuardar.disabled = false;
        btnGuardar.innerHTML = '<i class="fas fa-save mr-2"></i>Guardar Asignación';
    });
}

// MODAL TRASLADO: Buscar producto por código
function buscarProductoTraslado() {
    const codigo = document.getElementById('traslado_producto_codigo').value.trim();
    if (!codigo) return;
    
    fetch(`/inventarios/buscar?codigo=${encodeURIComponent(codigo)}`)
        .then(response => response.json())
        .then(data => {
            if (data.estado && data.data) {
                const producto = data.data;
                document.getElementById('traslado_inventario_id').value = producto.id;
                document.getElementById('traslado_producto_info').textContent = 
                    `✓ ${producto.codigo_barras} - ${producto.descripcion}`;
                document.getElementById('traslado_producto_info').classList.remove('hidden');
                
                // Consultar todas las ubicaciones del producto
                consultarUbicacionesProducto(producto.id);
                
                // Auto-focus al campo origen
                document.getElementById('traslado_origen_codigo').focus();
            } else {
                mostrarNotificacion('Producto no encontrado', 'warning');
                document.getElementById('traslado_producto_codigo').value = '';
                document.getElementById('traslado_producto_codigo').focus();
                // Ocultar ubicaciones si no se encuentra el producto
                document.getElementById('traslado_ubicaciones_producto').classList.add('hidden');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarNotificacion('Error al buscar producto', 'error');
            document.getElementById('traslado_ubicaciones_producto').classList.add('hidden');
        });
}

// MODAL TRASLADO: Consultar ubicaciones del producto
function consultarUbicacionesProducto(inventarioId) {
    fetch(`/warehouse-inventory/producto/${inventarioId}/ubicaciones`)
        .then(response => response.json())
        .then(data => {
            const contenedorUbicaciones = document.getElementById('traslado_ubicaciones_producto');
            const listaUbicaciones = document.getElementById('traslado_lista_ubicaciones');
            const totalUbicaciones = document.getElementById('traslado_total_ubicaciones');
            
            if (data.estado && data.ubicaciones && data.ubicaciones.length > 0) {
                // Mostrar contenedor
                contenedorUbicaciones.classList.remove('hidden');
                
                // Mostrar total
                totalUbicaciones.textContent = `${data.total_ubicaciones} ubicación${data.total_ubicaciones > 1 ? 'es' : ''} - Total: ${parseFloat(data.total).toFixed(2)} unidades`;
                
                // Mostrar lista de ubicaciones
                listaUbicaciones.innerHTML = data.ubicaciones.map(ubicacion => `
                    <div class="flex items-center justify-between p-2 bg-white border border-gray-200 rounded hover:bg-blue-50 transition">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="font-mono font-semibold text-sm text-blue-600">${ubicacion.codigo}</span>
                                <span class="text-xs text-gray-600">${ubicacion.nombre || 'Sin nombre'}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs font-semibold">
                                ${parseFloat(ubicacion.cantidad).toFixed(2)} unidades
                            </span>
                        </div>
                    </div>
                `).join('');
            } else {
                // Si no hay ubicaciones, mostrar mensaje
                contenedorUbicaciones.classList.remove('hidden');
                totalUbicaciones.textContent = 'Sin ubicaciones';
                listaUbicaciones.innerHTML = `
                    <div class="text-center py-4 text-gray-400 text-sm">
                        <i class="fas fa-inbox text-2xl mb-2"></i>
                        <p>Este producto no tiene ubicaciones asignadas</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error al consultar ubicaciones:', error);
            // Ocultar contenedor si hay error
            document.getElementById('traslado_ubicaciones_producto').classList.add('hidden');
        });
}

// MODAL TRASLADO: Buscar ubicación origen
function buscarOrigenTraslado() {
    const codigo = document.getElementById('traslado_origen_codigo').value.trim();
    if (!codigo) return;
    
    const inventarioId = document.getElementById('traslado_inventario_id').value;
    if (!inventarioId) {
        mostrarNotificacion('Primero debe escanear un producto', 'warning');
        document.getElementById('traslado_origen_codigo').value = '';
        document.getElementById('traslado_producto_codigo').focus();
        return;
    }
    
    fetch(`/warehouses/buscar?buscar=${encodeURIComponent(codigo)}`)
        .then(response => response.json())
        .then(data => {
            if (data.ubicaciones && data.ubicaciones.length > 0) {
                const warehouse = data.ubicaciones[0]; // Tomar el primer resultado
                document.getElementById('traslado_warehouse_origen_id').value = warehouse.id;
                
                // Consultar la cantidad disponible en esa ubicación para ese producto
                fetch(`/warehouse-inventory/stock-ubicacion?warehouse_id=${warehouse.id}&inventario_id=${inventarioId}`)
                    .then(response => response.json())
                    .then(stockData => {
                        const cantidadDisponible = stockData.cantidad || 0;
                        
                        document.getElementById('traslado_origen_info').innerHTML = 
                            `✓ Origen: <strong>${warehouse.codigo}</strong> - ${warehouse.nombre || 'Sin nombre'} 
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold ${cantidadDisponible > 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                                Stock: ${parseFloat(cantidadDisponible).toFixed(2)} unidades
                            </span>`;
                        document.getElementById('traslado_origen_info').classList.remove('hidden');
                        
                        // Establecer el máximo en el campo cantidad
                        const cantidadInput = document.getElementById('traslado_cantidad');
                        cantidadInput.max = cantidadDisponible;
                        
                        if (cantidadDisponible > 0) {
                            // Auto-focus al campo destino
                            document.getElementById('traslado_destino_codigo').focus();
                        } else {
                            mostrarNotificacion('No hay stock disponible en esta ubicación para este producto', 'warning');
                            document.getElementById('traslado_origen_codigo').value = '';
                            document.getElementById('traslado_origen_codigo').focus();
                        }
                    })
                    .catch(error => {
                        console.error('Error al consultar stock:', error);
                        // Si falla la consulta de stock, continuar sin esa información
                        document.getElementById('traslado_origen_info').innerHTML = 
                            `✓ Origen: <strong>${warehouse.codigo}</strong> - ${warehouse.nombre || 'Sin nombre'}`;
                        document.getElementById('traslado_origen_info').classList.remove('hidden');
                        document.getElementById('traslado_destino_codigo').focus();
                    });
            } else {
                mostrarNotificacion('Ubicación de origen no encontrada', 'warning');
                document.getElementById('traslado_origen_codigo').value = '';
                document.getElementById('traslado_origen_codigo').focus();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarNotificacion('Error al buscar ubicación origen', 'error');
        });
}

// MODAL TRASLADO: Buscar ubicación destino
function buscarDestinoTraslado() {
    const codigo = document.getElementById('traslado_destino_codigo').value.trim();
    if (!codigo) return;
    
    fetch(`/warehouses/buscar?buscar=${encodeURIComponent(codigo)}`)
        .then(response => response.json())
        .then(data => {
            if (data.ubicaciones && data.ubicaciones.length > 0) {
                const warehouse = data.ubicaciones[0]; // Tomar el primer resultado
                document.getElementById('traslado_warehouse_destino_id').value = warehouse.id;
                document.getElementById('traslado_destino_info').textContent = 
                    `✓ Destino: ${warehouse.codigo} - ${warehouse.nombre || 'Sin nombre'}`;
                document.getElementById('traslado_destino_info').classList.remove('hidden');
                // Auto-focus al campo cantidad
                document.getElementById('traslado_cantidad').focus();
            } else {
                mostrarNotificacion('Ubicación de destino no encontrada', 'warning');
                document.getElementById('traslado_destino_codigo').value = '';
                document.getElementById('traslado_destino_codigo').focus();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarNotificacion('Error al buscar ubicación destino', 'error');
        });
}

// MODAL TRASLADO: Guardar traslado
function guardarTraslado(event) {
    event.preventDefault();
    
    const inventarioId = document.getElementById('traslado_inventario_id').value;
    const origenId = document.getElementById('traslado_warehouse_origen_id').value;
    const destinoId = document.getElementById('traslado_warehouse_destino_id').value;
    const cantidad = document.getElementById('traslado_cantidad').value;
    
    if (!inventarioId || !origenId || !destinoId || !cantidad) {
        mostrarNotificacion('Por favor complete todos los campos', 'warning');
        return;
    }
    
    if (origenId === destinoId) {
        mostrarNotificacion('La ubicación de origen y destino deben ser diferentes', 'warning');
        return;
    }
    
    const btnGuardar = event.target.querySelector('button[type="submit"]');
    btnGuardar.disabled = true;
    btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Procesando...';
    
    fetch('/warehouse-inventory/transferir', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            inventario_id: inventarioId,
            warehouse_origen_id: origenId,
            warehouse_destino_id: destinoId,
            cantidad: parseFloat(cantidad),
            observaciones: document.getElementById('traslado_observaciones').value || null
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.estado) {
            mostrarNotificacion(data.msj, 'success');
            cerrarModal('modalTrasladoProducto');
            location.reload(); // Recargar para ver cambios
        } else {
            mostrarNotificacion(data.msj, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarNotificacion('Error al realizar el traslado', 'error');
    })
    .finally(() => {
        btnGuardar.disabled = false;
        btnGuardar.innerHTML = '<i class="fas fa-exchange-alt mr-2"></i>Ejecutar Traslado';
    });
}
</script>

<!-- Modal Asignar Producto a Ubicación -->
<div id="modalAsignarProducto" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-10 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4 pb-3 border-b">
            <h3 class="text-xl font-semibold text-gray-900">
                <i class="fas fa-box text-green-500 mr-2"></i>
                Asignar Producto a Ubicación
            </h3>
            <button onclick="cerrarModal('modalAsignarProducto')" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times fa-lg"></i>
            </button>
        </div>
        
        <form id="formAsignar" onsubmit="guardarAsignacion(event)" class="space-y-4">
            <input type="hidden" id="asignar_inventario_id">
            <input type="hidden" id="asignar_warehouse_id">
            
            <!-- Instrucciones -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                <p class="text-sm text-blue-800">
                    <i class="fas fa-info-circle mr-2"></i>
                    <strong>Instrucciones:</strong> Escanee el código de barras del producto, luego de la ubicación, ingrese la cantidad y presione Enter.
                </p>
            </div>
            
            <!-- Paso 1: Producto -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-green-500 text-white text-xs mr-2">1</span>
                    Escanear Producto
                </label>
                <input type="text" 
                       id="asignar_producto_codigo"
                       class="w-full px-4 py-3 text-lg border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                       placeholder="Escanee código de barras del producto..."
                       onchange="buscarProductoPorCodigo()"
                       autocomplete="off">
                <div id="asignar_producto_info" class="hidden mt-2 p-2 bg-green-50 border border-green-200 rounded text-sm text-green-800"></div>
            </div>
            
            <!-- Paso 2: Ubicación Destino -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-green-500 text-white text-xs mr-2">2</span>
                    Escanear Ubicación Destino
                </label>
                <input type="text" 
                       id="asignar_warehouse_codigo"
                       class="w-full px-4 py-3 text-lg border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                       placeholder="Escanee código de la ubicación..."
                       onchange="buscarWarehousePorCodigo()"
                       autocomplete="off">
                <div id="asignar_warehouse_info" class="hidden mt-2 p-2 bg-green-50 border border-green-200 rounded text-sm text-green-800"></div>
            </div>
            
            <!-- Paso 3: Cantidad -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-green-500 text-white text-xs mr-2">3</span>
                        Cantidad *
                    </label>
                    <input type="number" 
                           id="asignar_cantidad"
                           step="0.01"
                           min="0.01"
                           class="w-full px-4 py-3 text-lg border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                           placeholder="0.00"
                           required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Lote (Opcional)
                    </label>
                    <input type="text" 
                           id="asignar_lote"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                           placeholder="Número de lote">
                </div>
            </div>
            
            <!-- Observaciones -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Observaciones (Opcional)
                </label>
                <textarea id="asignar_observaciones"
                          rows="2"
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                          placeholder="Notas adicionales..."></textarea>
            </div>
            
            <!-- Botones -->
            <div class="flex justify-end space-x-3 pt-4">
                <button type="button" 
                        onclick="cerrarModal('modalAsignarProducto')"
                        class="px-5 py-2.5 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition">
                    <i class="fas fa-times mr-2"></i>Cancelar
                </button>
                <button type="submit"
                        class="px-5 py-2.5 text-white bg-green-600 hover:bg-green-700 rounded-lg transition">
                    <i class="fas fa-save mr-2"></i>Guardar Asignación
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Traslado entre Ubicaciones -->
<div id="modalTrasladoProducto" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-10 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4 pb-3 border-b">
            <h3 class="text-xl font-semibold text-gray-900">
                <i class="fas fa-exchange-alt text-blue-500 mr-2"></i>
                Traslado entre Ubicaciones
            </h3>
            <button onclick="cerrarModal('modalTrasladoProducto')" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times fa-lg"></i>
            </button>
        </div>
        
        <form id="formTraslado" onsubmit="guardarTraslado(event)" class="space-y-4">
            <input type="hidden" id="traslado_inventario_id">
            <input type="hidden" id="traslado_warehouse_origen_id">
            <input type="hidden" id="traslado_warehouse_destino_id">
            
            <!-- Instrucciones -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                <p class="text-sm text-blue-800">
                    <i class="fas fa-info-circle mr-2"></i>
                    <strong>Instrucciones:</strong> Escanee el producto, luego ubicación origen, destino, cantidad y Enter para confirmar.
                </p>
            </div>
            
            <!-- Paso 1: Producto -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-blue-500 text-white text-xs mr-2">1</span>
                    Escanear Producto
                </label>
                <input type="text" 
                       id="traslado_producto_codigo"
                       class="w-full px-4 py-3 text-lg border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="Escanee código de barras del producto..."
                       onchange="buscarProductoTraslado()"
                       autocomplete="off">
                <div id="traslado_producto_info" class="hidden mt-2 p-2 bg-blue-50 border border-blue-200 rounded text-sm text-blue-800"></div>
                <!-- Contenedor para mostrar todas las ubicaciones del producto -->
                <div id="traslado_ubicaciones_producto" class="hidden mt-3 p-3 bg-gray-50 border border-gray-200 rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="text-sm font-semibold text-gray-700">
                            <i class="fas fa-map-marker-alt text-blue-500 mr-1"></i>
                            Ubicaciones del Producto:
                        </h4>
                        <span id="traslado_total_ubicaciones" class="text-xs text-gray-500"></span>
                    </div>
                    <div id="traslado_lista_ubicaciones" class="space-y-2 max-h-48 overflow-y-auto">
                        <!-- Las ubicaciones se mostrarán aquí -->
                    </div>
                </div>
            </div>
            
            <!-- Paso 2: Ubicación Origen -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-blue-500 text-white text-xs mr-2">2</span>
                    Escanear Ubicación Origen
                </label>
                <input type="text" 
                       id="traslado_origen_codigo"
                       class="w-full px-4 py-3 text-lg border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="Escanee código de ubicación origen..."
                       onchange="buscarOrigenTraslado()"
                       autocomplete="off">
                <div id="traslado_origen_info" class="hidden mt-2 p-2 bg-orange-50 border border-orange-200 rounded text-sm text-orange-800"></div>
            </div>
            
            <!-- Paso 3: Ubicación Destino -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-blue-500 text-white text-xs mr-2">3</span>
                    Escanear Ubicación Destino
                </label>
                <input type="text" 
                       id="traslado_destino_codigo"
                       class="w-full px-4 py-3 text-lg border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="Escanee código de ubicación destino..."
                       onchange="buscarDestinoTraslado()"
                       autocomplete="off">
                <div id="traslado_destino_info" class="hidden mt-2 p-2 bg-green-50 border border-green-200 rounded text-sm text-green-800"></div>
            </div>
            
            <!-- Paso 4: Cantidad -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-blue-500 text-white text-xs mr-2">4</span>
                    Cantidad a Trasladar *
                </label>
                <input type="number" 
                       id="traslado_cantidad"
                       step="0.01"
                       min="0.01"
                       class="w-full px-4 py-3 text-lg border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="0.00"
                       required>
            </div>
            
            <!-- Observaciones -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Observaciones (Opcional)
                </label>
                <textarea id="traslado_observaciones"
                          rows="2"
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                          placeholder="Motivo del traslado..."></textarea>
            </div>
            
            <!-- Botones -->
            <div class="flex justify-end space-x-3 pt-4">
                <button type="button" 
                        onclick="cerrarModal('modalTrasladoProducto')"
                        class="px-5 py-2.5 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition">
                    <i class="fas fa-times mr-2"></i>Cancelar
                </button>
                <button type="submit"
                        class="px-5 py-2.5 text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition">
                    <i class="fas fa-exchange-alt mr-2"></i>Ejecutar Traslado
                </button>
            </div>
        </form>
    </div>
</div>

 