<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de ventas</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: right; }
        th { background-color: #f2f2f2; text-align: center; }
        td.fecha, td.text-left { text-align: left; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        tr.total-row { background-color: #e8f4fc; font-weight: bold; }
        .filtro { margin-bottom: 20px; padding: 15px; background: #e8f4fc; border-radius: 6px; }
        .filtro label { margin-right: 8px; }
        .filtro input[type="date"] { margin-right: 15px; }
        .filtro button { padding: 6px 14px; cursor: pointer; }
        h1 { margin-bottom: 10px; }
        .subtitulo { color: #666; margin-bottom: 20px; }
        .btn-export { padding: 6px 12px; background: #28a745; color: #fff; text-decoration: none; border-radius: 4px; font-size: 14px; border: none; cursor: pointer; }
        .btn-export:hover { background: #218838; color: #fff; }
        .btn-check { padding: 4px 10px; font-size: 13px; cursor: pointer; background: #e9ecef; border: 1px solid #dee2e6; border-radius: 4px; }
        .btn-check:hover { background: #dee2e6; }
    </style>
</head>
<body>
    <h1>Reporte de ventas</h1>
    <p class="subtitulo">Ventas por fecha y máquina fiscal. Filtre por rango para ver monto Bs y rango de facturas por máquina.</p>

    @if(session('error'))
        <p style="color: #c00; padding: 10px; background: #fee; border-radius: 4px;">{{ session('error') }}</p>
    @endif

    <form method="get" action="{{ url()->current() }}" class="filtro">
        <label for="fecha_desde">Desde:</label>
        <input type="date" id="fecha_desde" name="fecha_desde" value="{{ $fecha_desde ?? '' }}">
        <label for="fecha_hasta">Hasta:</label>
        <input type="date" id="fecha_hasta" name="fecha_hasta" value="{{ $fecha_hasta ?? '' }}">
        <button type="submit">Buscar</button>
        @if($fecha_desde ?? $fecha_hasta ?? null)
            <a href="{{ url()->current() }}" style="margin-left: 10px;">Quitar filtro</a>
        @endif
        @if(count($resultados ?? []) > 0)
            <a href="{{ route('reportes.cuadre-diario.export', request()->only(['fecha_desde', 'fecha_hasta'])) }}" style="margin-left: 15px;" class="btn-export">Exportar CSV (resumen)</a>
        @endif
    </form>

    @if(count($resultados ?? []) > 0)
    <form method="post" action="{{ route('reportes.cuadre-diario.descargar-masivo') }}" id="form-descarga-masiva">
        @csrf
        <input type="hidden" name="fecha_desde" value="{{ $fecha_desde ?? '' }}">
        <input type="hidden" name="fecha_hasta" value="{{ $fecha_hasta ?? '' }}">
    @endif
    <table>
        <thead>
            <tr>
                @if(count($resultados ?? []) > 0)
                <th style="width: 44px; text-align: center;">
                    <label style="cursor: pointer; display: inline-flex; align-items: center; gap: 4px; font-weight: normal;">
                        <input type="checkbox" id="cb-seleccionar-todo" title="Seleccionar todo">
                    </label>
                </th>
                @endif
                <th>Fecha</th>
                <th>Máquina fiscal</th>
                <th>Monto USD</th>
                <th>Monto Bs</th>
                <th>Tasa Bs/USD</th>
                <th>Nº factura inicio</th>
                <th>Nº factura fin</th>
                <th>Cant. pedidos</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse($resultados as $r)
                <tr>
                    @if(count($resultados ?? []) > 0)
                    <td style="text-align: center;">
                        <input type="checkbox" name="fechas[]" value="{{ $r->fecha }}" class="cb-dia">
                    </td>
                    @endif
                    <td class="fecha">{{ $r->fecha }}</td>
                    <td class="text-left">{{ $r->maquina_fiscal }}</td>
                    <td>{{ number_format($r->monto_usd ?? 0, 2) }}</td>
                    <td>{{ number_format($r->monto_bs, 2) }}</td>
                    <td>{{ $r->tasa !== null ? number_format($r->tasa, 4) : '—' }}</td>
                    <td class="text-left">{{ $r->factura_inicio }}</td>
                    <td class="text-left">{{ $r->factura_fin }}</td>
                    <td>{{ $r->cantidad }}</td>
                    <td class="text-left">
                        <a href="{{ route('reportes.cuadre-diario.dia', ['fecha' => $r->fecha]) }}{{ $r->maquina_fiscal !== '—' ? '?maquina_fiscal=' . urlencode($r->maquina_fiscal) : '' }}">Ver detalle</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">
                        @if($fecha_desde ?? $fecha_hasta ?? null)
                            No hay ventas en el rango indicado.
                        @else
                            No hay ventas con número de factura.
                        @endif
                    </td>
                </tr>
            @endforelse
            @if(count($resultados ?? []) > 0 && isset($total_dias))
                <tr class="total-row">
                    <td style="text-align: center;">—</td>
                    <td class="fecha text-left"><strong>{{ $total_dias }} {{ $total_dias === 1 ? 'día' : 'días' }}</strong></td>
                    <td class="text-left">—</td>
                    <td><strong>{{ number_format($total_monto_usd ?? 0, 2) }}</strong></td>
                    <td><strong>{{ number_format($total_monto_bs ?? 0, 2) }}</strong></td>
                    <td>—</td>
                    <td class="text-left">—</td>
                    <td class="text-left">—</td>
                    <td><strong>{{ number_format($total_cantidad_pedidos ?? 0, 0) }}</strong></td>
                    <td class="text-left"></td>
                </tr>
            @endif
        </tbody>
    </table>
    @if(count($resultados ?? []) > 0)
        <div style="margin-top: 12px;">
            <button type="submit" class="btn-export">Descargar ZIP (pedidos en Mes/Día)</button>
        </div>
    </form>
    <script>
        (function() {
            var selectAll = document.getElementById('cb-seleccionar-todo');
            var rows = document.querySelectorAll('.cb-dia');
            if (selectAll && rows.length) {
                selectAll.addEventListener('change', function() {
                    rows.forEach(function(cb) { cb.checked = selectAll.checked; });
                });
            }
        })();
    </script>
    @endif
</body>
</html>
