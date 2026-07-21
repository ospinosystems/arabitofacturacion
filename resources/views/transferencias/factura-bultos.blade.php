<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Factura de despacho — Orden #{{ $orden->id }}</title>
    <style>
        @page { size: letter portrait; margin: 12mm; }
        body { font-family: Arial, sans-serif; color: #111; font-size: 12px; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        .sub { color: #555; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #cbd5e1; padding: 5px 8px; text-align: left; font-size: 11px; }
        th { background: #1e3a8a; color: #fff; }
        td.cant, td.bulto { text-align: center; font-weight: bold; }
        td.bulto { background: #eef2ff; }
        tfoot td { font-weight: bold; }
    </style>
</head>
<body>
    <h1>FACTURA DE DESPACHO</h1>
    <div class="sub">Orden #{{ $orden->id }} · Destino {{ $orden->id_destino }} · Central #{{ $orden->id_transferencia_central ?? '—' }} · {{ $orden->created_at }}</div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Descripción</th>
                <th>Cód. Barras</th>
                <th>N° Bulto</th>
                <th>Cantidad</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $i => $f)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $f['descripcion'] ?? '—' }}</td>
                    <td>{{ $f['codigo_barras'] ?? '—' }}</td>
                    <td class="bulto">N° {{ $f['bulto'] }}</td>
                    <td class="cant">{{ number_format($f['cantidad'], 0) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" style="text-align:right;font-weight:bold;">TOTAL UNIDADES</td>
                <td class="cant">{{ number_format(collect($filas)->sum('cantidad'), 0) }}</td>
            </tr>
        </tfoot>
    </table>

    <script>
        window.onload = function () { setTimeout(function () { window.print(); }, 300); };
    </script>
</body>
</html>
