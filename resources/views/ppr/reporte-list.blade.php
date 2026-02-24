@extends('layouts.app')

@section('content')
@include('warehouse-inventory.partials.nav')

<div class="container-fluid px-2 sm:px-4 py-4">
    <h1 class="text-xl sm:text-2xl font-bold text-gray-800 mb-4">
        <i class="fas fa-clipboard-list text-blue-500 mr-2"></i>
        Reporte PPR (Pendiente por Retirar)
    </h1>

    <form method="get" action="{{ route('ppr.reporte') }}" class="bg-white rounded-xl border border-gray-200 p-4 mb-4 shadow-sm">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Fecha desde</label>
                <input type="date" name="fecha_desde" value="{{ $fecha_desde ?? '' }}"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Fecha hasta</label>
                <input type="date" name="fecha_hasta" value="{{ $fecha_hasta ?? '' }}"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nº pedido</label>
                <input type="number" name="id_pedido" value="{{ $id_pedido ?? '' }}" placeholder="Opcional"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" min="1">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Caja (vendedor)</label>
                <select name="id_caja" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    <option value="">Todas</option>
                    @foreach($vendedores ?? [] as $v)
                        <option value="{{ $v->id }}" {{ (isset($id_caja) && $id_caja == $v->id) ? 'selected' : '' }}>
                            {{ $v->nombre ?? $v->usuario ?? 'ID '.$v->id }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg text-sm">
                    <i class="fas fa-search mr-1"></i> Filtrar
                </button>
                <a href="{{ route('ppr.reporte') }}" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg text-sm inline-flex items-center">
                    Limpiar
                </a>
            </div>
        </div>
    </form>

    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-gray-600">
            Pedidos con entregas PPR: <strong>{{ count($porPedido ?? []) }}</strong>
        </p>
        @if(count($porPedido ?? []) > 0)
            <a href="{{ route('ppr.reporte.ver', request()->only(['fecha_desde','fecha_hasta','id_pedido','id_caja'])) }}"
               target="_blank"
               class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg text-sm">
                <i class="fas fa-file-alt mr-2"></i> Generar reporte (Blade)
            </a>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Pedido</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Fecha pedido</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Caja / Vendedor</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-700">Entregas</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($porPedido ?? [] as $p)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 font-mono font-semibold">#{{ $p['id_pedido'] }}</td>
                            <td class="px-4 py-2 text-gray-600">
                                {{ $p['fecha_pedido'] ? \Carbon\Carbon::parse($p['fecha_pedido'])->format('d/m/Y H:i') : '—' }}
                            </td>
                            <td class="px-4 py-2">{{ $p['vendedor_nombre'] ?? '—' }}</td>
                            <td class="px-4 py-2 text-right">{{ count($p['entregas'] ?? []) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-gray-500">No hay registros PPR con los filtros indicados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
