@extends('layouts.app')

@section('content')
@include('warehouse-inventory.partials.nav')

<!-- Agregar librería JsBarcode para generar códigos de barras -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h3 mb-3"><i class="fas fa-warehouse"></i> Gestión de Ubicaciones</h1>
                <div>
                    <a href="/warehouses/cargar-por-rango" class="btn btn-info me-2">
                        <i class="fas fa-layer-group"></i> Cargar por Rango
                    </a>
                    <button type="button" class="btn btn-warning me-2" onclick="imprimirSeleccionadas()" id="btnImprimirSeleccionadas" style="display: none;">
                        <i class="fas fa-print"></i> Imprimir Seleccionadas (<span id="contadorSeleccionadas">0</span>)
                    </button>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createWarehouseModal">
                        <i class="fas fa-plus"></i> Nueva Ubicación
                    </button>
                </div>
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
                            <th width="40">
                                <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)" class="form-check-input">
                            </th>
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
                                <td>
                                    <input type="checkbox" class="form-check-input warehouse-checkbox" 
                                           value="{{ $warehouse->codigo }}" 
                                           onchange="actualizarContador()">
                                </td>
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
                                        <button type="button" class="btn btn-sm btn-warning" onclick="imprimirEtiqueta('{{ $warehouse->codigo }}')" title="Imprimir etiqueta">
                                            <i class="fas fa-print"></i>
                                        </button>
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
                                <td colspan="9" class="text-center text-muted">
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
                        <div class="col-md-3 mb-3">
                            <label for="pasillo" class="form-label">Pasillo *</label>
                            <input type="text" class="form-control" id="pasillo" name="pasillo" required placeholder="Ej: A, B, C" maxlength="10">
                            <small class="text-muted">Letra o código del pasillo</small>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="cara" class="form-label">Cara *</label>
                            <input type="number" class="form-control" id="cara" name="cara" required min="1" placeholder="1, 2">
                            <small class="text-muted">Número de cara</small>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="rack" class="form-label">Rack *</label>
                            <input type="number" class="form-control" id="rack" name="rack" required min="1" placeholder="1-100">
                            <small class="text-muted">Número de rack</small>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="nivel" class="form-label">Nivel *</label>
                            <input type="number" class="form-control" id="nivel" name="nivel" required min="1" max="10" placeholder="1-4">
                            <small class="text-muted">Nivel de altura</small>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Código generado:</strong> <span id="codigoGenerado">Se generará automáticamente</span>
                        <small class="d-block mt-1">Formato: PASILLO + CARA - RACK - NIVEL (Ej: A1-01-1)</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nombre" class="form-label">Nombre</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" placeholder="Nombre descriptivo (opcional)">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="tipo" class="form-label">Tipo *</label>
                            <select class="form-select" id="tipo" name="tipo" required>
                                <option value="">Seleccione...</option>
                                <option value="almacenamiento" selected>Almacenamiento</option>
                                <option value="picking">Picking</option>
                                <option value="recepcion">Recepción</option>
                                <option value="despacho">Despacho</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="estado" class="form-label">Estado *</label>
                            <select class="form-select" id="estado" name="estado" required>
                                <option value="activa" selected>Activa</option>
                                <option value="inactiva">Inactiva</option>
                                <option value="mantenimiento">Mantenimiento</option>
                                <option value="bloqueada">Bloqueada</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="zona" class="form-label">Zona</label>
                            <input type="text" class="form-control" id="zona" name="zona" placeholder="Ej: Zona A, Almacén Principal">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="capacidad_peso" class="form-label">Capacidad Peso (Kg)</label>
                            <input type="number" step="0.01" class="form-control" id="capacidad_peso" name="capacidad_peso" placeholder="Kg">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="capacidad_volumen" class="form-label">Capacidad Volumen (m³)</label>
                            <input type="number" step="0.01" class="form-control" id="capacidad_volumen" name="capacidad_volumen" placeholder="m³">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="capacidad_unidades" class="form-label">Capacidad Unidades</label>
                            <input type="number" class="form-control" id="capacidad_unidades" name="capacidad_unidades" placeholder="Unidades">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="2" placeholder="Descripción adicional (opcional)"></textarea>
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
// Función para seleccionar/deseleccionar todos los checkboxes
function toggleSelectAll(checkbox) {
    const checkboxes = document.querySelectorAll('.warehouse-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = checkbox.checked;
    });
    actualizarContador();
}

// Función para actualizar el contador de elementos seleccionados
function actualizarContador() {
    const checkboxes = document.querySelectorAll('.warehouse-checkbox:checked');
    const contador = checkboxes.length;
    const boton = document.getElementById('btnImprimirSeleccionadas');
    const spanContador = document.getElementById('contadorSeleccionadas');
    
    spanContador.textContent = contador;
    
    if (contador > 0) {
        boton.style.display = 'inline-block';
    } else {
        boton.style.display = 'none';
    }
    
    // Actualizar el checkbox "Seleccionar todos"
    const selectAll = document.getElementById('selectAll');
    const allCheckboxes = document.querySelectorAll('.warehouse-checkbox');
    selectAll.checked = allCheckboxes.length > 0 && contador === allCheckboxes.length;
}

