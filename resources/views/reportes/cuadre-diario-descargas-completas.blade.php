<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Descargas completas - Reporte de ventas</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; max-width: 760px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background: #f2f2f2; }
        td.num { text-align: right; }
        tr.total { background: #e8f4fc; font-weight: bold; }
        .btn { padding: 5px 12px; background: #28a745; color: #fff; text-decoration: none; border-radius: 4px; font-size: 14px; }
        .nota { color: #666; font-size: 14px; }
    </style>
</head>
<body>
    <h1>Descargas completas</h1>
    <p class="nota">Todas las facturas en PDF, un ZIP por mes (carpetas Año-Mes y dentro una carpeta por día), y un ZIP con todo.</p>
    @if(session('error'))<p style="color:#c00">{{ session('error') }}</p>@endif
    @if(empty($archivos))
        <p>Las descargas se están preparando. Vuelva a abrir esta página en unos minutos.</p>
    @else
        <table>
            <tr><th>Archivo</th><th>Tamaño</th><th>Preparado</th><th></th></tr>
            @foreach($archivos as $a)
                <tr class="{{ str_contains($a['nombre'], 'COMPLETO') ? 'total' : '' }}">
                    <td>{{ $a['nombre'] }}</td>
                    <td class="num">{{ number_format($a['mb'], 1, ',', '.') }} MB</td>
                    <td>{{ $a['fecha'] }}</td>
                    <td><a class="btn" href="{{ route('reportes.cuadre-diario.descargas-completas.archivo', ['archivo' => $a['nombre']]) }}">Descargar</a></td>
                </tr>
            @endforeach
        </table>
    @endif
    <p><a href="{{ route('reportes.cuadre-diario') }}">&larr; Volver al reporte</a></p>
</body>
</html>
