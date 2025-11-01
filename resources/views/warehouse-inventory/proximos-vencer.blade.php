@extends('layouts.app')

@section('content')
@include('warehouse-inventory.partials.nav')

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-3">
                <i class="fas fa-exclamation-triangle text-warning"></i> Productos Próximos a Vencer
            </h1>
        </div>
    </div>

    <!-- Filtro de Días -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('warehouse-inventory.proximos-vencer') }}" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="dias" class="form-label">Mostrar productos que vencen en:</label>
                    <select name="dias" id="dias" class="form-select">
                        <option value="7" {{ $dias == 7 ? 'selected' : '' }}>7 días</option>
                        <option value="15" {{ $dias == 15 ? 'selected' : '' }}>15 días</option>
                        <option value="30" {{ $dias == 30 ? 'selected' : '' }}>30 días</option>
                        <option value="60" {{ $dias == 60 ? 'selected' : '' }}>60 días</option>
                        <option value="90" {{ $dias == 90 ? 'selected' : '' }}>90 días</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Alertas -->
    @if($productos->count() > 0)
        <div class="alert alert-warning" role="alert">
            <i class="fas fa-exclamation-triangle"></i> 
            Se encontraron <strong>{{ $productos->count() }}</strong> productos que vencen en los próximos <strong>{{ $dias }}</strong> días.
        </div>
    @else
        <div class="alert alert-success" role="alert">
            <i class="fas fa-check-circle"></i> 
            No hay productos próximos a vencer en los próximos <strong>{{ $dias }}</strong> días.
        </div>
    @endif

    <!-- Productos Próximos a Vencer -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Ubicación</th>
                            <th>Lote</th>
                            <th>Cantidad</th>
                            <th>Fecha Vencimiento</th>
                            <th>Días Restantes</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($productos as $producto)
                            @php
                                $diasRestantes = \Carbon\Carbon::now()->diffInDays(\Carbon\Carbon::parse($producto->fecha_vencimiento), false);
                                $vencido = $diasRestantes < 0;
                            @endphp
                            <tr class="{{ $vencido ? 'table-danger' : ($diasRestantes <= 7 ? 'table-warning' : '') }}">
                                <td>
                                    <strong>{{ $producto->inventario->descripcion }}</strong><br>
                                    <small class="text-muted">{{ $producto->inventario->codigo_barras }}</small>
                                </td>
                                <td>
                                    <strong>{{ $producto->warehouse->codigo }}</strong><br>
                                    <small class="text-muted">{{ $producto->warehouse->nombre }}</small>
                                </td>
                                <td>{{ $producto->lote ?? '-' }}</td>
                                <td>
                                    <span class="badge bg-primary">{{ number_format($producto->cantidad, 2) }}</span>
                                </td>
                                <td>{{ \Carbon\Carbon::parse($producto->fecha_vencimiento)->format('d/m/Y') }}</td>
                                <td>
                                    @if($vencido)
                                        <span class="badge bg-danger">
                                            <i class="fas fa-times"></i> Vencido hace {{ abs($diasRestantes) }} días
                                        </span>
                                    @elseif($diasRestantes == 0)
                                        <span class="badge bg-danger">
                                            <i class="fas fa-exclamation"></i> Vence HOY
                                        </span>
                                    @elseif($diasRestantes <= 7)
                                        <span class="badge bg-danger">
                                            <i class="fas fa-exclamation-triangle"></i> {{ $diasRestantes }} días
                                        </span>
                                    @elseif($diasRestantes <= 30)
                                        <span class="badge bg-warning">
                                            <i class="fas fa-clock"></i> {{ $diasRestantes }} días
                                        </span>
                                    @else
                                        <span class="badge bg-info">
                                            {{ $diasRestantes }} días
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if($producto->estado == 'disponible')
                                        <span class="badge bg-success">Disponible</span>
                                    @elseif($producto->estado == 'reservado')
                                        <span class="badge bg-warning">Reservado</span>
                                    @elseif($producto->estado == 'cuarentena')
                                        <span class="badge bg-danger">Cuarentena</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm btn-warning" onclick="marcarCuarentena({{ $producto->id }})" title="Mover a Cuarentena">
                                            <i class="fas fa-exclamation-triangle"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-info" onclick="verDetalles({{ $producto->id }})" title="Ver Detalles">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted">
                                    <i class="fas fa-check-circle fa-3x mb-3 text-success"></i>
                                    <p>No hay productos próximos a vencer</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function marcarCuarentena(id) {
    if (confirm('¿Está seguro de mover este producto a cuarentena?')) {
        // Implementar la lógica para cambiar el estado a cuarentena
        console.log('Marcar cuarentena:', id);
    }
}

function verDetalles(id) {
    // Implementar modal o redirección a vista de detalles
    console.log('Ver detalles de inventario:', id);
}
</script>
@endsection

