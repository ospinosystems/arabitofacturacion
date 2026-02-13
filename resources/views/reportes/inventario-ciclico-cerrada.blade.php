<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Planilla #{{ $planilla['id'] ?? '' }} - Cerrada</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; line-height: 1.35; color: #333; margin: 0; padding: 12px; background: #f5f5f5; }
        @media print {
            body { background: white; padding: 0; }
            .no-print { display: none !important; }
            .reporte-container { box-shadow: none; border: none; }
        }
        .reporte-container { max-width: 210mm; margin: 0 auto; background: white; padding: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        @media print { .reporte-container { max-width: 100%; } }
        .no-print { margin-bottom: 12px; display: flex; gap: 10px; flex-wrap: wrap; }
        .btn { padding: 10px 18px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; text-decoration: none; display: inline-block; }
        .btn-print { background: #2563eb; color: white; }
        .btn-pdf { background: #dc2626; color: white; }
        .btn:hover { opacity: 0.9; }
        .header { text-align: center; margin-bottom: 16px; border-bottom: 2px solid #333; padding-bottom: 12px; }
        .header h1 { margin: 0; font-size: 18px; color: #1e293b; }
        .header h2 { margin: 4px 0; font-size: 14px; color: #64748b; }
        .section-title { background: #334155; color: white; padding: 6px 10px; margin: 12px 0 6px 0; font-size: 12px; font-weight: bold; }
        .info-grid { display: table; width: 100%; margin-bottom: 10px; }
        .info-row { display: table-row; }
        .info-cell { display: table-cell; padding: 5px 8px; border: 1px solid #e2e8f0; background: #f8fafc; font-weight: bold; width: 22%; }
        .info-value { display: table-cell; padding: 5px 8px; border: 1px solid #e2e8f0; width: 28%; }
        .stats-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 11px; }
        .stats-table th, .stats-table td { padding: 5px 8px; border: 1px solid #e2e8f0; text-align: left; }
        .stats-table th { background: #f1f5f9; font-weight: bold; }
        .stats-table .text-right { text-align: right; }
        .tabla { width: 100%; border-collapse: collapse; font-size: 10px; }
        .tabla th, .tabla td { padding: 5px 6px; border: 1px solid #e2e8f0; }
        .tabla th { background: #334155; color: white; font-weight: bold; }
        .tabla tr:nth-child(even) { background: #f8fafc; }
        .diff-pos { color: #15803d; font-weight: bold; }
        .diff-neg { color: #b91c1c; font-weight: bold; }
        .footer { margin-top: 16px; text-align: center; font-size: 9px; color: #64748b; border-top: 1px solid #e2e8f0; padding-top: 8px; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <div class="no-print">
        <button type="button" class="btn btn-print" onclick="window.print();">Imprimir</button>
        <button type="button" class="btn btn-pdf" onclick="window.print();">Guardar como PDF</button>
        <p style="margin:8px 0 0 0; color:#64748b; font-size:12px;">Al imprimir, elija &quot;Guardar como PDF&quot; para guardar en PDF.</p>
    </div>
    <div class="reporte-container">
        <div class="header">
            <h1>REPORTE DE INVENTARIO CÍCLICO - PLANILLA CERRADA</h1>
            <h2>Planilla #{{ $planilla['id'] ?? 'N/A' }}</h2>
            <p>Generado el {{ date('d/m/Y H:i:s') }}</p>
        </div>

        <div class="section-title">INFORMACIÓN DE LA PLANILLA</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-cell">Planilla #</div>
                <div class="info-value">{{ $planilla['id'] ?? 'N/A' }}</div>
                <div class="info-cell">Sucursal</div>
                <div class="info-value">{{ isset($planilla['sucursal']) ? ($planilla['sucursal']['nombre'] ?? '') . ' (' . ($planilla['sucursal']['codigo'] ?? '') . ')' : 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-cell">Fecha Creación</div>
                <div class="info-value">{{ isset($planilla['fecha_creacion']) ? date('d/m/Y H:i', strtotime($planilla['fecha_creacion'])) : 'N/A' }}</div>
                <div class="info-cell">Fecha Cierre</div>
                <div class="info-value">{{ isset($planilla['fecha_cierre']) ? date('d/m/Y H:i', strtotime($planilla['fecha_cierre'])) : 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-cell">Creada por</div>
                <div class="info-value">{{ $planilla['usuarioCreador']['usuario'] ?? $planilla['usuarioCreador']['nombre'] ?? $planilla['usuario_creador'] ?? ($planilla['usuarioCreador']['name'] ?? '—') }}</div>
                <div class="info-cell">Estado</div>
                <div class="info-value">{{ $planilla['estatus'] ?? '—' }}</div>
            </div>
        </div>

        <div class="section-title">ESTADÍSTICAS</div>
        <table class="stats-table">
            <tr>
                <th>Total items</th>
                <td class="text-right">{{ $estadisticas['total_items'] ?? 0 }}</td>
                <th>Items con diferencia</th>
                <td class="text-right">{{ $estadisticas['items_con_diferencia'] ?? 0 }}</td>
            </tr>
            <tr>
                <th>Cant. sistema</th>
                <td class="text-right">{{ number_format($estadisticas['total_cantidad_sistema'] ?? 0) }}</td>
                <th>Cant. física</th>
                <td class="text-right">{{ number_format($estadisticas['total_cantidad_fisica'] ?? 0) }}</td>
            </tr>
            <tr>
                <th>Diferencia cantidad</th>
                <td class="text-right">{{ number_format($estadisticas['total_diferencia_cantidad'] ?? 0) }}</td>
                <th>Diferencia valor</th>
                <td class="text-right">${{ number_format($estadisticas['total_diferencia_valor'] ?? 0, 2) }}</td>
            </tr>
        </table>

        <div class="section-title">DETALLE DE PRODUCTOS</div>
        <table class="tabla">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Producto</th>
                    <th>Código barras</th>
                    <th>Código proveedor</th>
                    <th>Cant. sistema</th>
                    <th>Cant. física</th>
                    <th>Diferencia</th>
                    <th>Precio</th>
                    <th>Valor dif.</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tareas as $tarea)
                @php
                    $dif = ($tarea['cantidad_fisica'] ?? 0) - ($tarea['cantidad_sistema'] ?? 0);
                    $prod = $tarea['producto'] ?? [];
                @endphp
                <tr>
                    <td>{{ $tarea['id'] ?? '' }}</td>
                    <td>{{ $prod['descripcion'] ?? '—' }}</td>
                    <td>{{ $prod['codigo_barras'] ?? '—' }}</td>
                    <td>{{ $prod['codigo_proveedor'] ?? '—' }}</td>
                    <td class="text-right">{{ number_format($tarea['cantidad_sistema'] ?? 0) }}</td>
                    <td class="text-right">{{ number_format($tarea['cantidad_fisica'] ?? 0) }}</td>
                    <td class="text-right {{ $dif > 0 ? 'diff-pos' : ($dif < 0 ? 'diff-neg' : '') }}">{{ $dif > 0 ? '+' : '' }}{{ number_format($dif) }}</td>
                    <td class="text-right">${{ number_format($prod['precio'] ?? 0, 2) }}</td>
                    <td class="text-right {{ ($tarea['diferencia_valor'] ?? 0) != 0 ? (($tarea['diferencia_valor'] ?? 0) > 0 ? 'diff-pos' : 'diff-neg') : '' }}">${{ number_format($tarea['diferencia_valor'] ?? 0, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="footer">Reporte de inventario cíclico - Planilla cerrada</div>
    </div>
</body>
</html>
