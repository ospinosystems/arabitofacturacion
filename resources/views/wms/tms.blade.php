@extends('layouts.app')

@section('content')
@include('warehouse-inventory.partials.nav')

<div class="container-fluid px-2 sm:px-4">

    <h1 class="text-xl sm:text-2xl font-bold text-gray-800 flex items-center mb-4">
        <i class="fas fa-truck text-blue-500 mr-2"></i>
        TMS &middot; Transporte y distribución
    </h1>

    @php $ind = $indicadores; @endphp

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-xs uppercase text-gray-500 font-semibold">Rutas ({{ $ind['dias'] }} días)</p>
            <p class="text-3xl font-bold mt-1 text-gray-800">{{ $ind['rutas']['total'] }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ $ind['rutas']['completadas'] }} completadas</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-xs uppercase text-gray-500 font-semibold">Efectividad de entrega</p>
            <p class="text-3xl font-bold mt-1 text-gray-800">
                {{ $ind['paradas']['efectividad_pct'] !== null ? $ind['paradas']['efectividad_pct'].'%' : '—' }}
            </p>
            <p class="text-xs text-gray-500 mt-1">
                {{ $ind['paradas']['entregadas'] }} entregadas / {{ $ind['paradas']['fallidas'] }} fallidas
            </p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-xs uppercase text-gray-500 font-semibold">Utilización media</p>
            <p class="text-xl font-bold mt-1 text-gray-800">
                {{ $ind['utilizacion']['peso_promedio_pct'] }}% peso
            </p>
            <p class="text-xl font-bold text-gray-800">
                {{ $ind['utilizacion']['volumen_promedio_pct'] }}% volumen
            </p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-xs uppercase text-gray-500 font-semibold">Distancia estimada</p>
            <p class="text-3xl font-bold mt-1 text-gray-800">{{ number_format($ind['distancia_km'], 0) }}</p>
            <p class="text-xs text-gray-500 mt-1">km &middot; costo {{ number_format($ind['costo'], 2) }}</p>
        </div>
    </div>

    <div class="bg-blue-50 border-l-4 border-blue-400 p-3 rounded mb-4 text-sm text-blue-900">
        <strong>Cómo se reparte la carga.</strong> Los envíos se cubican con el peso y volumen de cada
        producto y se asignan a vehículos con <em>First Fit Decreasing</em>: primero los envíos grandes,
        cada uno al primer vehículo donde quepa. Se controlan peso y volumen a la vez porque el límite
        real cambia según la mercancía — lo denso satura el peso, lo voluminoso el cubicaje.
        La columna <strong>limitante</strong> dice cuál de los dos se agotó.
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-4 py-3 border-b bg-gray-50">
                <h2 class="font-semibold text-gray-800">Flota</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-3 py-2 text-left">Placa</th>
                            <th class="px-3 py-2 text-left">Tipo</th>
                            <th class="px-3 py-2 text-right">Peso kg</th>
                            <th class="px-3 py-2 text-right">Vol. m³</th>
                            <th class="px-3 py-2 text-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($vehiculos as $v)
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2 font-mono font-semibold">{{ $v->placa }}</td>
                            <td class="px-3 py-2">{{ $v->tipo }}{{ $v->refrigerado ? ' ❄' : '' }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format($v->capacidad_peso_kg, 0) }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format($v->capacidad_volumen_m3, 1) }}</td>
                            <td class="px-3 py-2 text-center">
                                @php
                                    $badge = [
                                        'disponible'   => 'bg-green-100 text-green-700',
                                        'en_ruta'      => 'bg-blue-100 text-blue-700',
                                        'mantenimiento'=> 'bg-amber-100 text-amber-700',
                                        'inactivo'     => 'bg-gray-100 text-gray-600',
                                    ][$v->estado] ?? 'bg-gray-100 text-gray-600';
                                @endphp
                                <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $badge }}">{{ $v->estado }}</span>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="px-3 py-6 text-center text-gray-500">
                            No hay vehículos cargados.
                        </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-4 py-3 border-b bg-gray-50">
                <h2 class="font-semibold text-gray-800">Rutas recientes</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-3 py-2 text-left">Código</th>
                            <th class="px-3 py-2 text-left">Vehículo</th>
                            <th class="px-3 py-2 text-right">Paradas</th>
                            <th class="px-3 py-2 text-right">Uso</th>
                            <th class="px-3 py-2 text-center">Estado</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($rutas as $r)
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2 font-mono">{{ $r->codigo }}</td>
                            <td class="px-3 py-2">{{ optional($r->vehiculo)->placa ?? '—' }}</td>
                            <td class="px-3 py-2 text-right">{{ $r->paradas_count }}</td>
                            <td class="px-3 py-2 text-right text-xs">
                                {{ $r->utilizacion_peso_pct !== null ? round($r->utilizacion_peso_pct).'%' : '—' }} /
                                {{ $r->utilizacion_volumen_pct !== null ? round($r->utilizacion_volumen_pct).'%' : '—' }}
                            </td>
                            <td class="px-3 py-2 text-center">
                                <span class="px-2 py-0.5 rounded text-xs font-semibold bg-gray-100 text-gray-700">
                                    {{ $r->estado }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-center">
                                <a href="{{ route('tms.rutas.manifiesto', $r->id) }}"
                                   class="text-blue-600 hover:underline text-xs">manifiesto</a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="px-3 py-6 text-center text-gray-500">
                            No hay rutas planificadas.
                        </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
@endsection
