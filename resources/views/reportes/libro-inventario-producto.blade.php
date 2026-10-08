<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kardex {{ $producto['codigo'] }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { margin-bottom: 4px; font-size: 20px; }
        .subtitulo { color: #666; margin: 0 0 14px; }
        .btn { padding: 6px 12px; border-radius: 4px; border: 1px solid #ccc; background: #fff; text-decoration: none; color: #222; font-size: 14px; }
        .btn-export { background: #28a745; color: #fff; border-color: #28a745; }
        table { border-collapse: collapse; width: 100%; font-size: 13px; margin-top: 12px; }
        th, td { border: 1px solid #ddd; padding: 5px 7px; text-align: right; }
        th { background: #f2f2f2; text-align: center; }
        td.l { text-align: left; }
        tr.ini { background: #e8f4fc; font-weight: bold; }
        tr.ENTRADA td { background: #f1fbf3; }
        tr.DEVOLUCION td { background: #fff8e6; }
        .neg { color: #c00; font-weight: bold; }
    </style>
</head>
<body>
@php $fmt = fn($n, $d = 2) => number_format((float)$n, $d, ',', '.'); $qs = request()->only(['desde','hasta','tipos']); @endphp
<h1>Kardex: {{ $producto['descripcion'] }}</h1>
<p class="subtitulo">Código {{ $producto['codigo'] ?: '—' }} · Cód. proveedor {{ $producto['codigo_proveedor'] ?: '—' }} · ID {{ $producto['id'] }} · {{ $empresa->sucursal ?? '' }} · Período {{ $libro['desde'] }} → {{ $libro['hasta'] }} · Entradas: {{ implode(', ', $libro['tipos']) }}</p>
<p>
    <a class="btn" href="{{ route('reportes.libro-inventario', $qs) }}">&larr; Volver al libro</a>
    <a class="btn btn-export" href="{{ route('reportes.libro-inventario.movimientos.export', $qs + ['producto' => $producto['id']]) }}">Exportar CSV</a>
</p>
<p>
    Existencia inicial <strong>{{ $fmt($producto['ini_qty'], 2) }}</strong> ($ {{ $fmt($producto['ini_valor']) }}) ·
    Entradas <strong>{{ $fmt($producto['ent_qty'], 2) }}</strong> ($ {{ $fmt($producto['ent_valor']) }}) ·
    Salidas <strong>{{ $fmt($producto['sal_qty'], 2) }}</strong> ($ {{ $fmt($producto['sal_valor']) }} al costo; venta $ {{ $fmt($producto['venta_usd']) }}) ·
    Devoluciones <strong>{{ $fmt($producto['dev_qty'], 2) }}</strong> ·
    Existencia final <strong class="{{ $producto['fin_qty'] < -0.00001 ? 'neg' : '' }}">{{ $fmt($producto['fin_qty'], 2) }}</strong> · costo promedio $ {{ $fmt($producto['prom'], 4) }} · valor $ {{ $fmt($producto['fin_valor']) }} / Bs {{ $fmt($producto['fin_valor_bs']) }}
    @if($producto['negativo'])<span class="neg"> · la existencia fue negativa en algún momento (salidas sin entrada previa)</span>@endif
</p>
<table>
    <thead><tr><th>Fecha</th><th>Movimiento</th><th>Documento</th><th>Proveedor / origen</th><th>Entrada</th><th>Salida</th><th>Costo unit. USD</th><th>Total USD</th><th>Tasa</th><th>Total Bs</th><th>Saldo cant.</th><th>Costo prom.</th><th>Saldo valor USD</th></tr></thead>
    <tbody>
        <tr class="ini"><td class="l">{{ $libro['desde'] }}</td><td class="l" colspan="3">Existencia inicial (acumulado de documentos anteriores)</td><td></td><td></td><td></td><td></td><td></td><td></td><td>{{ $fmt($producto['ini_qty'], 2) }}</td><td>{{ $producto['ini_qty'] > 0 ? $fmt($producto['ini_valor'] / $producto['ini_qty'], 4) : '' }}</td><td>{{ $fmt($producto['ini_valor']) }}</td></tr>
        @foreach($libro['movimientos'] as $m)
            <tr class="{{ $m['tipo'] }}">
                <td class="l">{{ $m['fecha'] }}</td>
                <td class="l">{{ $m['tipo'] }} ({{ $m['subtipo'] }})</td>
                <td class="l">{{ $m['documento'] }}</td>
                <td class="l">{{ $m['tercero'] }}</td>
                <td>{{ $m['entrada'] ? $fmt($m['entrada'], 2) : '' }}</td>
                <td>{{ $m['salida'] ? $fmt($m['salida'], 2) : '' }}</td>
                <td>{{ $fmt($m['costo_unit'], 4) }}</td>
                <td>{{ $fmt($m['total_usd']) }}</td>
                <td>{{ $fmt($m['tasa'], 4) }}</td>
                <td>{{ $fmt($m['total_bs']) }}</td>
                <td class="{{ $m['saldo_qty'] < -0.00001 ? 'neg' : '' }}">{{ $fmt($m['saldo_qty'], 2) }}</td>
                <td>{{ $fmt($m['saldo_prom'], 4) }}</td>
                <td>{{ $fmt($m['saldo_valor']) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
</body>
</html>
