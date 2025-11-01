@extends('layouts.app')

@section('content')
@include('warehouse-inventory.partials.nav')

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h3 mb-3"><i class="fas fa-warehouse"></i> Gestión de Ubicaciones</h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createWarehouseModal">
                    <i class="fas fa-plus"></i> Nueva Ubicación
                </button>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('warehouses.index') }}" class="row g-3">
                <div class="col-md-3">
                    <label for="tipo" class="form-label">Tipo</label>
                    <select name="tipo" id="tipo" class="form-select">
                        <option value="">Todos los tipos</option>
                        <option value="pasillo" {{ request('tipo') == 'pasillo' ? 'selected' : '' }}>Pasillo</option>
                        <option value="estante" {{ request('tipo') == 'estante' ? 'selected' : '' }}>Estante</option>
                        <option value="nivel" {{ request('tipo') == 'nivel' ? 'selected' : '' }}>Nivel</option>
                        <option value="zona" {{ request('tipo') == 'zona' ? 'selected' : '' }}>Zona</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="estado" class="form-label">Estado</label>
                    <select name="estado" id="estado" class="form-select">
                        <option value="">Todos los estados</option>
                        <option value="activa" {{ request('estado') == 'activa' ? 'selected' : '' }}>Activa</option>
                        <option value="inactiva" {{ request('estado') == 'inactiva' ? 'selected' : '' }}>Inactiva</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="buscar" class="form-label">Buscar</label>
                    <input type="text" name="buscar" id="buscar" class="form-control" value="{{ request('buscar') }}" placeholder="Código o nombre">
                </div>
                
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla de Ubicaciones -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Capacidad</th>
                            <th>Ocupación</th>
                            <th>Estado</th>
                            <th>Ubicación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($warehouses as $warehouse)
                            <tr>
                                <td><strong>{{ $warehouse->codigo }}</strong></td>
                                <td>{{ $warehouse->nombre }}</td>
                                <td>
                                    <span class="badge bg-secondary">{{ ucfirst($warehouse->tipo) }}</span>
                                </td>
                                <td>
                                    {{ $warehouse->capacidad_maxima ? number_format($warehouse->capacidad_maxima, 2) : 'Sin límite' }}
                                </td>
                                <td>
                                    @if($warehouse->capacidad_maxima)
                                        @php
                                            $ocupacion = $warehouse->inventarios()->sum('cantidad');
                                            $porcentaje = ($ocupacion / $warehouse->capacidad_maxima) * 100;
                                        @endphp
                                        <div class="progress" style="height: 25px;">
                                            <div class="progress-bar {{ $porcentaje > 90 ? 'bg-danger' : ($porcentaje > 70 ? 'bg-warning' : 'bg-success') }}" 
                                                 role="progressbar" 
                                                 style="width: {{ min($porcentaje, 100) }}%"
                                                 aria-valuenow="{{ $porcentaje }}" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                                {{ number_format($porcentaje, 1) }}%
                                            </div>
                                        </div>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @if($warehouse->estado == 'activa')
                                        <span class="badge bg-success">Activa</span>
                                    @else
                                        <span class="badge bg-danger">Inactiva</span>
                                    @endif
                                </td>
                                <td>
                                    <small>{{ $warehouse->ubicacion ?? '-' }}</small>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('warehouses.show', $warehouse->id) }}" class="btn btn-sm btn-info" title="Ver detalles">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-success" onclick="asignarProductoAUbicacion({{ $warehouse->id }}, '{{ $warehouse->codigo }}')" title="Asignar producto">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="editWarehouse({{ $warehouse->id }})" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="deleteWarehouse({{ $warehouse->id }})" title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted">
                                    <i class="fas fa-inbox fa-3x mb-3"></i>
                                    <p>No se encontraron ubicaciones</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <!-- Paginación -->
            @if($warehouses instanceof \Illuminate\Pagination\LengthAwarePaginator)
                <div class="d-flex justify-content-center mt-4">
                    {{ $warehouses->appends(request()->query())->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Modal para crear ubicación -->
<div class="modal fade" id="createWarehouseModal" tabindex="-1" aria-labelledby="createWarehouseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createWarehouseModalLabel">
                    <i class="fas fa-plus"></i> Nueva Ubicación
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="createWarehouseForm" onsubmit="createWarehouse(event)">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="codigo" class="form-label">Código *</label>
                            <input type="text" class="form-control" id="codigo" name="codigo" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="nombre" class="form-label">Nombre *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="tipo" class="form-label">Tipo *</label>
                            <select class="form-select" id="tipo" name="tipo" required>
                                <option value="">Seleccione...</option>
                                <option value="pasillo">Pasillo</option>
                                <option value="estante">Estante</option>
                                <option value="nivel">Nivel</option>
                                <option value="zona">Zona</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="capacidad_maxima" class="form-label">Capacidad Máxima</label>
                            <input type="number" step="0.01" class="form-control" id="capacidad_maxima" name="capacidad_maxima">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="ubicacion" class="form-label">Ubicación Física</label>
                        <input type="text" class="form-control" id="ubicacion" name="ubicacion" placeholder="Ej: Pasillo A, Estante 1">
                    </div>
                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Crear Ubicación
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para asignar producto a ubicación -->
<div class="modal fade" id="asignarProductoModal" tabindex="-1" aria-labelledby="asignarProductoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="asignarProductoModalLabel">
                    <i class="fas fa-plus"></i> Asignar Producto a Ubicación
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="asignarProductoForm" onsubmit="asignarProducto(event)">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Ubicación seleccionada:</strong> <span id="ubicacionSeleccionada"></span>
                    </div>
                    
                    <input type="hidden" id="warehouse_id_asignar" name="warehouse_id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="inventario_id_asignar" class="form-label">Producto (ID) *</label>
                            <input type="number" class="form-control" id="inventario_id_asignar" name="inventario_id" required placeholder="ID del producto">
                            <small class="text-muted">Ingrese el ID del producto</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="cantidad_asignar" class="form-label">Cantidad *</label>
                            <input type="number" step="0.01" class="form-control" id="cantidad_asignar" name="cantidad" required min="0.01">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="fecha_entrada_asignar" class="form-label">Fecha de Entrada *</label>
                            <input type="date" class="form-control" id="fecha_entrada_asignar" name="fecha_entrada" required value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="lote_asignar" class="form-label">Lote</label>
                            <input type="text" class="form-control" id="lote_asignar" name="lote" placeholder="Número de lote">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="fecha_vencimiento_asignar" class="form-label">Fecha de Vencimiento</label>
                            <input type="date" class="form-control" id="fecha_vencimiento_asignar" name="fecha_vencimiento">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="documento_referencia_asignar" class="form-label">Documento de Referencia</label>
                            <input type="text" class="form-control" id="documento_referencia_asignar" name="documento_referencia" placeholder="Ej: Orden de compra, factura">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="observaciones_asignar" class="form-label">Observaciones</label>
                        <textarea class="form-control" id="observaciones_asignar" name="observaciones" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Asignar Producto
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function asignarProductoAUbicacion(warehouseId, codigo) {
    document.getElementById('warehouse_id_asignar').value = warehouseId;
    document.getElementById('ubicacionSeleccionada').textContent = codigo;
    
    // Limpiar el formulario
    document.getElementById('asignarProductoForm').reset();
    document.getElementById('warehouse_id_asignar').value = warehouseId;
    document.getElementById('fecha_entrada_asignar').value = '{{ date("Y-m-d") }}';
    
    // Abrir el modal
    const modal = new bootstrap.Modal(document.getElementById('asignarProductoModal'));
    modal.show();
}

function asignarProducto(event) {
    event.preventDefault();
    const form = document.getElementById('asignarProductoForm');
    const formData = new FormData(form);
    
    fetch('{{ route("warehouse-inventory.asignar") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.estado) {
            alert(data.msj);
            window.location.reload();
        } else {
            alert(data.msj || 'Error al asignar el producto');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al procesar la solicitud');
    });
}

function createWarehouse(event) {
    event.preventDefault();
    const form = document.getElementById('createWarehouseForm');
    const formData = new FormData(form);
    
    fetch('{{ route("warehouses.store") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.estado) {
            alert(data.msj);
            window.location.reload();
        } else {
            alert(data.msj || 'Error al crear la ubicación');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al procesar la solicitud');
    });
}

function editWarehouse(id) {
    // Implementar la edición
    window.location.href = `/warehouses/${id}/edit`;
}

function deleteWarehouse(id) {
    if (confirm('¿Está seguro de eliminar esta ubicación?')) {
        fetch(`/warehouses/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.estado) {
                alert(data.msj);
                window.location.reload();
            } else {
                alert(data.msj || 'Error al eliminar la ubicación');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al procesar la solicitud');
        });
    }
}
</script>
@endsection

