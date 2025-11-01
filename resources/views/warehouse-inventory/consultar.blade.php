@extends('layouts.app')

@section('content')
@include('warehouse-inventory.partials.nav')

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-3">
                <i class="fas fa-map-marker-alt"></i> Ubicaciones del Producto
            </h1>
        </div>
    </div>

    <!-- Información del Producto -->
    <div class="card mb-4">
        <div class="card-header bg-primary ">
            <h5 class="mb-0"><i class="fas fa-box"></i> Información del Producto</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Descripción:</strong> {{ $producto->descripcion }}</p>
                    <p><strong>Código de Barras:</strong> {{ $producto->codigo_barras }}</p>
                    <p><strong>Categoría:</strong> {{ $producto->categoria->nombre ?? 'N/A' }}</p>
                </div>
                <div class="col-md-6">
                    <p><strong>Marca:</strong> {{ $producto->marca->nombre ?? 'N/A' }}</p>
                    <p><strong>Proveedor:</strong> {{ $producto->proveedor->razonsocial ?? 'N/A' }}</p>
                    <p><strong>Stock Total en Ubicaciones:</strong> 
                        <span class="badge bg-success fs-6">{{ number_format($totalStock, 2) }}</span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Ubicaciones -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-warehouse"></i> Ubicaciones ({{ $ubicaciones->count() }})</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Ubicación</th>
                            <th>Lote</th>
                            <th>Cantidad</th>
                            <th>Estado</th>
                            <th>Fecha Entrada</th>
                            <th>Fecha Vencimiento</th>
                            <th>Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ubicaciones as $ubicacion)
                            <tr>
                                <td>
                                    <strong>{{ $ubicacion->warehouse->codigo }}</strong><br>
                                    <small class="text-muted">{{ $ubicacion->warehouse->nombre }}</small>
                                </td>
                                <td>{{ $ubicacion->lote ?? '-' }}</td>
                                <td>
                                    <span class="badge bg-primary">{{ number_format($ubicacion->cantidad, 2) }}</span>
                                </td>
                                <td>
                                    @if($ubicacion->estado == 'disponible')
                                        <span class="badge bg-success">Disponible</span>
                                    @elseif($ubicacion->estado == 'reservado')
                                        <span class="badge bg-warning">Reservado</span>
                                    @elseif($ubicacion->estado == 'cuarentena')
                                        <span class="badge bg-danger">Cuarentena</span>
                                    @endif
                                </td>
                                <td>{{ $ubicacion->fecha_entrada ? \Carbon\Carbon::parse($ubicacion->fecha_entrada)->format('d/m/Y') : '-' }}</td>
                                <td>
                                    @if($ubicacion->fecha_vencimiento)
                                        {{ \Carbon\Carbon::parse($ubicacion->fecha_vencimiento)->format('d/m/Y') }}
                                        @php
                                            $diasVencimiento = \Carbon\Carbon::now()->diffInDays(\Carbon\Carbon::parse($ubicacion->fecha_vencimiento), false);
                                        @endphp
                                        @if($diasVencimiento < 0)
                                            <span class="badge bg-danger ms-1">Vencido</span>
                                        @elseif($diasVencimiento < 30)
                                            <span class="badge bg-warning ms-1">{{ $diasVencimiento }} días</span>
                                        @endif
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    <small>{{ $ubicacion->observaciones ?? '-' }}</small>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted">
                                    <i class="fas fa-inbox fa-3x mb-3"></i>
                                    <p>Este producto no tiene ubicaciones asignadas</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3">
        <a href="{{ route('warehouse-inventory.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>
</div>
@endsection

