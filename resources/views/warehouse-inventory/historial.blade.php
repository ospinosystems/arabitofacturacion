@extends('layouts.app')

@section('content')
@include('warehouse-inventory.partials.nav')

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-3"><i class="fas fa-history"></i> Historial de Movimientos</h1>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('warehouse-inventory.historial') }}" class="row g-3">
                <div class="col-md-2">
                    <label for="tipo" class="form-label">Tipo de Movimiento</label>
                    <select name="tipo" id="tipo" class="form-select">
                        <option value="">Todos</option>
                        <option value="entrada" {{ request('tipo') == 'entrada' ? 'selected' : '' }}>Entrada</option>
                        <option value="salida" {{ request('tipo') == 'salida' ? 'selected' : '' }}>Salida</option>
                        <option value="transferencia" {{ request('tipo') == 'transferencia' ? 'selected' : '' }}>Transferencia</option>
                        <option value="ajuste" {{ request('tipo') == 'ajuste' ? 'selected' : '' }}>Ajuste</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="warehouse_id" class="form-label">Ubicación</label>
                    <select name="warehouse_id" id="warehouse_id" class="form-select">
                        <option value="">Todas las ubicaciones</option>
                        @foreach($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" {{ request('warehouse_id') == $warehouse->id ? 'selected' : '' }}>
                                {{ $warehouse->codigo }} - {{ $warehouse->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                    <input type="date" name="fecha_inicio" id="fecha_inicio" class="form-control" value="{{ request('fecha_inicio') }}">
                </div>
                
                <div class="col-md-2">
                    <label for="fecha_fin" class="form-label">Fecha Fin</label>
                    <input type="date" name="fecha_fin" id="fecha_fin" class="form-control" value="{{ request('fecha_fin') }}">
                </div>
                
                <div class="col-md-2">
                    <label for="inventario_id" class="form-label">ID Producto</label>
                    <input type="number" name="inventario_id" id="inventario_id" class="form-control" value="{{ request('inventario_id') }}" placeholder="ID">
                </div>
                
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Historial de Movimientos -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Producto</th>
                            <th>Origen</th>
                            <th>Destino</th>
                            <th>Cantidad</th>
                            <th>Lote</th>
                            <th>Usuario</th>
                            <th>Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($movimientos as $movimiento)
                            <tr>
                                <td>
                                    {{ \Carbon\Carbon::parse($movimiento->fecha_movimiento)->format('d/m/Y H:i') }}
                                </td>
                                <td>
                                    @if($movimiento->tipo == 'entrada')
                                        <span class="badge bg-success"><i class="fas fa-arrow-down"></i> Entrada</span>
                                    @elseif($movimiento->tipo == 'salida')
                                        <span class="badge bg-danger"><i class="fas fa-arrow-up"></i> Salida</span>
                                    @elseif($movimiento->tipo == 'transferencia')
                                        <span class="badge bg-primary"><i class="fas fa-exchange-alt"></i> Transferencia</span>
                                    @elseif($movimiento->tipo == 'ajuste')
                                        <span class="badge bg-warning"><i class="fas fa-edit"></i> Ajuste</span>
                                    @endif
                                </td>
                                <td>
                                    <strong>{{ $movimiento->inventario->descripcion }}</strong><br>
                                    <small class="text-muted">{{ $movimiento->inventario->codigo_barras }}</small>
                                </td>
                                <td>
                                    @if($movimiento->warehouseOrigen)
                                        {{ $movimiento->warehouseOrigen->codigo }}<br>
                                        <small class="text-muted">{{ $movimiento->warehouseOrigen->nombre }}</small>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @if($movimiento->warehouseDestino)
                                        {{ $movimiento->warehouseDestino->codigo }}<br>
                                        <small class="text-muted">{{ $movimiento->warehouseDestino->nombre }}</small>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-primary">{{ number_format($movimiento->cantidad, 2) }}</span>
                                </td>
                                <td>{{ $movimiento->lote ?? '-' }}</td>
                                <td>
                                    @if($movimiento->usuario)
                                        {{ $movimiento->usuario->name }}
                                    @else
                                        Sistema
                                    @endif
                                </td>
                                <td>
                                    <small>{{ $movimiento->observaciones ?? '-' }}</small>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted">
                                    <i class="fas fa-inbox fa-3x mb-3"></i>
                                    <p>No se encontraron movimientos</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <!-- Paginación -->
            <div class="d-flex justify-content-center mt-4">
                {{ $movimientos->appends(request()->query())->links() }}
            </div>
        </div>
    </div>
</div>
@endsection

