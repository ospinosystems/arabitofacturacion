@extends('layouts.app')

@section('content')
@include('warehouse-inventory.partials.nav')

<div class="container-fluid px-2 sm:px-4">

    <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <h1 class="text-xl sm:text-2xl font-bold text-gray-800 flex items-center">
            <i class="fas fa-clipboard-check text-blue-500 mr-2"></i>
            Conteo cíclico por ubicación
        </h1>
        <button onclick="abrirGenerar()"
                class="inline-flex items-center px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg shadow-sm transition">
            <i class="fas fa-plus mr-2"></i> Generar conteo
        </button>
    </div>

    <div class="bg-blue-50 border-l-4 border-blue-400 p-3 rounded mb-4 text-sm text-blue-900">
        <strong>Qué cuenta esto.</strong> Ubicaciones físicas contra <code>warehouse_inventory</code>.
        Es distinto del <em>inventario cíclico</em> que corre contra central, que cuenta productos contra el stock general.
        El conteo es <strong>ciego</strong> (el contador no ve la cantidad de sistema) y toda diferencia
        se <strong>recuenta antes de ajustar</strong>: ajustar al primer conteo convierte un error de conteo en un error de inventario.
    </div>

    {{-- Exactitud: el KPI que resume si el almacén es fiable --}}
    @php $ex = $exactitud; @endphp
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-xs uppercase text-gray-500 font-semibold">Exactitud (90 días)</p>
            <p class="text-3xl font-bold mt-1 {{ $ex['exactitud_pct'] === null ? 'text-gray-400' : ($ex['cumple_meta'] ? 'text-green-600' : 'text-red-600') }}">
                {{ $ex['exactitud_pct'] !== null ? $ex['exactitud_pct'] . '%' : '—' }}
            </p>
            <p class="text-xs text-gray-500 mt-1">Meta: {{ $ex['meta_pct'] }}%</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-xs uppercase text-gray-500 font-semibold">Líneas contadas</p>
            <p class="text-3xl font-bold mt-1 text-gray-800">{{ number_format($ex['lineas_contadas']) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ $ex['conteos'] }} conteo(s) cerrados</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-xs uppercase text-gray-500 font-semibold">Con diferencia</p>
            <p class="text-3xl font-bold mt-1 text-amber-600">{{ number_format($ex['lineas_con_diferencia']) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-xs uppercase text-gray-500 font-semibold">Valor del descuadre</p>
            <p class="text-2xl font-bold mt-1 text-gray-800">{{ number_format($ex['valor_diferencia'], 2) }}</p>
        </div>
    </div>

    {{-- Ubicaciones con plazo vencido según la clase del producto --}}
    <div class="bg-white rounded-lg shadow p-4 mb-4">
        <h2 class="font-semibold text-gray-800 mb-1">Ubicaciones con recuento vencido</h2>
        <p class="text-xs text-gray-500 mb-3">
            La frecuencia sale del ABC: lo que más se toca es lo que más se descuadra.
        </p>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            @foreach($frecuencias as $clase => $dias)
                @php
                    $estilos = ['A' => 'text-red-600', 'B' => 'text-amber-600', 'C' => 'text-gray-600'];
                    $n = $pendientes[$clase] ?? 0;
                @endphp
                <div class="border rounded-lg p-3 flex items-center justify-between">
                    <div>
                        <p class="font-bold text-gray-800">Clase {{ $clase }}</p>
                        <p class="text-xs text-gray-500">cada {{ $dias }} días</p>
                    </div>
                    <div class="text-right">
                        <p class="text-2xl font-bold {{ $estilos[$clase] ?? 'text-gray-600' }}">{{ number_format($n) }}</p>
                        <p class="text-xs text-gray-500">pendientes</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-4 py-3 border-b bg-gray-50">
            <h2 class="font-semibold text-gray-800">Conteos</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-3 py-2 text-left">Código</th>
                        <th class="px-3 py-2 text-left">Tipo</th>
                        <th class="px-3 py-2 text-center">Estado</th>
                        <th class="px-3 py-2 text-right">Líneas</th>
                        <th class="px-3 py-2 text-right">Contadas</th>
                        <th class="px-3 py-2 text-right">Diferencias</th>
                        <th class="px-3 py-2 text-right">Exactitud</th>
                        <th class="px-3 py-2 text-center">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($conteos as $c)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 font-mono">{{ $c->codigo }}</td>
                        <td class="px-3 py-2">{{ $c->tipo }} {{ $c->criterio_abc ? '('.$c->criterio_abc.')' : '' }}</td>
                        <td class="px-3 py-2 text-center">
                            @php
                                $badge = [
                                    'planificado' => 'bg-gray-100 text-gray-700',
                                    'en_proceso'  => 'bg-blue-100 text-blue-700',
                                    'contado'     => 'bg-amber-100 text-amber-700',
                                    'ajustado'    => 'bg-green-100 text-green-700',
                                    'cancelado'   => 'bg-red-100 text-red-700',
                                ][$c->estado] ?? 'bg-gray-100 text-gray-700';
                            @endphp
                            <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $badge }}">{{ $c->estado }}</span>
                        </td>
                        <td class="px-3 py-2 text-right">{{ $c->total_lineas }}</td>
                        <td class="px-3 py-2 text-right">{{ $c->lineas_contadas }}</td>
                        <td class="px-3 py-2 text-right {{ $c->lineas_con_diferencia > 0 ? 'text-amber-600 font-semibold' : '' }}">
                            {{ $c->lineas_con_diferencia }}
                        </td>
                        <td class="px-3 py-2 text-right">{{ $c->exactitud_pct !== null ? number_format($c->exactitud_pct, 1).'%' : '—' }}</td>
                        <td class="px-3 py-2 text-center">
                            <a href="{{ route('wms.conteos.tareas', $c->id) }}" class="text-blue-600 hover:underline">tareas</a>
                            <span class="text-gray-300">|</span>
                            <a href="{{ route('wms.conteos.reporte', $c->id) }}" class="text-blue-600 hover:underline">reporte</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="px-3 py-6 text-center text-gray-500">
                        No hay conteos todavía. Genere el primero con el botón de arriba.
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Modal de generación --}}
<div id="modalGenerar" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
        <div class="px-4 py-3 border-b flex justify-between items-center">
            <h3 class="font-semibold">Generar conteo cíclico</h3>
            <button onclick="cerrarGenerar()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4 space-y-3">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Clase ABC</label>
                <select id="gClase" class="w-full rounded-lg border-gray-300 text-sm">
                    <option value="">Todas las vencidas</option>
                    <option value="A">Sólo clase A</option>
                    <option value="B">Sólo clase B</option>
                    <option value="C">Sólo clase C</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Máximo de líneas</label>
                <input id="gLimite" type="number" value="50" min="1" max="500"
                       class="w-full rounded-lg border-gray-300 text-sm">
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input id="gCiego" type="checkbox" checked class="rounded border-gray-300">
                Conteo ciego (recomendado)
            </label>
            <p class="text-xs text-gray-500">
                Sin conteo ciego el contador ve la cantidad esperada y tiende a confirmarla en vez de contar.
            </p>
        </div>
        <div class="px-4 py-3 border-t flex justify-end gap-2">
            <button onclick="cerrarGenerar()" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancelar</button>
            <button onclick="generarConteo()" id="btnGenerar"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-lg">Generar</button>
        </div>
    </div>
</div>

<script>
function abrirGenerar() { document.getElementById('modalGenerar').classList.remove('hidden'); }
function cerrarGenerar() { document.getElementById('modalGenerar').classList.add('hidden'); }

function generarConteo() {
    const btn = document.getElementById('btnGenerar');
    btn.disabled = true;
    btn.textContent = 'Generando...';

    fetch('{{ route('wms.conteos.generar') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        },
        body: JSON.stringify({
            criterio_abc: document.getElementById('gClase').value || null,
            limite: parseInt(document.getElementById('gLimite').value, 10),
            ciego: document.getElementById('gCiego').checked,
        }),
    })
    .then(r => r.json())
    .then(d => {
        alert(d.msj || (d.estado ? 'Conteo generado' : 'No se pudo generar'));
        if (d.estado) { location.reload(); }
    })
    .catch(e => alert('Error: ' + e.message))
    .finally(() => { btn.disabled = false; btn.textContent = 'Generar'; });
}
</script>
@endsection
