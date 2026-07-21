<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Orden de despacho — Orden #{{ $orden->id }}</title>
    <style>
        @page { size: letter portrait; margin: 12mm; }
        body { font-family: Arial, sans-serif; color: #111; font-size: 12px; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        .sub { color: #555; margin-bottom: 12px; }
        h2 { font-size: 14px; margin: 16px 0 6px; border-bottom: 2px solid #1e3a8a; padding-bottom: 2px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #cbd5e1; padding: 5px 8px; text-align: left; font-size: 11px; }
        th { background: #1e3a8a; color: #fff; }
        td.cant { text-align: center; font-weight: bold; }
        .bulto-h { background: #e0e7ff; font-weight: bold; }
        .excl th { background: #b91c1c; }
        .tot { margin-top: 10px; font-size: 12px; }
        .pie { margin-top: 28px; font-size: 11px; }
        .firma { display: inline-block; width: 45%; margin-top: 30px; border-top: 1px solid #333; text-align: center; padding-top: 4px; }
    </style>
</head>
<body>
    @php
        $totalBultos = $orden->bultos->count();
        $totalUnidades = $orden->bultos->sum(fn($b) => $b->items->sum('cantidad'));
    @endphp
    <h1>ORDEN DE DESPACHO</h1>
    <div class="sub">Orden #{{ $orden->id }} · Destino {{ $orden->id_destino }} · Central #{{ $orden->id_transferencia_central ?? '—' }} · {{ $orden->created_at }}</div>

    <h2>Bultos ({{ $totalBultos }})</h2>
    @foreach ($orden->bultos->sortBy('numero') as $b)
        <table>
            <tr class="bulto-h">
                <td colspan="3">BULTO N° {{ $b->numero }} — {{ $b->codigo_barras }} ({{ strtoupper($b->estado) }})</td>
            </tr>
            <tr><th>Descripción</th><th>Cód. Barras</th><th>Cantidad</th></tr>
            @foreach ($b->items as $bi)
                <tr>
                    <td>{{ $bi->producto->descripcion ?? '—' }}</td>
                    <td>{{ $bi->producto->codigo_barras ?? '—' }}</td>
                    <td class="cant">{{ number_format($bi->cantidad, 0) }}</td>
                </tr>
            @endforeach
        </table>
        <br>
    @endforeach

    <div class="tot"><b>Total bultos:</b> {{ $totalBultos }} &nbsp;·&nbsp; <b>Total unidades despachadas:</b> {{ number_format($totalUnidades, 0) }}</div>

    @if (count($excluidos))
        <h2 style="border-color:#b91c1c;color:#b91c1c;">Mercancía EXCLUIDA (no encontrada / no empacada)</h2>
        <table class="excl">
            <tr><th>Descripción</th><th>Cód. Barras</th><th>Solicitado</th><th>Empacado</th><th>Excluido</th></tr>
            @foreach ($excluidos as $e)
                <tr>
                    <td>{{ $e['descripcion'] ?? '—' }}</td>
                    <td>{{ $e['codigo_barras'] ?? '—' }}</td>
                    <td class="cant">{{ number_format($e['solicitado'], 0) }}</td>
                    <td class="cant">{{ number_format($e['empacado'], 0) }}</td>
                    <td class="cant">{{ number_format($e['excluido'], 0) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <div class="pie">
        <span class="firma">Despachado por</span>
        <span class="firma" style="float:right;">Recibido por</span>
    </div>

    <script>
        window.onload = function () { setTimeout(function () { window.print(); }, 300); };
    </script>
</body>
</html>
