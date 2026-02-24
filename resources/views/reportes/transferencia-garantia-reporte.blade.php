@php
    $orden = $orden ?? [];
    $sucursal = $sucursal ?? null;
    $items = $orden['items'] ?? [];
    $sucursalOrigen = $orden['sucursal_origen'] ?? $orden['sucursalOrigen'] ?? null;
    $sucursalDestino = $orden['sucursal_destino'] ?? $orden['sucursalDestino'] ?? null;
    $proveedor = $orden['proveedor'] ?? null;
    $fecha = isset($orden['created_at']) ? date('d/m/Y H:i', strtotime($orden['created_at'])) : '';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Orden Transferencia Garantía #{{ $orden['id'] ?? 'N/A' }}</title>
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; color: #333; margin: 0; padding: 12px; background: #f5f5f5; }
        @media print {
            body { background: white; padding: 0; }
            .no-print { display: none !important; }
            .reporte-container { box-shadow: none; border: none; }
        }
        @page { size: letter portrait; margin: 12mm; }
        .reporte-container { max-width: 215.9mm; min-height: 279.4mm; margin: 0 auto; background: white; padding: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        @media print { .reporte-container { max-width: 100%; min-height: auto; } }
        .no-print { margin-bottom: 12px; display: flex; gap: 10px; align-items: center; }
        .btn { padding: 8px 14px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; }
        .btn-print { background: #2563eb; color: white; }
        .header { margin-bottom: 14px; padding-bottom: 10px; border-bottom: 2px solid #333; }
        .header-empresa { font-size: 14px; font-weight: bold; margin: 0; }
        .header-rif { font-size: 10px; color: #555; margin: 2px 0 0 0; }
        .titulo { font-size: 16px; font-weight: bold; text-align: center; margin: 12px 0; }
        .subtitulo { font-size: 12px; text-align: center; color: #555; margin: 0 0 12px 0; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 20px; margin-bottom: 14px; font-size: 11px; }
        .info-label { font-weight: bold; color: #444; }
        .tabla { width: 100%; border-collapse: collapse; font-size: 10px; margin-top: 8px; }
        .tabla th, .tabla td { border: 1px solid #000; padding: 6px 8px; text-align: left; }
        .tabla th { background: #e5e5e5; font-weight: bold; }
        .tabla .text-right { text-align: right; }
        .tabla .text-center { text-align: center; }
        .observaciones { margin-top: 12px; padding: 8px; background: #f9f9f9; border: 1px solid #ddd; font-size: 10px; }
        .pie { margin-top: 16px; padding-top: 8px; border-top: 1px solid #ccc; font-size: 9px; color: #666; text-align: center; }
    </style>
</head>
<body>
    <div class="no-print">
        <button type="button" class="btn btn-print" onclick="window.print();">Imprimir</button>
    </div>
    <div class="reporte-container">
        <div class="header">
            <p class="header-empresa">{{ $sucursal->nombre_registro ?? 'Empresa' }}</p>
            <p class="header-rif">RIF: {{ $sucursal->rif ?? '' }} · {{ $sucursal->sucursal ?? '' }}</p>
        </div>

        <h1 class="titulo">Reporte – Orden de Transferencia de Garantía</h1>
        <p class="subtitulo">Resumen de lo enviado</p>

        <div class="info-grid">
            <div>
                <span class="info-label">N° Orden:</span> {{ $orden['id'] ?? 'N/A' }}
            </div>
            <div>
                <span class="info-label">Estado:</span> {{ $orden['estado'] ?? 'N/A' }}
            </div>
            <div>
                <span class="info-label">Fecha:</span> {{ $fecha }}
            </div>
            <div>
                <span class="info-label">Usuario creación:</span> {{ $orden['usuario_creacion_nombre'] ?? '—' }}
            </div>
            <div>
                <span class="info-label">Sucursal origen:</span> {{ $sucursalOrigen['nombre'] ?? $sucursalOrigen['codigo'] ?? $orden['sucursal_origen_id'] ?? '—' }}
            </div>
            <div>
                <span class="info-label">Sucursal destino:</span> {{ $sucursalDestino['nombre'] ?? $sucursalDestino['codigo'] ?? $orden['sucursal_destino_id'] ?? '—' }}
            </div>
            @if($proveedor && !empty($proveedor['descripcion']))
            <div style="grid-column: 1 / -1;">
                <span class="info-label">Proveedor:</span> {{ $proveedor['descripcion'] ?? $proveedor['rif'] ?? '—' }}
            </div>
            @endif
        </div>

        <table class="tabla">
            <thead>
                <tr>
                    <th style="width: 8%;">#</th>
                    <th style="width: 32%;">Producto / Código</th>
                    <th class="text-center" style="width: 14%;">Tipo origen</th>
                    <th class="text-center" style="width: 14%;">Tipo destino</th>
                    <th class="text-right" style="width: 10%;">Cantidad</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $idx => $it)
                @php
                    $prod = $it['producto'] ?? [];
                @endphp
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>
                        <strong>{{ $prod['descripcion'] ?? $it['producto_id'] ?? '—' }}</strong>
                        @if(!empty($prod['codigo_barras']) || !empty($prod['codigo_proveedor']))
                        <br><span style="font-size: 9px; color: #666;">{{ trim(($prod['codigo_barras'] ?? '') . ' ' . ($prod['codigo_proveedor'] ?? '')) }}</span>
                        @endif
                    </td>
                    <td class="text-center">{{ $it['tipo_inventario_origen'] ?? '—' }}</td>
                    <td class="text-center">{{ $it['tipo_inventario_destino'] ?? '—' }}</td>
                    <td class="text-right">{{ $it['cantidad'] ?? 0 }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        @if(!empty($orden['observaciones']))
        <div class="observaciones">
            <strong>Observaciones:</strong> {{ $orden['observaciones'] }}
        </div>
        @endif

        <div class="pie">
            Documento generado el {{ date('d/m/Y H:i') }} · Orden de Transferencia de Garantía #{{ $orden['id'] ?? '' }}
        </div>
    </div>
</body>
</html>
