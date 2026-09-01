<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Manifiesto {{ $ruta->codigo }}</title>
    <style>
        /* Hoja pensada para imprimir y entregar al conductor. */
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; margin: 18px; color: #111; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        .sub { color: #555; font-size: 11px; margin-bottom: 14px; }
        .caja { border: 1px solid #bbb; border-radius: 4px; padding: 10px; margin-bottom: 12px; }
        .fila { display: flex; flex-wrap: wrap; gap: 22px; }
        .fila div { min-width: 130px; }
        .etiqueta { color: #666; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; }
        .valor { font-weight: bold; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #ccc; padding: 5px 6px; text-align: left; }
        th { background: #f0f0f0; font-size: 11px; }
        td.num, th.num { text-align: right; }
        .parada { margin-bottom: 16px; page-break-inside: avoid; }
        .parada h3 { font-size: 13px; margin: 0 0 4px; background: #eee; padding: 5px 7px; border-radius: 3px; }
        .firma { margin-top: 26px; display: flex; gap: 40px; }
        .firma div { flex: 1; border-top: 1px solid #333; padding-top: 4px; font-size: 10px; text-align: center; }
        .aviso { background: #fff8e1; border-left: 3px solid #ffa000; padding: 7px 9px; font-size: 11px; margin-bottom: 12px; }
        @media print { .noprint { display: none; } body { margin: 8px; } }
    </style>
</head>
<body>

<div class="noprint" style="margin-bottom:12px">
    <button onclick="window.print()" style="padding:7px 14px;cursor:pointer">Imprimir</button>
</div>

<h1>Manifiesto de carga &middot; {{ $ruta->codigo }}</h1>
<div class="sub">
    Fecha {{ $ruta->fecha ? $ruta->fecha->format('d/m/Y') : '—' }}
    &middot; emitido {{ now()->format('d/m/Y H:i') }}
</div>

@php
    // Si algún producto no tiene ficha física, el peso del manifiesto va corto.
    $sinFicha = 0;
    foreach ($ruta->paradas as $p) {
        foreach ($p->items as $it) {
            if ((float) $it->peso_kg == 0.0 && (float) $it->volumen_m3 == 0.0) { $sinFicha++; }
        }
    }
@endphp
@if($sinFicha > 0)
<div class="aviso">
    <strong>Atención:</strong> {{ $sinFicha }} línea(s) sin peso ni volumen registrado.
    Los totales de carga están subestimados.
</div>
@endif

<div class="caja">
    <div class="fila">
        <div>
            <div class="etiqueta">Vehículo</div>
            <div class="valor">{{ optional($ruta->vehiculo)->placa ?? '—' }}</div>
        </div>
        <div>
            <div class="etiqueta">Tipo</div>
            <div class="valor">{{ optional($ruta->vehiculo)->tipo ?? '—' }}</div>
        </div>
        <div>
            <div class="etiqueta">Conductor</div>
            <div class="valor">{{ optional($ruta->conductor)->nombre ?? '—' }}</div>
        </div>
        <div>
            <div class="etiqueta">Paradas</div>
            <div class="valor">{{ $ruta->paradas->count() }}</div>
        </div>
        <div>
            <div class="etiqueta">Peso total</div>
            <div class="valor">{{ number_format($ruta->peso_total_kg, 2) }} kg</div>
        </div>
        <div>
            <div class="etiqueta">Volumen total</div>
            <div class="valor">{{ number_format($ruta->volumen_total_m3, 3) }} m³</div>
        </div>
        <div>
            <div class="etiqueta">Bultos</div>
            <div class="valor">{{ $ruta->bultos_total }}</div>
        </div>
        <div>
            <div class="etiqueta">Utilización</div>
            <div class="valor">
                {{ $ruta->utilizacion_peso_pct !== null ? round($ruta->utilizacion_peso_pct).'%' : '—' }} peso /
                {{ $ruta->utilizacion_volumen_pct !== null ? round($ruta->utilizacion_volumen_pct).'%' : '—' }} vol.
            </div>
        </div>
    </div>
</div>

@foreach($ruta->paradas as $parada)
<div class="parada">
    <h3>
        Parada {{ $parada->orden }} &middot; {{ $parada->destino_nombre ?? 'Sin destino' }}
        @if($parada->direccion) &mdash; {{ $parada->direccion }} @endif
        @if($parada->ventana_inicio)
            &middot; ventana {{ $parada->ventana_inicio }}–{{ $parada->ventana_fin }}
        @endif
    </h3>

    <table>
        <thead>
            <tr>
                <th style="width:45%">Producto</th>
                <th class="num" style="width:12%">Cantidad</th>
                <th class="num" style="width:14%">Peso kg</th>
                <th class="num" style="width:14%">Vol. m³</th>
                <th style="width:15%">Recibido</th>
            </tr>
        </thead>
        <tbody>
            @foreach($parada->items as $item)
            <tr>
                <td>{{ $item->descripcion ?? ('Producto #'.$item->inventario_id) }}</td>
                <td class="num">{{ rtrim(rtrim(number_format($item->cantidad, 2, ',', '.'), '0'), ',') }}</td>
                <td class="num">{{ number_format($item->peso_kg, 2) }}</td>
                <td class="num">{{ number_format($item->volumen_m3, 4) }}</td>
                <td></td>
            </tr>
            @endforeach
            <tr>
                <td style="text-align:right"><strong>Totales de la parada</strong></td>
                <td class="num"><strong>{{ $parada->bultos }} bulto(s)</strong></td>
                <td class="num"><strong>{{ number_format($parada->peso_kg, 2) }}</strong></td>
                <td class="num"><strong>{{ number_format($parada->volumen_m3, 4) }}</strong></td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <div class="firma">
        <div>Recibido por (nombre y documento)</div>
        <div>Firma</div>
        <div>Fecha y hora</div>
    </div>
</div>
@endforeach

<div class="firma" style="margin-top:34px">
    <div>Conductor</div>
    <div>Despachador</div>
</div>

</body>
</html>
