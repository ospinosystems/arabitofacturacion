<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de ventas - Detalle {{ $fecha }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: right; }
        th { background-color: #f2f2f2; text-align: center; }
        td.text-left, td.fecha { text-align: left; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .toolbar { margin-bottom: 20px; padding: 15px; background: #f0f0f0; border-radius: 6px; }
        h1 { margin-bottom: 5px; }
        .subtitulo { color: #666; margin-bottom: 15px; }
        .btn-export { padding: 6px 12px; background: #28a745; color: #fff; text-decoration: none; border-radius: 4px; font-size: 14px; display: inline-block; margin-right: 10px; }
        .btn-export:hover { background: #218838; color: #fff; }
        .btn-back { padding: 6px 12px; background: #6c757d; color: #fff; text-decoration: none; border-radius: 4px; font-size: 14px; display: inline-block; }
        .btn-back:hover { color: #fff; }
    </style>
</head>
<body>
    <h1>Detalle del día {{ $fecha }}</h1>
    <p class="subtitulo">Ventas de este día
        @if($maquina_fiscal)
            (máquina {{ $maquina_fiscal }})
        @endif
        . Clic en un pedido para ver sus ítems.</p>

    <div class="toolbar">
        <a href="{{ route('reportes.cuadre-diario') }}?fecha_desde={{ $fecha }}&fecha_hasta={{ $fecha }}" class="btn-back">&larr; Volver al resumen</a>
        <a href="{{ route('reportes.cuadre-diario.dia.export', ['fecha' => $fecha]) }}{{ $maquina_fiscal ? '?maquina_fiscal=' . urlencode($maquina_fiscal) : '' }}" class="btn-export">Exportar CSV (pedidos de este día)</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>Nº factura</th>
                <th>Máquina fiscal</th>
                <th>Tasa Bs</th>
                <th>Monto USD</th>
                <th>Monto Bs</th>
                <th>Cant. ítems</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse($pedidos as $row)
                <tr>
                    <td class="text-left">{{ $row->pedido->numero_factura ?? '—' }}</td>
                    <td class="text-left">{{ $row->pedido->maquina_fiscal ?? '—' }}</td>
                    <td>{{ isset($row->tasa_bs) ? number_format($row->tasa_bs, 4) : '—' }}</td>
                    <td>{{ number_format($row->monto_usd ?? 0, 2) }}</td>
                    <td>{{ number_format($row->monto_bs, 2) }}</td>
                    <td>{{ $row->cant_items }}</td>
                    <td class="text-left">
                        <a href="{{ route('reportes.cuadre-diario.pedido', ['id' => $row->pedido->id]) }}">Ver ítems</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">No hay ventas para esta fecha.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