// Función para imprimir múltiples etiquetas seleccionadas
function imprimirSeleccionadas() {
    const checkboxes = document.querySelectorAll('.warehouse-checkbox:checked');
    const codigos = Array.from(checkboxes).map(cb => cb.value);
    
    if (codigos.length === 0) {
        alert('Por favor, seleccione al menos una ubicación para imprimir');
        return;
    }
    
    imprimirMultiplesEtiquetas(codigos);
}

// Función para imprimir múltiples etiquetas en una sola ventana
function imprimirMultiplesEtiquetas(codigos) {
    const ventanaImpresion = window.open('', '_blank', 'width=600,height=800');
    
    // Generar el HTML para múltiples etiquetas
    let etiquetasHTML = '';
    codigos.forEach(codigo => {
        etiquetasHTML += `
            <div class="etiqueta-container">
                <div class="etiqueta">
                    <div class="contenido-wrapper">
                    <div class="codigo-texto">${codigo}</div>
                        <div class="barcode-wrapper">
                            <div class="barcode-container">
                    <svg class="barcode" data-codigo="${codigo}"></svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    const contenido = `
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Etiquetas de Ubicaciones</title>
            <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"><\/script>
            <style>
                @page {
                    size: 57mm 44mm;
                    margin: 0;
                }
                
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                
                html, body {
                    margin: 0;
                    padding: 0;
                    font-family: Arial, 'Helvetica Neue', Helvetica, sans-serif;
                    background: white;
                }
                
                .etiqueta-container {
                    width: 57mm;
                    height: 44mm;
                    page-break-after: always;
                    break-after: page;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    padding: 0.5mm;
                }
                
                .etiqueta-container:last-child {
                    page-break-after: auto;
                    break-after: auto;
                }
                
                .etiqueta {
                    width: 100%;
                    height: 100%;
                    max-width: 55mm;
                    display: flex;
                    flex-direction: column;
                    justify-content: flex-start;
                    align-items: center;
                    text-align: center;
                }
                
                .contenido-wrapper {
                    width: 100%;
                    padding: 0.5mm;
                    border: 1.5mm solid #000;
                    background: white;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: flex-start;
                    flex: 1;
                    min-height: 0;
                }
                
                .codigo-texto {
                    font-size: 28px;
                    font-weight: 900;
                    letter-spacing: 1.5px;
                    background: transparent;
                    color: #000;
                    padding: 0.5mm 2mm;
                    display: inline-block;
                    margin-bottom: 0;
                    margin-top: 0;
                    width: 100%;
                    text-align: center;
                }
                
                .barcode-wrapper {
                    width: 100%;
                    padding: 0;
                    background: white;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    flex: 1;
                    min-height: 0;
                    margin-top: 0;
                }
                
                .barcode-container {
                    width: 70%;
                    max-width: 70%;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    overflow: hidden;
                }
                
                .barcode {
                    width: 100%;
                    max-width: 100%;
                    height: auto;
                    max-height: 14mm;
                }
                
                @media print {
                    html, body {
                        width: 57mm;
                        height: 44mm;
                        margin: 0;
                        padding: 0;
                    }
                    
                    .etiqueta-container {
                        width: 57mm;
                        height: 44mm;
                        margin: 0;
                        padding: 0.5mm;
                    }
                    
                    .etiqueta {
                        max-width: 55mm;
                    }
                    
                    body {
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                    
                    @page {
                        margin: 0;
                        size: 57mm 44mm;
                    }
                }
            </style>
        </head>
        <body>
            ${etiquetasHTML}
            
            <script>
                // Generar códigos de barras para todas las etiquetas
                document.addEventListener('DOMContentLoaded', function() {
                    const barcodes = document.querySelectorAll('.barcode');
                    barcodes.forEach(function(barcode) {
                        const codigo = barcode.getAttribute('data-codigo');
                        const container = barcode.closest('.etiqueta');
                        try {
                        JsBarcode(barcode, codigo, {
                            format: "CODE128",
                            width: 0.5,
                            height: 14,
                            displayValue: false,
                            margin: 1,
                            marginLeft: 1,
                            marginRight: 1,
                            fontSize: 0,
                            background: "#ffffff"
                        });
                            
                            // Ajustar SVG
                            barcode.style.maxWidth = '100%';
                            barcode.style.width = '100%';
                            barcode.style.height = 'auto';
                        } catch (e) {
                            console.error("Error generando código de barras:", e);
                        }
                    });
                    
                    // Imprimir automáticamente cuando se cargue todo
                    setTimeout(function() {
                        window.print();
                    }, 500);
                });
            <\/script>
        </body>
        </html>
    `;
    
    ventanaImpresion.document.write(contenido);
    ventanaImpresion.document.close();
}

function imprimirEtiqueta(codigo) {
    // Crear ventana emergente para impresión
    const ventanaImpresion = window.open('', '_blank', 'width=400,height=300');
    
    // Contenido HTML de la etiqueta con medidas 57mm x 44mm optimizado
    const contenido = `
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Etiqueta ${codigo}</title>
            <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"><\/script>
            <style>
                @page {
                    size: 57mm 44mm;
                    margin: 0;
                }
                
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                
                html, body {
                    width: 57mm;
                    height: 44mm;
                    margin: 0;
                    padding: 0;
                    font-family: Arial, 'Helvetica Neue', Helvetica, sans-serif;
                    background: white;
                    overflow: hidden;
                }
                
                .etiqueta {
                    width: 57mm;
                    height: 44mm;
                    padding: 0.5mm 1mm;
                    display: flex;
                    flex-direction: column;
                    justify-content: flex-start;
                    align-items: center;
                    text-align: center;
                }
                
                .contenido-wrapper {
                    width: 100%;
                    padding: 0.5mm;
                    border: 1.5mm solid #000;
                    background: white;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: flex-start;
                    flex: 1;
                    min-height: 0;
                }
                
                .codigo-texto {
                    font-size: 28px;
                    font-weight: 900;
                    letter-spacing: 1.5px;
                    background: transparent;
                    color: #000;
                    padding: 0.5mm 2mm;
                    display: inline-block;
                    margin-bottom: 0;
                    margin-top: 0;
                    width: 100%;
                    text-align: center;
                }
                
                .barcode-wrapper {
                    width: 100%;
                    padding: 0;
                    background: white;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    flex: 1;
                    min-height: 0;
                    margin-top: 0;
                }
                
                .barcode-container {
                    width: 70%;
                    max-width: 70%;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    overflow: hidden;
                }
                
                #barcode {
                    width: 100%;
                    max-width: 100%;
                    height: auto;
                    max-height: 14mm;
                }
                
                @media print {
                    html, body {
                        width: 57mm;
                        height: 44mm;
                        margin: 0;
                        padding: 0;
                    }
                    
                    .etiqueta {
                        width: 57mm;
                        height: 44mm;
                        margin: 0;
                        padding: 0.5mm 1mm;
                    }
                    
                    body {
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                    
                    @page {
                        margin: 0;
                        size: 57mm 44mm;
                    }
                }
            </style>
        </head>
        <body>
            <div class="etiqueta">
                <div class="contenido-wrapper">
                <div class="codigo-texto">${codigo}</div>
                    <div class="barcode-wrapper">
                <div class="barcode-container">
                    <svg id="barcode"></svg>
                        </div>
                    </div>
                </div>
            </div>
            
            <script>
                // Esperar a que el DOM esté listo
                document.addEventListener('DOMContentLoaded', function() {
                    // Generar código de barras
                    try {
                        JsBarcode("#barcode", "${codigo}", {
                            format: "CODE128",
                            width: 0.5,
                            height: 14,
                            displayValue: false,
                            margin: 1,
                            marginLeft: 1,
                            marginRight: 1,
                            fontSize: 0,
                            background: "#ffffff"
                        });
                        
                        // Ajustar SVG para que se ajuste al contenedor
                        const svgElement = document.getElementById('barcode');
                        if (svgElement) {
                            svgElement.style.maxWidth = '100%';
                            svgElement.style.width = '100%';
                            svgElement.style.height = 'auto';
                        }
                    } catch (e) {
                        console.error("Error generando código de barras:", e);
                    }
                });
                
                // Imprimir automáticamente cuando se cargue la página
                window.onload = function() {
                    setTimeout(function() {
                        window.print();
                    }, 500);
                };
                
                // Cerrar ventana después de imprimir
                window.onafterprint = function() {
                    setTimeout(function() {
                        window.close();
                    }, 100);
                };
            <\/script>
        </body>
        </html>
    `;
    
    ventanaImpresion.document.write(contenido);
    ventanaImpresion.document.close();
}

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

// Función para actualizar el código generado en tiempo real
function actualizarCodigoGenerado() {
    const pasillo = document.getElementById('pasillo').value.toUpperCase();
    const cara = document.getElementById('cara').value;
    const rack = document.getElementById('rack').value;
    const nivel = document.getElementById('nivel').value;
    
    if (pasillo && cara && rack && nivel) {
        const rackPadded = String(rack).padStart(2, '0');
        const codigoGenerado = `${pasillo}${cara}-${rackPadded}-${nivel}`;
        document.getElementById('codigoGenerado').textContent = codigoGenerado;
    } else {
        document.getElementById('codigoGenerado').textContent = 'Se generará automáticamente';
    }
}

// Agregar event listeners cuando se carga la página
document.addEventListener('DOMContentLoaded', function() {
    const camposPasillo = document.getElementById('pasillo');
    const camposCara = document.getElementById('cara');
    const camposRack = document.getElementById('rack');
    const camposNivel = document.getElementById('nivel');
    
    if (camposPasillo) camposPasillo.addEventListener('input', actualizarCodigoGenerado);
    if (camposCara) camposCara.addEventListener('input', actualizarCodigoGenerado);
    if (camposRack) camposRack.addEventListener('input', actualizarCodigoGenerado);
    if (camposNivel) camposNivel.addEventListener('input', actualizarCodigoGenerado);
});

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


