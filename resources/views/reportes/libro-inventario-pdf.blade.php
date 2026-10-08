<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 8.5px; color: #111; }
        table { page-break-inside: auto; margin-bottom: 4px; }
        tr { page-break-inside: avoid; }
        h1 { font-size: 13px; margin: 0 0 2px; text-align: center; }
        .enc { text-align: center; margin: 0 0 8px; font-size: 9px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #999; padding: 2px 3px; text-align: right; }
        th { background: #e9e9e9; text-align: center; font-size: 8px; }
        td.l { text-align: left; }
        tr.total td { background: #e8f4fc; font-weight: bold; }
        .pie { margin-top: 8px; font-size: 8px; color: #444; }
    </style>
</head>
<body>
@php $fmt = fn($n, $d = 2) => number_format((float)$n, $d, ',', '.'); $t = $libro['totales']; @endphp
<h1>REGISTRO DE ENTRADAS Y SALIDAS DE INVENTARIO</h1>
<p class="enc">
    {{ $empresa->nombre_registro ?? '' }} · RIF {{ $empresa->rif ?? '' }}<br>
    {{ $empresa->sucursal ?? '' }}@if(!empty($empresa->direccion_sucursal)) · {{ $empresa->direccion_sucursal }}@endif<br>
    Período: {{ $libro['desde'] }} al {{ $libro['hasta'] }} · Entradas consideradas: {{ implode(', ', $libro['tipos']) }} · Método de valoración: costo promedio ponderado · Tasa de cierre: {{ $fmt($libro['tasa_cierre'], 4) }} Bs/USD
</p>
@foreach(array_chunk($productos, 60, true) as $bloque)
<table>
    <thead>
        <tr><th>Código</th><th>Descripción</th><th>Unid.</th><th>Exist. inicial</th><th>Entradas</th><th>Costo entradas USD</th><th>Salidas</th><th>Costo salidas USD</th><th>Devol.</th><th>Existencia final</th><th>Costo prom. USD</th><th>Valor final USD</th><th>Valor final Bs</th></tr>
    </thead>
    <tbody>
    @foreach($bloque as $p)
        <tr>
            <td class="l">{{ $p['codigo'] }}</td><td class="l">{{ $p['descripcion'] }}</td><td>{{ $p['unidad'] }}</td>
            <td>{{ $fmt($p['ini_qty'], 2) }}</td><td>{{ $fmt($p['ent_qty'], 2) }}</td><td>{{ $fmt($p['ent_valor']) }}</td><td>{{ $fmt($p['sal_qty'], 2) }}</td><td>{{ $fmt($p['sal_valor']) }}</td><td>{{ $fmt($p['dev_qty'], 2) }}</td>
            <td>{{ $fmt($p['fin_qty'], 2) }}</td><td>{{ $fmt($p['prom'], 4) }}</td><td>{{ $fmt($p['fin_valor']) }}</td><td>{{ $fmt($p['fin_valor_bs']) }}</td>
        </tr>
    @endforeach
    @if(!$loop->last)
    </tbody>
</table>
    @endif
@endforeach
        <tr class="total"><td class="l" colspan="3">TOTALES ({{ $fmt($t['productos'], 0) }} productos)</td><td></td><td>{{ $fmt($t['ent_qty'], 2) }}</td><td>{{ $fmt($t['ent_valor']) }}</td><td>{{ $fmt($t['sal_qty'], 2) }}</td><td>{{ $fmt($t['sal_valor']) }}</td><td>{{ $fmt($t['dev_qty'], 2) }}</td><td>{{ $fmt($t['fin_qty'], 2) }}</td><td></td><td>{{ $fmt($t['fin_valor']) }}</td><td>{{ $fmt($t['fin_valor_bs']) }}</td></tr>
    </tbody>
</table>
<p class="pie">Entradas: facturas fiscales de compra recibidas en la sucursal (N° de factura del proveedor). Salidas: facturas de venta (número de factura y máquina fiscal). Las salidas se valoran al costo promedio ponderado de las entradas de cada producto; las devoluciones reingresan al mismo costo. Existencia = entradas - salidas acumuladas desde el primer documento. Los productos con existencia negativa indican ventas sin factura de compra previa registrada. Generado el {{ now()->format('d/m/Y H:i') }}.</p>
</body>
</html>
