<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="icon" type="image/png" href="{{ asset('images/favicon.ico') }}">
    <title>Nota de Despacho TCD - {{ $orden->numero_orden }}</title>

    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <style>
        @media print {
            .pagebreak { page-break-before: always; }
            @page {
                size: letter;
                margin: 0.25in;
            }
        }
        
        .delivery-note {
            font-family: Arial, sans-serif;
            max-width: 100%;
            width: 8.5in;
            margin: 0 auto;
            padding: 0.25in;
            box-sizing: border-box;
            font-size: 9px;
        }
        
        .delivery-header {
            text-align: center;
            margin-bottom: 0.1in;
            border-bottom: 1px solid #333;
            padding-bottom: 0.05in;
        }
        
        .delivery-title {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 0.02in;
        }
        
        .client-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.25in;
            margin-bottom: 0.25in;
        }
        
        .client-section {
            border: 1px solid #ddd;
            padding: 0.15in;
            border-radius: 5px;
        }
        
        .client-section h4 {
            margin: 0 0 0.1in 0;
            color: #333;
            font-size: 10px;
        }
        
        .client-field {
            margin-bottom: 0.05in;
        }
        
        .client-label {
            font-weight: bold;
            color: #555;
        }
        
        .products-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0.25in;
        }
        
        .products-table th {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            padding: 6px 4px;
            text-align: center;
            font-weight: bold;
            font-size: 9px;
        }
        
        .products-table td {
            border: 1px solid #ddd;
            padding: 4px 4px;
            text-align: center;
            font-size: 8px;
        }
        
        .products-table .product-description {
            text-align: left;
            max-width: 200px;
        }
        
        .products-table .quantity {
            text-align: center;
            font-weight: bold;
        }
        
        .totals-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75in;
            margin-bottom: 0.5in;
        }
        
        .totals-table {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid #333;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .totals-table th,
        .totals-table td {
            border: 1px solid #ddd;
            padding: 6px 8px;
            text-align: right;
            font-size: 9px;
        }
        
        .totals-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #333;
            border-bottom: 2px solid #333;
        }
        
        .totals-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .totals-table tr:hover {
            background-color: #f0f0f0;
        }
        
        .total-row {
            font-weight: bold;
            font-size: 12px;
            background-color: #e9ecef !important;
            border-top: 2px solid #333;
        }
        
        .total-row th,
        .total-row td {
            color: #333;
            font-weight: bold;
        }
        
        .signatures-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1in;
            margin-top: 1in;
        }
        
        .signature-box {
            border-top: 1px solid #333;
            padding-top: 10px;
            text-align: center;
        }
        
        .signature-label {
            font-weight: bold;
            margin-bottom: 15px;
            font-size: 9px;
        }
        
        .notes-section {
            margin-top: 0.5in;
            border: 1px solid #ddd;
            padding: 0.25in;
            border-radius: 5px;
        }
        
        .notes-title {
            font-weight: bold;
            margin-bottom: 6px;
            font-size: 9px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
        
        .status-completada {
            background-color: #28a745;
            color: white;
        }
        
        .status-despachada {
            background-color: #17a2b8;
            color: white;
        }
    </style>
</head>
<body>
    <div class="delivery-note">
        <div class="delivery-header">
            <div class="delivery-title">
                NOTA DE DESPACHO - TCD
                <span class="status-badge status-{{ $orden->estado }}">{{ strtoupper($orden->estado) }}</span>
            </div>
            <div style="font-size: 10px; margin-bottom: 0; display: flex; justify-content: space-between; align-items: center;">
                <span>N° {{ $orden->numero_orden }}</span>
                <span style="font-size: 8px; color: #666;">Fecha: {{ $orden->created_at->format('d/m/Y') }}</span>
            </div>
        </div>
        
        <div class="client-info">
            <div class="client-section">
                <h4>DATOS DE LA ORDEN</h4>
                <div class="client-field">
                    <span class="client-label">N° Orden:</span><br>
                    {{ $orden->numero_orden }}
                </div>
                <div class="client-field">
                    <span class="client-label">Chequeador:</span><br>
                    {{ $orden->chequeador->nombre ?? 'N/A' }}
                </div>
                <div class="client-field">
                    <span class="client-label">Fecha Creación:</span><br>
                    {{ $orden->created_at->format('d/m/Y H:i') }}
                </div>
            </div>
            
            <div class="client-section">
                <h4>DESTINO / CLIENTE</h4>
                @if($orden->sucursal_destino_codigo)
                    @if($sucursalDestino)
                    <div class="client-field">
                        <span class="client-label">Razón Social:</span><br>
                        {{ $sucursalDestino->razon_social ?? $orden->sucursal_destino_codigo }}
                    </div>
                    <div class="client-field">
                        <span class="client-label">RIF:</span><br>
                        {{ $sucursalDestino->rif ?? 'N/A' }}
                    </div>
                    <div class="client-field">
                        <span class="client-label">Dirección:</span><br>
                        {{ $sucursalDestino->direccion_fiscal ?? $sucursalDestino->direccion ?? 'N/A' }}
                    </div>
                    <div class="client-field">
                        <span class="client-label">Sucursal:</span><br>
                        {{ $sucursalDestino->nombre ?? $orden->sucursal_destino_codigo }} ({{ $orden->sucursal_destino_codigo }})
                    </div>
                    @else
                    <div class="client-field">
                        <span class="client-label">Sucursal Destino:</span><br>
                        {{ $orden->sucursal_destino_codigo }}
                    </div>
                    @endif
                @if($orden->pedido_central_numero)
                <div class="client-field">
                    <span class="client-label">Pedido Central:</span><br>
                    {{ $orden->pedido_central_numero }}
                </div>
                @endif
                @else
                <div class="client-field">
                    Despacho local / Cliente directo
                </div>
                @endif
                @if($pasilleros->count() > 0)
                <div class="client-field">
                    <span class="client-label">Pasilleros:</span><br>
                    {{ $pasilleros->pluck('nombre')->implode(', ') }}
                </div>
                @endif
            </div>
        </div>

        <table class="products-table">
            <thead>
                <tr>
                    <th style="width: 15%;">Código</th>
                    <th style="width: 40%;">Descripción del Producto</th>
                    <th style="width: 15%;">Cantidad</th>
                    <th style="width: 15%;">Ubicación</th>
                    <th style="width: 15%;">Procesado</th>
                </tr>
            </thead>
            <tbody>
                @php $totalItems = 0; $totalProcesado = 0; @endphp
                @foreach ($items as $item)
                    @php 
                        $totalItems += $item->cantidad;
                        $procesado = $asignaciones->where('tcd_orden_item_id', $item->id)->sum('cantidad_procesada');
                        $totalProcesado += $procesado;
                        $ubicaciones = $asignaciones->where('tcd_orden_item_id', $item->id)->pluck('warehouse_codigo')->filter()->unique()->implode(', ');
                    @endphp
                    <tr>
                        <td>{{ $item->inventario->codigo_barras ?? $item->inventario->codigo_proveedor ?? '-' }}</td>
                        <td class="product-description">{{ $item->inventario->descripcion ?? 'Producto no encontrado' }}</td>
                        <td class="quantity">{{ number_format($item->cantidad, 2) }}</td>
                        <td>{{ $ubicaciones ?: '-' }}</td>
                        <td class="quantity">{{ number_format($procesado, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals-section">
            <div class="notes-section">
                <div class="notes-title">Observaciones:</div>
                <div style="min-height: 80px; border: 1px solid #eee; padding: 10px; background-color: #f9f9f9;">
                    {{ $orden->observaciones ?? '' }}
                </div>
            </div>
            
            <table class="totals-table">
                <tr>
                    <th>Total Productos</th>
                    <td>{{ $items->count() }}</td>
                </tr>
                <tr>
                    <th>Unidades Solicitadas</th>
                    <td>{{ number_format($totalItems, 2) }}</td>
                </tr>
                <tr>
                    <th>Unidades Procesadas</th>
                    <td>{{ number_format($totalProcesado, 2) }}</td>
                </tr>
                @if($ticket)
                <tr>
                    <th>Código Ticket</th>
                    <td style="font-family: monospace; font-size: 8px;">{{ $ticket->codigo_barras }}</td>
                </tr>
                @endif
                <tr class="total-row">
                    <th>COMPLETADO</th>
                    <td>{{ $totalItems > 0 ? number_format(($totalProcesado / $totalItems) * 100, 1) : 0 }}%</td>
                </tr>
            </table>
        </div>

        <div class="signatures-section">
            <div class="signature-box">
                <div class="signature-label">Firma del Despachador</div>
            </div>
            <div class="signature-box">
                <div class="signature-label">Firma de Recibido</div>
            </div>
        </div>
    </div>
</body>
</html>
