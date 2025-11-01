@extends('layouts.app')

@section('content')
@include('warehouse-inventory.partials.nav')

<div class="container-fluid px-2 sm:px-4">
    <!-- Header -->
    <div class="mb-3 sm:mb-4">
        <h1 class="text-xl sm:text-2xl font-bold text-gray-800 flex items-center">
            <i class="fas fa-barcode text-blue-500 mr-2"></i>
            <span>Buscar por Código de Barras</span>
        </h1>
        <p class="text-sm text-gray-600 mt-1">Escanea el código de barras del ticket para ver la información</p>
    </div>

    <!-- Formulario de Búsqueda -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-4">
        <div class="p-4 sm:p-6">
            <form method="GET" action="{{ route('warehouse-inventory.buscar-codigo') }}" class="max-w-2xl mx-auto">
                <div class="flex flex-col sm:flex-row gap-3">
                    <div class="flex-1">
                        <label for="codigo" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-search text-blue-500 mr-1"></i>
                            Código de Barras
                        </label>
                        <input type="text" 
                               id="codigo" 
                               name="codigo" 
                               value="{{ $codigo ?? '' }}"
                               placeholder="WH-000123 o escanea el código..."
                               autofocus
                               autocomplete="off"
                               class="block w-full px-4 py-3 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" 
                                class="w-full sm:w-auto px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg shadow-sm transition">
                            <i class="fas fa-search mr-2"></i>
                            Buscar
                        </button>
                    </div>
                </div>
                <p class="mt-2 text-xs text-gray-500">
                    <i class="fas fa-info-circle"></i> Escanea el código de barras del ticket o escribe el código manualmente (ejemplo: WH-000123)
                </p>
            </form>
        </div>
    </div>

    <!-- Error -->
    @if(isset($error))
    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
        <div class="flex items-start">
            <i class="fas fa-exclamation-triangle text-red-500 text-xl mr-3 mt-0.5"></i>
            <div class="flex-1">
                <h3 class="text-sm font-semibold text-red-800 mb-1">Error de Búsqueda</h3>
                <p class="text-sm text-red-700">{{ $error }}</p>
            </div>
        </div>
    </div>
    @endif

    <!-- Resultado -->
    @if(isset($inventario))
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <!-- Header del Resultado -->
        <div class="bg-gradient-to-r from-green-500 to-green-600 px-4 sm:px-6 py-4 rounded-t-lg">
            <div class="flex items-center justify-between">
                <div class="flex items-center text-white">
                    <i class="fas fa-check-circle text-2xl mr-3"></i>
                    <div>
                        <h2 class="text-lg font-bold">Producto Encontrado</h2>
                        <p class="text-sm opacity-90">Código: {{ $codigo }}</p>
                    </div>
                </div>
                <a href="{{ route('warehouse-inventory.ticket', $inventario->id) }}" 
                   target="_blank"
                   class="px-4 py-2 bg-white text-green-600 hover:bg-green-50 font-medium rounded-lg shadow-sm transition">
                    <i class="fas fa-print mr-2"></i>
                    <span class="hidden sm:inline">Reimprimir</span>
                </a>
            </div>
        </div>

        <div class="p-4 sm:p-6">
            <!-- Grid de Información -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Ubicación -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h3 class="text-sm font-semibold text-blue-800 mb-3 flex items-center">
                        <i class="fas fa-map-marker-alt mr-2"></i>
                        Ubicación en Almacén
                    </h3>
                    <div class="space-y-2">
                        <div class="flex justify-between py-2 border-b border-blue-100">
                            <span class="text-sm font-medium text-gray-600">Código:</span>
                            <span class="text-sm font-bold text-gray-900">{{ $inventario->warehouse->codigo }}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-blue-100">
                            <span class="text-sm font-medium text-gray-600">Nombre:</span>
                            <span class="text-sm text-gray-900">{{ $inventario->warehouse->nombre }}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-blue-100">
                            <span class="text-sm font-medium text-gray-600">Tipo:</span>
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                {{ $inventario->warehouse->tipo }}
                            </span>
                        </div>
                        @if($inventario->warehouse->ubicacion)
                        <div class="flex justify-between py-2">
                            <span class="text-sm font-medium text-gray-600">Ubicación:</span>
                            <span class="text-sm text-gray-900">{{ $inventario->warehouse->ubicacion }}</span>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Producto -->
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <h3 class="text-sm font-semibold text-green-800 mb-3 flex items-center">
                        <i class="fas fa-box mr-2"></i>
                        Información del Producto
                    </h3>
                    <div class="space-y-2">
                        <div class="py-2 border-b border-green-100">
                            <span class="text-xs font-medium text-gray-600 block mb-1">Descripción:</span>
                            <span class="text-sm font-semibold text-gray-900">{{ $inventario->inventario->descripcion }}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-green-100">
                            <span class="text-sm font-medium text-gray-600">Código de Barras:</span>
                            <span class="text-sm font-mono text-gray-900">{{ $inventario->inventario->codigo_barras }}</span>
                        </div>
                        @if($inventario->inventario->proveedor)
                        <div class="flex justify-between py-2 border-b border-green-100">
                            <span class="text-sm font-medium text-gray-600">Proveedor:</span>
                            <span class="text-sm text-gray-900">{{ $inventario->inventario->proveedor->razonsocial }}</span>
                        </div>
                        @endif
                        <div class="flex justify-between py-2">
                            <span class="text-sm font-medium text-gray-600">Precio:</span>
                            <span class="text-sm font-bold text-green-600">{{ number_format($inventario->inventario->precio_venta, 2) }} Bs.</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Información de Inventario -->
            <div class="mt-6 bg-gray-50 border border-gray-200 rounded-lg p-4">
                <h3 class="text-sm font-semibold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-warehouse mr-2"></i>
                    Detalles de Inventario
                </h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                    <!-- Cantidad -->
                    <div class="text-center p-3 bg-white rounded-lg border border-gray-200">
                        <div class="text-2xl font-bold text-blue-600 mb-1">{{ number_format($inventario->cantidad, 2) }}</div>
                        <div class="text-xs text-gray-600">Cantidad</div>
                    </div>

                    <!-- Estado -->
                    <div class="text-center p-3 bg-white rounded-lg border border-gray-200">
                        <div class="mb-1">
                            @if($inventario->estado == 'disponible')
                                <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Disponible</span>
                            @elseif($inventario->estado == 'reservado')
                                <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Reservado</span>
                            @else
                                <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Cuarentena</span>
                            @endif
                        </div>
                        <div class="text-xs text-gray-600">Estado</div>
                    </div>

                    <!-- Lote -->
                    <div class="text-center p-3 bg-white rounded-lg border border-gray-200">
                        <div class="text-sm font-semibold text-gray-900 mb-1">{{ $inventario->lote ?? 'N/A' }}</div>
                        <div class="text-xs text-gray-600">Lote</div>
                    </div>

                    <!-- Fecha Entrada -->
                    <div class="text-center p-3 bg-white rounded-lg border border-gray-200">
                        <div class="text-sm font-semibold text-gray-900 mb-1">
                            {{ $inventario->fecha_entrada ? \Carbon\Carbon::parse($inventario->fecha_entrada)->format('d/m/Y') : 'N/A' }}
                        </div>
                        <div class="text-xs text-gray-600">F. Entrada</div>
                    </div>

                    <!-- Fecha Vencimiento -->
                    <div class="text-center p-3 bg-white rounded-lg border border-gray-200">
                        <div class="text-sm font-semibold text-gray-900 mb-1">
                            @if($inventario->fecha_vencimiento)
                                {{ \Carbon\Carbon::parse($inventario->fecha_vencimiento)->format('d/m/Y') }}
                                @php
                                    $dias = \Carbon\Carbon::parse($inventario->fecha_vencimiento)->diffInDays(now(), false);
                                @endphp
                                @if($dias > 0)
                                    <span class="block text-xs text-red-600 mt-1">Vencido</span>
                                @elseif(abs($dias) < 30)
                                    <span class="block text-xs text-yellow-600 mt-1">Próximo</span>
                                @endif
                            @else
                                N/A
                            @endif
                        </div>
                        <div class="text-xs text-gray-600">F. Vencimiento</div>
                    </div>
                </div>
            </div>

            <!-- Observaciones -->
            @if($inventario->observaciones)
            <div class="mt-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-yellow-800 mb-2 flex items-center">
                    <i class="fas fa-sticky-note mr-2"></i>
                    Observaciones
                </h4>
                <p class="text-sm text-gray-700">{{ $inventario->observaciones }}</p>
            </div>
            @endif

            <!-- Acciones -->
            <div class="mt-6 flex flex-wrap gap-3 justify-center">
                <a href="{{ route('warehouse-inventory.por-ubicacion', ['warehouse_id' => $inventario->warehouse_id]) }}" 
                   class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg shadow-sm transition">
                    <i class="fas fa-map-marker-alt mr-2"></i>
                    Ver Inventario de esta Ubicación
                </a>
                <a href="{{ route('warehouse-inventory.index') }}" 
                   class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white font-medium rounded-lg shadow-sm transition">
                    <i class="fas fa-boxes mr-2"></i>
                    Ver Todo el Inventario
                </a>
            </div>
        </div>
    </div>
    @endif
</div>

<script>
// Auto-focus en el campo de búsqueda
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('codigo');
    if (input && !input.value) {
        input.focus();
    }
});

// Detectar enter en el campo
document.getElementById('codigo').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.target.form.submit();
    }
});
</script>
@endsection

