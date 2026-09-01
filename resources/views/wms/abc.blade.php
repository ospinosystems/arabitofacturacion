@extends('layouts.app')

@section('content')
@include('warehouse-inventory.partials.nav')

<div class="container-fluid px-2 sm:px-4">

    <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <h1 class="text-xl sm:text-2xl font-bold text-gray-800 flex items-center">
            <i class="fas fa-chart-pie text-blue-500 mr-2"></i>
            Clasificación ABC
        </h1>
        <form method="GET" class="flex gap-2">
            <select name="criterio" onchange="this.form.submit()"
                    class="rounded-lg border-gray-300 text-sm">
                @foreach(['combinado' => 'Combinado (slotting)', 'popularidad' => 'Popularidad (nº de líneas)', 'valor' => 'Valor (consumo valorizado)', 'unidades' => 'Unidades'] as $k => $v)
                    <option value="{{ $k }}" {{ $criterio === $k ? 'selected' : '' }}>{{ $v }}</option>
                @endforeach
            </select>
        </form>
    </div>

    {{-- El ABC parte de Pareto: pocos artículos concentran casi toda la actividad. --}}
    <div class="bg-blue-50 border-l-4 border-blue-400 p-3 rounded mb-4 text-sm text-blue-900">
        <strong>Cómo leerlo.</strong> Los productos se ordenan de mayor a menor actividad y se acumula el porcentaje:
        la clase <strong>A</strong> llega al {{ config('wms.abc.umbral_a') }}%, la <strong>B</strong> al {{ config('wms.abc.umbral_b') }}%,
        y el resto es <strong>C</strong>. Si pocos productos concentran mucha actividad, el Pareto está sano.
        @if($periodo)
            <br>Periodo analizado: {{ $periodo->periodo_inicio->format('d/m/Y') }} a {{ $periodo->periodo_fin->format('d/m/Y') }}
            &middot; recalculado {{ $periodo->calculado_en ? $periodo->calculado_en->diffForHumans() : '-' }}.
        @endif
    </div>

    {{-- Salud del dato físico: sin peso ni volumen el slotting trabaja a ciegas --}}
    @php $pctMedidos = $totalActivos > 0 ? round(($medidos / $totalActivos) * 100, 1) : 0; @endphp
    @if($pctMedidos < 100)
    <div class="bg-amber-50 border-l-4 border-amber-400 p-3 rounded mb-4 text-sm text-amber-900">
        <strong>Datos físicos.</strong>
        {{ number_format($medidos) }} de {{ number_format($totalActivos) }} productos ({{ $pctMedidos }}%) tienen peso y volumen medidos.
        El resto usa valores <strong>estimados</strong>, suficientes para probar el flujo pero no para decidir con confianza.
        @if($sinDatos > 0)
            {{ number_format($sinDatos) }} no tienen ningún dato físico.
        @endif
        <a href="{{ route('wms.medidas.pendientes') }}" class="underline font-medium">Ver pendientes de medir</a>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
        @foreach($distribucion as $d)
            @php
                // Clases completas y literales: Tailwind purga lo que construye por
                // interpolación, así que "border-{$c}-500" no existiría en el CSS final.
                $estilos = [
                    'A' => ['borde' => 'border-red-500',   'barra' => 'bg-red-500'],
                    'B' => ['borde' => 'border-amber-500', 'barra' => 'bg-amber-500'],
                    'C' => ['borde' => 'border-gray-400',  'barra' => 'bg-gray-400'],
                ];
                $e = $estilos[$d['clase']] ?? $estilos['C'];
            @endphp
            <div class="bg-white rounded-lg shadow p-4 border-t-4 {{ $e['borde'] }}">
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-gray-800">Clase {{ $d['clase'] }}</span>
                    <span class="text-sm text-gray-500">{{ number_format($d['productos']) }} productos</span>
                </div>
                <div class="mt-3 text-sm text-gray-600">
                    <div class="flex justify-between py-1">
                        <span>% del catálogo</span>
                        <span class="font-semibold">{{ $d['productos_pct'] }}%</span>
                    </div>
                    <div class="flex justify-between py-1">
                        <span>% de la actividad</span>
                        <span class="font-semibold">{{ $d['participacion_pct'] }}%</span>
                    </div>
                </div>
                <div class="mt-2 h-2 bg-gray-200 rounded overflow-hidden">
                    <div class="h-full {{ $e['barra'] }}" style="width: {{ min(100, $d['participacion_pct']) }}%"></div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-4 py-3 border-b bg-gray-50">
                <h2 class="font-semibold text-gray-800">Top 50 por actividad</h2>
            </div>
            <div class="overflow-x-auto" style="max-height: 480px">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100 sticky top-0">
                        <tr>
                            <th class="px-3 py-2 text-left">#</th>
                            <th class="px-3 py-2 text-left">Producto</th>
                            <th class="px-3 py-2 text-center">Clase</th>
                            <th class="px-3 py-2 text-right">Líneas</th>
                            <th class="px-3 py-2 text-right">Acum.</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($top as $t)
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2 text-gray-500">{{ $t->ranking }}</td>
                            <td class="px-3 py-2">{{ Str::limit(optional($t->inventario)->descripcion ?? '—', 42) }}</td>
                            <td class="px-3 py-2 text-center">
                                <span class="px-2 py-0.5 rounded text-xs font-bold
                                    {{ $t->clase === 'A' ? 'bg-red-100 text-red-700' : ($t->clase === 'B' ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600') }}">
                                    {{ $t->clase }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-right">{{ number_format($t->lineas) }}</td>
                            <td class="px-3 py-2 text-right text-gray-500">{{ number_format($t->acumulado_pct, 1) }}%</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-4 py-3 border-b bg-gray-50">
                <h2 class="font-semibold text-gray-800">Candidatos a reubicar</h2>
                <p class="text-xs text-gray-500 mt-0.5">
                    Productos que subieron de clase: ahora se piden más y conviene acercarlos al muelle.
                </p>
            </div>
            <div class="overflow-x-auto" style="max-height: 480px">
                @if($reubicar->isEmpty())
                    <p class="p-4 text-sm text-gray-500">
                        Sin cambios de clase registrados todavía. Aparecerán tras el segundo recálculo del ABC.
                    </p>
                @else
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100 sticky top-0">
                        <tr>
                            <th class="px-3 py-2 text-left">Producto</th>
                            <th class="px-3 py-2 text-center">Antes</th>
                            <th class="px-3 py-2 text-center">Ahora</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($reubicar as $r)
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2">{{ Str::limit(optional($r->inventario)->descripcion ?? '—', 46) }}</td>
                            <td class="px-3 py-2 text-center text-gray-500">{{ $r->clase_anterior ?? '—' }}</td>
                            <td class="px-3 py-2 text-center font-bold text-green-600">{{ $r->clase_nueva }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </div>

    </div>

    <div class="mt-4 bg-gray-50 border rounded-lg p-3 text-xs text-gray-600">
        Para recalcular:
        <code class="bg-gray-200 px-1.5 py-0.5 rounded">php artisan wms:abc-recalcular</code>
        &middot; conviene programarlo semanalmente.
    </div>

</div>
@endsection
