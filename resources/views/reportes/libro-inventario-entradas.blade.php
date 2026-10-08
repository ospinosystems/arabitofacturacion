<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entradas de inventario</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { margin-bottom: 4px; font-size: 20px; }
        .subtitulo { color: #666; margin: 0 0 14px; }
        .filtro { margin-bottom: 14px; padding: 12px 15px; background: #e8f4fc; border-radius: 6px; }
        .filtro label { margin-right: 10px; }
        .btn { padding: 6px 12px; border-radius: 4px; border: 1px solid #ccc; background: #fff; text-decoration: none; color: #222; font-size: 14px; }
        .btn-export { background: #28a745; color: #fff; border-color: #28a745; }
        table { border-collapse: collapse; width: 100%; font-size: 13px; }
        th, td { border: 1px solid #ddd; padding: 5px 7px; text-align: right; }
        th { background: #f2f2f2; text-align: center; }
        td.l { text-align: left; }
        tr:nth-child(even) { background: #fafafa; }
        tr.total-row { background: #e8f4fc; font-weight: bold; }
        .neg { color: #c00; }
    </style>
</head>
<body>
@php $fmt = fn($n, $d = 2) => number_format((float)$n, $d, ',', '.'); @endphp
<h1>Entradas de inventario (documentos de compra recibidos en la sucursal)</h1>
<p class="subtitulo">Importadas de central. En la BD: @foreach($porTipo as $t => $n){{ $t }} {{ $n }}@if(!$loop->last) · @endif @endforeach</p>
<form method="get" class="filtro">
    <label>Tipo:
        <select name="tipo">
            <option value="">Todos</option>
            @foreach(['FACTURA','NOTA','TRANSFERENCIA'] as $t)<option value="{{ $t }}" {{ ($filtros['tipo'] ?? '') === $t ? 'selected' : '' }}>{{ $t }}</option>@endforeach
        </select>
    </label>
    <label>Desde: <input type="date" name="desde" value="{{ $filtros['desde'] ?? '' }}"></label>
    <label>Hasta: <input type="date" name="hasta" value="{{ $filtros['hasta'] ?? '' }}"></label>
    <label>Buscar: <input type="text" name="q" value="{{ $filtros['q'] ?? '' }}" placeholder="N° de factura o proveedor"></label>
    <button type="submit">Aplicar</button>
    <a class="btn btn-export" href="{{ route('reportes.libro-inventario.entradas.export', $filtros) }}">Exportar ítems CSV</a>
    <a class="btn" href="{{ route('reportes.libro-inventario') }}">&larr; Volver al libro</a>
</form>
<p>{{ $fmt($resumen['n'], 0) }} documento(s) · {{ $fmt($resumen['items'], 0) }} líneas · {{ $fmt($resumen['unidades'], 2) }} unidades · costo $ {{ $fmt($resumen['costo_usd']) }}
    @if($resumen['sin_mapear'])<span class="neg"> · {{ $fmt($resumen['sin_mapear'], 0) }} líneas sin producto local</span>@endif</p>
<table>
    <thead><tr><th>Recepción</th><th>Emisión</th><th>Tipo</th><th>Documento</th><th>Nota</th><th>Proveedor</th><th>RIF</th><th>Origen</th><th>Líneas</th><th>Unidades</th><th>Costo USD</th><th>Tasa</th><th>Costo Bs</th><th></th></tr></thead>
    <tbody>
    @forelse($entradas as $e)
        <tr>
            <td class="l">{{ $e->fecha_recepcion }}</td><td class="l">{{ $e->fecha_emision }}</td><td class="l">{{ $e->tipo }}{{ $e->anulado ? ' (ANULADA)' : '' }}</td>
            <td class="l">{{ $e->numero_documento }}</td><td class="l">{{ $e->numero_nota }}</td><td class="l">{{ $e->proveedor }}</td><td class="l">{{ $e->proveedor_rif }}</td><td class="l">{{ $e->origen_sucursal }}</td>
            <td>{{ $e->items }}</td><td>{{ $fmt($e->unidades, 2) }}</td><td>{{ $fmt($e->costo_usd) }}</td><td>{{ $fmt($e->tasa_bs, 4) }}</td><td>{{ $fmt($e->costo_usd * $e->tasa_bs) }}</td>
            <td class="l">{{ $e->sin_mapear ? '⚠ ' . $e->sin_mapear . ' sin producto' : '' }}</td>
        </tr>
    @empty
        <tr><td colspan="14" class="l">No hay entradas con estos filtros.</td></tr>
    @endforelse
    </tbody>
</table>
</body>
</html>
