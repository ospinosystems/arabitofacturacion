<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Orden de recolección — Orden #{{ $orden->id }}</title>
    <style>
        @page { size: letter portrait; margin: 12mm; }
        body { font-family: Arial, sans-serif; color: #111; font-size: 12px; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        .sub { color: #555; margin-bottom: 12px; }
        .pas { font-size: 14px; font-weight: bold; background: #f1f5f9; padding: 6px 10px; border-radius: 6px; display: inline-block; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; font-size: 12px; }
        th { background: #1e3a8a; color: #fff; }
        td.cant { text-align: center; font-weight: bold; }
        .chk { width: 22px; height: 22px; border: 2px solid #334155; display: inline-block; }
        .pie { margin-top: 24px; font-size: 11px; color: #444; }
    </style>
</head>
<body>
    <h1>ORDEN DE RECOLECCIÓN</h1>
    <div class="sub">Orden #{{ $orden->id }} · Destino {{ $orden->id_destino }} · {{ $orden->created_at }}</div>
    <div class="pas">PASILLERO: {{ $pasillero->nombre ?? ('#'.$pasillero->id ?? '—') }}</div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Descripción</th>
                <th>Cód. Barras</th>
                <th>Cód. Prov.</th>
                <th>Ubicación</th>
                <th>Cant. a buscar</th>
                <th>Recolectado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($asignaciones as $i => $a)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $a->producto->descripcion ?? '—' }}</td>
                    <td>{{ $a->producto->codigo_barras ?? '—' }}</td>
                    <td>{{ $a->producto->codigo_proveedor ?? '—' }}</td>
                    <td>{{ $a->warehouse_codigo ?? '' }}</td>
                    <td class="cant">{{ number_format($a->cantidad, 0) }}</td>
                    <td style="text-align:center;"><span class="chk"></span></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="pie">
        Total líneas: {{ $asignaciones->count() }} · Total unidades: {{ number_format($asignaciones->sum('cantidad'), 0) }}<br><br>
        Firma pasillero: ____________________________
    </div>

    <script>
        window.onload = function () { setTimeout(function () { window.print(); }, 300); };
    </script>
</body>
</html>
