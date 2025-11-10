<!-- Filtros de Búsqueda -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-4 p-4">
    <form method="GET" action="{{ route('warehouse-inventory.index') }}" class="space-y-4">
        <input type="hidden" name="vista" value="productos_generales">
        
        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
            <!-- Campo de búsqueda -->
            <div class="md:col-span-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
                <input type="text" 
                       name="buscar" 
                       value="{{ request('buscar') }}" 
                       placeholder="Buscar producto..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
            </div>
            
            <!-- Tipo de búsqueda -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Buscar por</label>
                <select name="campo_busqueda" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    <option value="todos" {{ request('campo_busqueda') === 'todos' ? 'selected' : '' }}>Todos los campos</option>
                    <option value="codigo_barras" {{ request('campo_busqueda') === 'codigo_barras' ? 'selected' : '' }}>Código Barras</option>
                    <option value="codigo_proveedor" {{ request('campo_busqueda') === 'codigo_proveedor' ? 'selected' : '' }}>Código Proveedor</option>
                    <option value="descripcion" {{ request('campo_busqueda') === 'descripcion' ? 'selected' : '' }}>Descripción</option>
                </select>
            </div>
            
            <!-- Filtro por Warehouse -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Estado Ubicación</label>
                <select name="filtro_warehouse" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos</option>
                    <option value="con_warehouse" {{ request('filtro_warehouse') === 'con_warehouse' ? 'selected' : '' }}>Con ubicación</option>
                    <option value="sin_warehouse" {{ request('filtro_warehouse') === 'sin_warehouse' ? 'selected' : '' }}>Sin ubicación</option>
                </select>
            </div>
            
            <!-- Categoría -->
            <div class="md:col-span-2">
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
            <div class="md:col-span-2">
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
            
            <!-- Botón de búsqueda -->
            <div class="md:col-span-1 flex items-end">
                <button type="submit" class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md transition">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Tabla de Productos -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Código</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descripción</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Código Proveedor</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Stock General</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">En Ubicaciones</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Ubicaciones</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($productos ?? [] as $producto)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-gray-900">{{ $producto->codigo_barras }}</div>
                            @if($producto->codigo_proveedor)
                                <div class="text-xs text-gray-500">Prov: {{ $producto->codigo_proveedor }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-gray-900">{{ $producto->descripcion }}</div>
                            @if($producto->proveedor)
                                <div class="text-xs text-gray-500">{{ $producto->proveedor->razonsocial }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="text-sm text-gray-900">{{ $producto->codigo_proveedor ?? '-' }}</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium {{ $producto->existencia > 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ number_format($producto->existencia, 2) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($producto->tiene_warehouse ?? false)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                    {{ number_format($producto->total_en_warehouses ?? 0, 2) }}
                                </span>
                            @else
                                <span class="text-sm text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
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
                        <td class="px-4 py-3 text-center">
                            @if($producto->tiene_warehouse ?? false)
                                <button type="button" 
                                        onclick="verUbicaciones({{ $producto->id }})"
                                        class="inline-flex items-center px-2 py-1 text-xs font-medium text-blue-700 bg-blue-50 rounded hover:bg-blue-100 transition">
                                    <i class="fas fa-map-marker-alt mr-1"></i>
                                    {{ $producto->numero_ubicaciones ?? 0 }} ubicación(es)
                                </button>
                            @else
                                <span class="text-xs text-gray-400">Sin asignar</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center space-x-2">
                                <button type="button" 
                                        onclick="consultarUbicaciones({{ $producto->id }})"
                                        class="p-2 text-blue-600 hover:bg-blue-50 rounded transition"
                                        title="Ver detalles">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button type="button" 
                                        onclick="asignarProducto({{ $producto->id }})"
                                        class="p-2 text-green-600 hover:bg-green-50 rounded transition"
                                        title="Asignar a ubicación">
                                    <i class="fas fa-plus-circle"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center">
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

<!-- Modal para ver ubicaciones -->
<div id="modalUbicaciones" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Ubicaciones del Producto</h3>
            <button onclick="cerrarModal('modalUbicaciones')" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times fa-lg"></i>
            </button>
        </div>
        <div id="contenidoUbicaciones" class="mt-4">
            <!-- Se cargará dinámicamente -->
        </div>
    </div>
</div>

<script>
function verUbicaciones(productoId) {
    fetch(`/warehouse-inventory/producto/${productoId}`)
        .then(response => response.json())
        .then(data => {
            if (data.estado) {
                const ubicaciones = data.data;
                let html = '<div class="space-y-2">';
                
                if (ubicaciones.length > 0) {
                    ubicaciones.forEach(ubi => {
                        html += `
                            <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                                <div>
                                    <span class="font-semibold text-blue-600">${ubi.warehouse.codigo}</span>
                                    ${ubi.lote ? `<span class="text-sm text-gray-500 ml-2">Lote: ${ubi.lote}</span>` : ''}
                                </div>
                                <span class="font-medium">${parseFloat(ubi.cantidad).toFixed(2)} unidades</span>
                            </div>
                        `;
                    });
                } else {
                    html += '<p class="text-gray-500 text-center py-4">No hay ubicaciones asignadas</p>';
                }
                
                html += '</div>';
                document.getElementById('contenidoUbicaciones').innerHTML = html;
                document.getElementById('modalUbicaciones').classList.remove('hidden');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al cargar ubicaciones');
        });
}

function consultarUbicaciones(productoId) {
    window.location.href = `/warehouse-inventory/producto/${productoId}`;
}

function asignarProducto(productoId) {
    // Redirigir a la vista de asignaciones con el producto seleccionado
    window.location.href = `/warehouse-inventory?inventario_id=${productoId}`;
}

function cerrarModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}
</script>

