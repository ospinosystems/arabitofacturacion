<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Libro de inventario</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { margin-bottom: 4px; }
        .subtitulo { color: #666; margin: 0 0 16px; }
        .filtro { margin-bottom: 14px; padding: 12px 15px; background: #e8f4fc; border-radius: 6px; }
        .filtro label { margin-right: 10px; white-space: nowrap; }
        .filtro input[type="date"], .filtro input[type="text"] { margin-right: 10px; }
        .filtro button, .btn { padding: 6px 12px; cursor: pointer; border-radius: 4px; border: 1px solid #ccc; background: #fff; text-decoration: none; color: #222; font-size: 14px; }
        .btn-export { background: #28a745; color: #fff; border-color: #28a745; }
        .btn-export:hover { background: #218838; color: #fff; }
        .totales { display: flex; flex-wrap: wrap; gap: 10px; margin: 10px 0 14px; }
        .tot { background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 8px 12px; min-width: 150px; }
        .tot .l { font-size: 11px; color: #777; text-transform: uppercase; }
        .tot .v { font-size: 17px; font-weight: bold; }
        table { border-collapse: collapse; width: 100%; font-size: 13px; }
        th, td { border: 1px solid #ddd; padding: 5px 7px; text-align: right; }
        th { background: #f2f2f2; text-align: center; }
        td.l { text-align: left; }
        tr:nth-child(even) { background: #fafafa; }
        tr.total-row { background: #e8f4fc; font-weight: bold; }
        .neg { color: #c00; font-weight: bold; }
        .aviso { background: #fff3cd; border: 1px solid #ffeeba; padding: 10px; border-radius: 6px; margin-bottom: 12px; }
        .pag a { margin: 0 4px; }
    </style>
</head>
<body>
@php $fmt = fn($n, $d = 2) => number_format((float)$n, $d, ',', '.'); $qs = request()->only(['desde','hasta','tipos','q','negativos','con_movimientos']); @endphp
<h1>Libro de entradas y salidas de inventario</h1>
<p class="subtitulo">{{ $empresa->nombre_registro ?? '' }} · RIF {{ $empresa->rif ?? '' }} · {{ $empresa->sucursal ?? '' }} · Período {{ $libro['desde'] }} → {{ $libro['hasta'] }} · Entradas: {{ implode(', ', $libro['tipos']) }} · Costo promedio ponderado (USD); Bs a la tasa de cada documento, existencia final a la tasa de cierre {{ $fmt($libro['tasa_cierre'], 4) }}</p>

@if(!$hayEntradas)
    <div class="aviso">Aún no hay entradas importadas. Ejecute <code>php artisan inventario:importar-entradas --sucursal-central=&lt;id&gt; --central-env=&lt;.env de central&gt;</code>.</div>
@endif

<form method="get" class="filtro">
    <label>Desde: <input type="date" name="desde" value="{{ $desde }}" min="{{ $min }}" max="{{ $max }}"></label>
    <label>Hasta: <input type="date" name="hasta" value="{{ $hasta }}" min="{{ $min }}" max="{{ $max }}"></label>
    @foreach(['FACTURA' => 'Facturas fiscales', 'NOTA' => 'Notas (sin factura)', 'TRANSFERENCIA' => 'Traslados'] as $t => $et)
        <label><input type="checkbox" name="tipos[]" value="{{ $t }}" {{ in_array($t, $tipos) ? 'checked' : '' }}> {{ $et }}</label>
    @endforeach
    <label>Buscar: <input type="text" name="q" value="{{ $q }}" placeholder="código o descripción"></label>
    <label><input type="checkbox" name="negativos" value="1" {{ $negativos ? 'checked' : '' }}> solo existencia negativa</label>
    <label><input type="checkbox" name="con_movimientos" value="1" {{ $con_movimientos ? 'checked' : '' }}> solo con movimientos en el período</label>
    <button type="submit">Aplicar</button>
    <a href="{{ route('reportes.libro-inventario') }}" class="btn">Quitar filtros</a>
</form>

<p>
    <a class="btn btn-export" href="{{ route('reportes.libro-inventario.export', $qs) }}">Exportar resumen CSV</a>
    <a class="btn btn-export" href="{{ route('reportes.libro-inventario.movimientos.export', $qs) }}">Exportar movimientos CSV</a>
    <a class="btn btn-export" href="{{ route('reportes.libro-inventario.pdf', $qs) }}">Libro en PDF</a>
    <a class="btn" href="{{ route('reportes.libro-inventario.entradas') }}">Ver entradas (facturas de compra)</a>
    <a class="btn" href="{{ route('reportes.cuadre-diario') }}">Ver salidas (facturas de venta)</a>
</p>

@php $t = $libro['totales']; @endphp
<div class="totales">
    <div class="tot"><div class="l">Productos</div><div class="v">{{ $fmt($t['productos'], 0) }}</div></div>
    <div class="tot"><div class="l">Existencia inicial (unid.)</div><div class="v">{{ $fmt($t['ini_qty'], 2) }}</div></div>
    <div class="tot"><div class="l">Entradas (unid.)</div><div class="v">{{ $fmt($t['ent_qty'], 2) }}</div></div>
    <div class="tot"><div class="l">Entradas (USD costo)</div><div class="v">{{ $fmt($t['ent_valor']) }}</div></div>
    <div class="tot"><div class="l">Salidas (unid.)</div><div class="v">{{ $fmt($t['sal_qty'], 2) }}</div></div>
    <div class="tot"><div class="l">Salidas (USD costo)</div><div class="v">{{ $fmt($t['sal_valor']) }}</div></div>
    <div class="tot"><div class="l">Devoluciones (unid., reingresan)</div><div class="v">{{ $fmt($t['dev_qty'], 2) }}</div></div>
    <div class="tot"><div class="l">Existencia final (unid.)</div><div class="v">{{ $fmt($t['fin_qty'], 2) }}</div></div>
    <div class="tot"><div class="l">Valor inventario final</div><div class="v">$ {{ $fmt($t['fin_valor']) }} · Bs {{ $fmt($t['fin_valor_bs']) }}</div></div>
    <div class="tot"><div class="l">Ventas del período (USD)</div><div class="v">{{ $fmt($t['venta_usd']) }}</div></div>
    <div class="tot"><div class="l">Productos con existencia negativa</div><div class="v {{ $t['negativos'] ? 'neg' : '' }}">{{ $fmt($t['negativos'], 0) }}</div></div>
    @if($t['sin_ficha'])<div class="tot"><div class="l">Sin ficha local</div><div class="v neg">{{ $fmt($t['sin_ficha'], 0) }}</div></div>@endif
</div>
<p class="subtitulo">Existencia final = existencia inicial + entradas &minus; salidas + devoluciones. La existencia inicial es la acumulada por los documentos anteriores a la fecha «Desde» (el libro parte del primer documento registrado, sin stock histórico).</p>

<p>{{ $fmt($totalFilas, 0) }} producto(s) · página {{ $pagina }} de {{ max(1, (int) ceil($totalFilas / $porPagina)) }}
    @if($totalFilas > $porPagina)
        <span class="pag">
            @if($pagina > 1)<a href="{{ route('reportes.libro-inventario', $qs + ['pagina' => $pagina - 1]) }}">&larr; anterior</a>@endif
            @if($pagina * $porPagina < $totalFilas)<a href="{{ route('reportes.libro-inventario', $qs + ['pagina' => $pagina + 1]) }}">siguiente &rarr;</a>@endif
        </span>
    @endif
</p>

<table>
    <thead>
        <tr>
            <th>Código</th><th>Descripción</th><th>Unid.</th>
            <th>Exist. inicial</th><th>Entradas</th><th>Costo entradas USD</th><th>Salidas</th><th>Costo salidas USD</th><th>Devol.</th>
            <th>Existencia</th><th>Costo prom. USD</th><th>Valor USD</th><th>Valor Bs</th><th></th>
        </tr>
    </thead>
    <tbody>
    @forelse($productos as $p)
        <tr>
            <td class="l">{{ $p['codigo'] }}</td>
            <td class="l">{{ $p['descripcion'] }}{!! $p['sin_ficha'] ? ' <span class="neg">(sin ficha local)</span>' : '' !!}</td>
            <td>{{ $p['unidad'] }}</td>
            <td>{{ $fmt($p['ini_qty'], 2) }}</td>
            <td>{{ $fmt($p['ent_qty'], 2) }}</td>
            <td>{{ $fmt($p['ent_valor']) }}</td>
            <td>{{ $fmt($p['sal_qty'], 2) }}</td>
            <td>{{ $fmt($p['sal_valor']) }}</td>
            <td>{{ $fmt($p['dev_qty'], 2) }}</td>
            <td class="{{ $p['fin_qty'] < -0.00001 ? 'neg' : '' }}">{{ $fmt($p['fin_qty'], 2) }}</td>
            <td>{{ $fmt($p['prom'], 4) }}</td>
            <td>{{ $fmt($p['fin_valor']) }}</td>
            <td>{{ $fmt($p['fin_valor_bs']) }}</td>
            <td class="l"><a href="{{ route('reportes.libro-inventario.producto', ['id' => $p['id']] + $qs) }}">Kardex</a></td>
        </tr>
    @empty
        <tr><td colspan="14" class="l">No hay productos con movimientos para estos filtros.</td></tr>
    @endforelse
    </tbody>
    @if($totalFilas)
    <tfoot>
        <tr class="total-row">
            <td class="l" colspan="3">Totales del período (todos los productos)</td>
            <td>{{ $fmt($t['ini_qty'], 2) }}</td><td>{{ $fmt($t['ent_qty'], 2) }}</td><td>{{ $fmt($t['ent_valor']) }}</td><td>{{ $fmt($t['sal_qty'], 2) }}</td><td>{{ $fmt($t['sal_valor']) }}</td><td>{{ $fmt($t['dev_qty'], 2) }}</td>
            <td>{{ $fmt($t['fin_qty'], 2) }}</td><td></td><td>{{ $fmt($t['fin_valor']) }}</td><td>{{ $fmt($t['fin_valor_bs']) }}</td><td></td>
        </tr>
    </tfoot>
    @endif
</table>
</body>
</html>
