<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cuadre Diario - Pedido #{{ $pedido->id }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: right; }
        th { background-color: #f2f2f2; text-align: center; }
        td.text-left { text-align: left; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .toolbar { margin-bottom: 20px; padding: 15px; background: #f0f0f0; border-radius: 6px; }
        h1 { margin-bottom: 5px; }
        .subtitulo { color: #666; margin-bottom: 15px; }
        .btn-export { padding: 6px 12px; background: #28a745; color: #fff; text-decoration: none; border-radius: 4px; font-size: 14px; display: inline-block; margin-right: 10px; }
        .btn-export:hover { background: #218838; color: #fff; }
        .btn-back { padding: 6px 12px; background: #6c757d; color: #fff; text-decoration: none; border-radius: 4px; font-size: 14px; display: inline-block; }
        .btn-back:hover { color: #fff; }
        .pedido-info { margin-bottom: 15px; padding: 10px; background: #e8f4fc; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>Pedido #{{ $pedido->id }}</h1>
    <p class="subtitulo">Nº factura: {{ $pedido->numero_factura ?? '—' }} | Máquina: {{ $pedido->maquina_fiscal ?? '—' }}</p>

    <div class="toolbar">
        @php
            $fechaPedido = date('Y-m-d', strtotime($pedido->fecha_factura ?? $pedido->created_at));
        @endphp
        <a href="{{ route('reportes.cuadre-diario.dia', ['fecha' => $fechaPedido]) }}{{ $pedido->maquina_fiscal ? '?maquina_fiscal=' . urlencode($pedido->maquina_fiscal) : '' }}" class="btn-back">&larr; Volver al día</a>
        <a href="{{ route('reportes.cuadre-diario.pedido.export', ['id' => $pedido->id]) }}" class="btn-export">Exportar CSV (ítems de este pedido)</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>Código barras</th>
                <th>Código proveedor</th>
                <th>Descripción</th>
                <th>Cantidad</th>
                <th>Precio unit.</th>
                <th>Tasa Bs</th>
                <th>Monto</th>
                <th>Monto Bs</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $item)
                <tr>
                    <td class="text-left">{{ $item->codigo_barras ?? '—' }}</td>
                    <td class="text-left">{{ $item->codigo_proveedor ?? '—' }}</td>
                    <td class="text-left">{{ $item->producto_descripcion ?? '—' }}</td>
                    <td>{{ $item->cantidad }}</td>
                    <td>{{ $item->precio_unitario !== null ? number_format($item->precio_unitario, 4) : '—' }}</td>
                    <td>{{ $item->tasa !== null ? number_format($item->tasa, 4) : '—' }}</td>
                    <td>{{ $item->monto !== null ? number_format($item->monto, 4) : '—' }}</td>
                    <td>{{ $item->monto_bs !== null ? number_format($item->monto_bs, 4) : '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">No hay ítems en este pedido.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
