<!-- Filtros -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-4 p-4">
    <form method="GET" action="{{ route('warehouse-inventory.index') }}" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
            <!-- Buscar producto -->
            <div class="md:col-span-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Buscar Producto</label>
                <input type="text" 
                       name="buscar" 
                       value="{{ request('buscar') }}" 
                       placeholder="Buscar por código o descripción..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
            </div>
            
            <!-- Ubicación -->
            <div class="md:col-span-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Ubicación</label>
                <select name="warehouse_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todas las ubicaciones</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" {{ request('warehouse_id') == $warehouse->id ? 'selected' : '' }}>
                            {{ $warehouse->codigo }} - {{ $warehouse->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            
            <!-- Estado -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                <select name="estado" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos</option>
                    <option value="disponible" {{ request('estado') === 'disponible' ? 'selected' : '' }}>Disponible</option>
                    <option value="reservado" {{ request('estado') === 'reservado' ? 'selected' : '' }}>Reservado</option>
                    <option value="bloqueado" {{ request('estado') === 'bloqueado' ? 'selected' : '' }}>Bloqueado</option>
                </select>
            </div>
            
            <!-- Lote -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Lote</label>
                <input type="text" 
                       name="lote" 
                       value="{{ request('lote') }}" 
                       placeholder="Número de lote"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
            </div>
            
            <!-- Botón buscar -->
            <div class="md:col-span-1 flex items-end">
                <button type="submit" class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md transition">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Tabla de Asignaciones -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ubicación</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Producto</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Lote</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Cantidad</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha Entrada</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Vencimiento</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($inventarios ?? [] as $inventario)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center bg-blue-100 rounded-lg">
                                    <i class="fas fa-warehouse text-blue-600"></i>
                                </div>
                                <div class="ml-3">
                                    <div class="text-sm font-medium text-gray-900">{{ $inventario->warehouse->codigo }}</div>
                                    <div class="text-xs text-gray-500">{{ $inventario->warehouse->nombre }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-gray-900">{{ $inventario->inventario->descripcion }}</div>
                            <div class="text-xs text-gray-500">{{ $inventario->inventario->codigo_barras }}</div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($inventario->lote)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    {{ $inventario->lote }}
                                </span>
                            @else
                                <span class="text-gray-400 text-sm">-</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-green-100 text-green-800">
                                {{ number_format($inventario->cantidad, 2) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($inventario->estado === 'disponible')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    Disponible
                                </span>
                            @elseif($inventario->estado === 'reservado')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                    Reservado
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    Bloqueado
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center text-sm text-gray-900">
                            {{ $inventario->fecha_entrada ? \Carbon\Carbon::parse($inventario->fecha_entrada)->format('d/m/Y') : '-' }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($inventario->fecha_vencimiento)
                                @php
                                    $vencimiento = \Carbon\Carbon::parse($inventario->fecha_vencimiento);
                                    $diasParaVencer = $vencimiento->diffInDays(now(), false);
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                    {{ $diasParaVencer > 0 ? 'bg-red-100 text-red-800' : ($diasParaVencer > -30 ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800') }}">
                                    {{ $vencimiento->format('d/m/Y') }}
                                </span>
                            @else
                                <span class="text-gray-400 text-sm">-</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center space-x-2">
                                <a href="{{ route('warehouse-inventory.ticket', $inventario->id) }}" 
                                   target="_blank"
                                   class="p-2 text-blue-600 hover:bg-blue-50 rounded transition"
                                   title="Imprimir ticket">
                                    <i class="fas fa-print"></i>
                                </a>
                                <button type="button" 
                                        onclick="transferir({{ $inventario->id }})"
                                        class="p-2 text-yellow-600 hover:bg-yellow-50 rounded transition"
                                        title="Transferir">
                                    <i class="fas fa-exchange-alt"></i>
                                </button>
                                <button type="button" 
                                        onclick="retirar({{ $inventario->id }})"
                                        class="p-2 text-red-600 hover:bg-red-50 rounded transition"
                                        title="Retirar">
                                    <i class="fas fa-minus-circle"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center">
                            <div class="flex flex-col items-center justify-center text-gray-400">
                                <i class="fas fa-box-open fa-3x mb-3"></i>
                                <p class="text-lg font-medium">No hay productos asignados</p>
                                <p class="text-sm">Comienza asignando productos a ubicaciones</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    <!-- Paginación -->
    @if(isset($inventarios) && $inventarios->hasPages())
        <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
            {{ $inventarios->appends(request()->query())->links() }}
        </div>
    @endif
</div>

<script>
function abrirModalAsignar() {
    // Implementar modal de asignación
    alert('Funcionalidad de asignación - Por implementar');
}

function transferir(id) {
    if (confirm('¿Desea transferir este producto a otra ubicación?')) {
        // Implementar transferencia
        alert('Funcionalidad de transferencia - Por implementar');
    }
}

function retirar(id) {
    if (confirm('¿Está seguro de retirar este producto de la ubicación?')) {
        // Implementar retiro
        alert('Funcionalidad de retiro - Por implementar');
    }
}
</script>

