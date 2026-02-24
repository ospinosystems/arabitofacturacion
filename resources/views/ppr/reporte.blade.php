@php
    $porPedido = $porPedido ?? [];
    $fecha_desde = $fecha_desde ?? '';
    $fecha_hasta = $fecha_hasta ?? '';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte PPR — {{ $fecha_desde }} al {{ $fecha_hasta }}</title>
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; color: #333; margin: 0; padding: 12px; background: #f5f5f5; }
        @media print {
            body { background: white; padding: 0; }
            .no-print { display: none !important; }
        }
        @page { size: landscape; margin: 10mm; }
        .no-print { margin-bottom: 12px; }
        .btn { padding: 8px 14px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; background: #2563eb; color: white; }
        .header { margin-bottom: 14px; padding-bottom: 10px; border-bottom: 2px solid #333; }
        .titulo { font-size: 18px; font-weight: bold; text-align: center; margin: 8px 0; }
        .subtitulo { font-size: 12px; text-align: center; color: #555; margin: 0 0 12px 0; }
        .tabla { width: 100%; border-collapse: collapse; font-size: 10px; margin-top: 8px; }
        .tabla th, .tabla td { border: 1px solid #000; padding: 5px 6px; text-align: left; }
        .tabla th { background: #e5e5e5; font-weight: bold; }
        .tabla .text-right { text-align: right; }
        .tabla .text-center { text-align: center; }
        .pedido-block { margin-bottom: 16px; page-break-inside: avoid; }
        .pedido-header { background: #334155; color: white; padding: 6px 10px; font-weight: bold; }
        .entregas-table { margin-top: 4px; }
        .pie { margin-top: 16px; padding-top: 8px; border-top: 1px solid #ccc; font-size: 9px; color: #666; text-align: center; }
    </style>
</head>
<body>
    <div class="no-print">
        <button type="button" class="btn" onclick="window.print();">Imprimir</button>
    </div>

    <div class="header">
        <div class="titulo">Reporte PPR — Pendiente por Retirar</div>
        <div class="subtitulo">Desde {{ $fecha_desde }} hasta {{ $fecha_hasta }} — Generado {{ now()->format('d/m/Y H:i') }}</div>
    </div>

    @foreach($porPedido as $p)
        <div class="pedido-block">
            <div class="pedido-header">
                Pedido #{{ $p['id_pedido'] }}
                — Fecha pedido: {{ $p['fecha_pedido'] ? \Carbon\Carbon::parse($p['fecha_pedido'])->format('d/m/Y H:i') : '—' }}
                — Vendedor: {{ $p['vendedor_nombre'] ?? '—' }}
                — Cliente: {{ $p['cliente_nombre'] ?? '—' }} @if(!empty($p['cliente_identificacion'])) ({{ $p['cliente_identificacion'] }}) @endif
            </div>
            <table class="tabla entregas-table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Cód. barras</th>
                        <th>Cód. proveedor</th>
                        <th class="text-right">Unid. entregadas</th>
                        <th>Fecha y hora entrega</th>
                        <th>Portero</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($p['entregas'] ?? [] as $e)
                        <tr>
                            <td>{{ $e['producto_desc'] ?? '—' }}</td>
                            <td>{{ $e['codigo_barras'] ?? '—' }}</td>
                            <td>{{ $e['codigo_proveedor'] ?? '—' }}</td>
                            <td class="text-right">{{ $e['unidades_entregadas'] ?? 0 }}</td>
                            <td>{{ $e['fecha_hora'] ? \Carbon\Carbon::parse($e['fecha_hora'])->format('d/m/Y H:i') : '—' }}</td>
                            <td>{{ $e['portero'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

    @if(count($porPedido) === 0)
        <p class="text-center text-gray-500 py-8">No hay registros PPR en el rango indicado.</p>
    @endif

    <div class="pie">
        Reporte PPR — {{ count($porPedido) }} pedido(s) — {{ $fecha_desde }} / {{ $fecha_hasta }}
    </div>
</body>
</html>
