<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planilla #{{ $planilla['id'] ?? '' }} - Pendiente</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; color: #333; margin: 0; padding: 12px; background: #f5f5f5; }
        @media print {
            body { background: white; padding: 0; }
            .no-print { display: none !important; }
            .reporte-container { box-shadow: none; border: none; }
        }
        @page { size: letter portrait; margin: 12mm; }
        .reporte-container { max-width: 215.9mm; margin: 0 auto; background: white; padding: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        @media print { .reporte-container { max-width: 100%; } }
        .no-print { margin-bottom: 12px; display: flex; gap: 10px; align-items: center; }
        .btn { padding: 8px 14px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; }
        .btn-print { background: #2563eb; color: white; }
        .btn-pdf { background: #dc2626; color: white; }
        .header { margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px solid #ccc; }
        .header-title { font-size: 16px; font-weight: bold; margin: 0; }
        .header-info { font-size: 10px; color: #666; margin-top: 2px; }
        .tabla { width: 100%; border-collapse: collapse; font-size: 10px; }
        .tabla th, .tabla td { border: 1px solid #000; padding: 4px 6px; }
        .tabla th { background: #e0e0e0; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .blanco { background: #fff; min-width: 50px; }
        .seccion { margin-top: 12px; }
        .seccion-tit { font-size: 11px; font-weight: bold; margin-bottom: 4px; }
    </style>
</head>
<body>
    <div class="no-print">
        <button type="button" class="btn btn-print" onclick="window.print();">Imprimir</button>
        <button type="button" class="btn btn-pdf" onclick="window.print();">Guardar como PDF</button>
    </div>
    <div class="reporte-container">
        <div class="header">
            <p class="header-title">Planilla pendiente #{{ $planilla['id'] ?? '' }}</p>
            <p class="header-info">{{ isset($planilla['sucursal']) ? ($planilla['sucursal']['nombre'] ?? '') . ' · ' . ($planilla['sucursal']['codigo'] ?? '') : '' }} · {{ isset($planilla['fecha_creacion']) ? date('d/m/Y', strtotime($planilla['fecha_creacion'])) : '' }}</p>
        </div>

        <table class="tabla">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Producto</th>
                    <th>Cód. barras</th>
                    <th>Cód. proveedor</th>
                    <th class="text-right">Cant. sistema</th>
                    <th class="text-center blanco">Cant. física</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tareas as $tarea)
                @php $prod = $tarea['producto'] ?? []; @endphp
                <tr>
                    <td>{{ $tarea['id'] ?? '' }}</td>
                    <td>{{ $prod['descripcion'] ?? '—' }}</td>
                    <td>{{ $prod['codigo_barras'] ?? '—' }}</td>
                    <td>{{ $prod['codigo_proveedor'] ?? '—' }}</td>
                    <td class="text-right">{{ number_format($tarea['cantidad_sistema'] ?? 0) }}</td>
                    <td class="blanco"></td>
                </tr>
                @endforeach
            </tbody>
        </table>

        @if(isset($pendientesLocales) && count($pendientesLocales) > 0)
        <div class="seccion">
            <p class="seccion-tit">Solo en local (pendientes envío)</p>
            <table class="tabla">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Producto</th>
                        <th>Cód. barras</th>
                        <th>Cód. proveedor</th>
                        <th class="text-right">Cant. sistema</th>
                        <th class="text-center blanco">Cant. física</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pendientesLocales as $idx => $p)
                    @php $prod = $p['producto'] ?? []; @endphp
                    <tr>
                        <td>{{ $idx + 1 }}</td>
                        <td>{{ $prod['descripcion'] ?? '—' }}</td>
                        <td>{{ $prod['codigo_barras'] ?? '—' }}</td>
                        <td>{{ $prod['codigo_proveedor'] ?? '—' }}</td>
                        <td class="text-right">{{ isset($p['cantidad_sistema']) ? number_format($p['cantidad_sistema']) : ($prod['cantidad'] ?? '') }}</td>
                        <td class="blanco"></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</body>
</html>
