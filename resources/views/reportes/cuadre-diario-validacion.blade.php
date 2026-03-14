<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validación del reporte de ventas</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: right; }
        th { background-color: #f2f2f2; text-align: center; }
        td.fecha, td.text-left { text-align: left; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .ok { color: #28a745; font-weight: bold; }
        .fail { color: #dc3545; font-weight: bold; }
        .na { color: #6c757d; }
        .filtro { margin-bottom: 20px; padding: 15px; background: #e8f4fc; border-radius: 6px; }
        .filtro label { margin-right: 8px; }
        .filtro input[type="date"], .filtro input[type="text"] { margin-right: 15px; }
        .filtro button { padding: 6px 14px; cursor: pointer; }
        h1 { margin-bottom: 10px; }
        .subtitulo { color: #666; margin-bottom: 20px; }
        .leyenda { margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 4px; font-size: 13px; }
    </style>
</head>
<body>
    <h1>Validación del reporte de ventas</h1>
    <p class="subtitulo">Estado de las ventas por día: monto, rango de facturas y comprobaciones.</p>

    <form method="get" action="{{ route('reportes.cuadre-diario.validacion') }}" class="filtro">
        <label for="fecha_desde">Desde:</label>
        <input type="date" id="fecha_desde" name="fecha_desde" value="{{ $fecha_desde ?? '' }}">
        <label for="fecha_hasta">Hasta:</label>
        <input type="date" id="fecha_hasta" name="fecha_hasta" value="{{ $fecha_hasta ?? '' }}">
        <label for="csv">CSV (ruta opcional):</label>
        <input type="text" id="csv" name="csv" value="{{ $csv_path ?? '' }}" placeholder="Ruta al CSV de indicaciones" style="min-width: 280px;">
        <button type="submit">Filtrar</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Máquina fiscal</th>
                <th>Monto Bs</th>
                <th>Rango factura (inicio – fin)</th>
                <th>Cant.</th>
                <th>Coincide con CSV</th>
                <th>Pedidos naturales</th>
            </tr>
        </thead>
        <tbody>
            @forelse($filas as $r)
                <tr>
                    <td class="fecha">{{ $r->fecha }}</td>
                    <td class="text-left">{{ $r->maquina_fiscal }}</td>
                    <td>{{ number_format($r->monto_bs, 4) }}</td>
                    <td>{{ $r->factura_inicio }} – {{ $r->factura_fin }}</td>
                    <td>{{ $r->cantidad }}</td>
                    <td>
                        @if($r->check_csv === null)
                            <span class="na">—</span>
                        @elseif($r->check_csv)
                            <span class="ok">✓ Sí</span>
                        @else
                            <span class="fail">✗ No</span>
                        @endif
                    </td>
                    <td>
                        @if($r->check_natural)
                            <span class="ok">✓ Sí</span>
                        @else
                            <span class="fail">✗ No</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">No hay ventas en el rango indicado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="leyenda">
        <strong>Leyenda:</strong><br>
        <strong>Coincide con CSV:</strong> Si se indicó ruta de CSV, comprueba que el monto, rango de facturas y cantidad del resultado final coincidan con las indicaciones originales.<br>
        <strong>Pedidos naturales:</strong> Comprueba que ningún pedido del día concentre más del 50% del monto total (evita “muchos pedidos en cero y un solo ajuste”).
    </div>
</body>
</html>
