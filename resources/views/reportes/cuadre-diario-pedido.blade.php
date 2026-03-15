<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de ventas - Factura {{ $pedido->numero_factura ?? '—' }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: sans-serif; font-size: 14px; color: #111; background: #fff; }
        .wrap { display: flex; flex-direction: column; min-height: 100vh; }
        .header { flex-shrink: 0; padding: 12px 16px; background: #f3f4f6; border-bottom: 1px solid #d1d5db; display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 16px; }
        .header-cliente { }
        .header-cliente label { display: block; font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; margin-bottom: 4px; }
        .header-cliente .val { font-size: 13px; color: #111; margin-bottom: 2px; }
        .header-origen label { font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; margin-bottom: 4px; }
        .header-origen .val { font-size: 14px; font-weight: 500; color: #111; }
        .header-pedido { font-size: 13px; color: #6b7280; }
        .content { flex: 1; overflow: auto; padding: 16px; }
        .content-inner { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-start; }
        .tabla-wrap { flex: 1; min-width: 0; }
        table.tabla { width: 100%; border-collapse: collapse; border: 1px solid #d1d5db; font-size: 13px; }
        table.tabla th, table.tabla td { border: 1px solid #e5e7eb; padding: 8px 12px; text-align: left; }
        table.tabla th { background: #f3f4f6; font-weight: 600; color: #374151; }
        table.tabla th.num, table.tabla td.num { text-align: right; }
        table.tabla td.mono { font-family: ui-monospace, monospace; }
        table.tabla tbody tr:hover { background: #f9fafb; }
        .totales { border: 1px solid #d1d5db; border-radius: 6px; background: #f9fafb; padding: 12px 16px; }
        .totales table { border-collapse: collapse; margin-left: auto; }
        .totales td { padding: 4px 0; font-size: 13px; }
        .totales td:first-child { padding-right: 16px; color: #374151; }
        .totales td:last-child { text-align: right; font-weight: 500; }
        .totales tr.total td { font-weight: 700; }
        .footer { flex-shrink: 0; display: flex; align-items: center; justify-content: flex-end; gap: 12px; padding: 12px 16px; background: #f3f4f6; border-top: 1px solid #d1d5db; }
        .btn { display: inline-flex; align-items: center; padding: 8px 16px; border-radius: 6px; font-size: 14px; text-decoration: none; cursor: pointer; border: 1px solid; font-weight: 500; }
        .btn-back { background: #6b7280; color: #fff; border-color: #4b5563; }
        .btn-back:hover { background: #4b5563; color: #fff; }
        .btn-export { background: #28a745; color: #fff; border-color: #1e7e34; }
        .btn-export:hover { background: #218838; color: #fff; }
        .btn-print { background: #4f46e5; color: #fff; border-color: #4338ca; }
        .btn-print:hover { background: #4338ca; color: #fff; }
        @media print {
            .footer { display: none !important; }
            .header { background: transparent; border: none; }
        }
    </style>
</head>
<body>
    @php
        $cliente = $pedido->cliente;
        $razonSocial = $cliente && ($cliente->nombre ?? null) ? ($cliente->nombre === 'CF' ? 'Sin cliente' : $cliente->nombre) : '—';
        $rifCliente = $cliente && $cliente->identificacion && $cliente->identificacion !== 'CF' ? $cliente->identificacion : '—';
        $direccionCliente = $cliente && $cliente->direccion && trim((string)$cliente->direccion) !== '' ? $cliente->direccion : '—';
        $nombreOrigen = '—';
        $fechaPedido = $pedido->fecha_factura ? date('Y-m-d', strtotime($pedido->fecha_factura)) : ($pedido->created_at ? date('Y-m-d', strtotime($pedido->created_at)) : '');
        $fmt = function($n) { return number_format((float)$n, 2, ',', '.'); };
        $fmtPrecio = function($n) { return number_format((float)$n, 4, ',', '.'); };
        $tasaPedido = $tasaPedido ?? null;
    @endphp

    <div class="wrap">
        <header class="header">
            <div class="header-cliente">
                <label>Cliente</label>
                <div class="val"><strong>Razón Social:</strong> {{ $razonSocial }}</div>
                <div class="val"><strong>RIF:</strong> {{ $rifCliente }}</div>
                <div class="val"><strong>Dirección:</strong> {{ $direccionCliente }}</div>
            </div>
            <div class="header-origen">
                <label>Origen</label>
                <div class="val">{{ $nombreOrigen }}</div>
            </div>
            <div class="header-pedido">
                @if($fechaPedido){{ $fechaPedido }}@endif
                @if($pedido->numero_factura)
                    @if($fechaPedido)<br>@endif
                    Factura {{ $pedido->numero_factura }}
                @endif
            </div>
        </header>

        <div class="content">
            <div class="content-inner">
                <div class="tabla-wrap">
                    <table class="tabla">
                        <thead>
                            <tr>
                                <th style="width: 48px;">#</th>
                                <th>Código</th>
                                <th>Cód. proveedor</th>
                                <th>Descripción</th>
                                <th class="num" style="width: 96px;">Cantidad</th>
                                <th class="num" style="width: 112px;">Precio</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($items as $index => $item)
                                @php
                                    $codigo = trim($item->codigo_barras ?? '') ?: '—';
                                    $codigoProv = trim($item->codigo_proveedor ?? '') ?: '—';
                                    $desc = $item->producto_descripcion ?? '—';
                                    $cant = (float)($item->cantidad ?? 0);
                                    $precio = $item->precio_unitario !== null ? (float)$item->precio_unitario : 0;
                                @endphp
                                <tr>
                                    <td class="num mono" style="color:#4b5563;">{{ $index + 1 }}</td>
                                    <td class="mono">{{ $codigo }}</td>
                                    <td class="mono">{{ $codigoProv }}</td>
                                    <td>{{ $desc }}</td>
                                    <td class="num">{{ $cant == (int)$cant ? (int)$cant : number_format($cant, 2, ',', '.') }}</td>
                                    <td class="num">{{ $fmtPrecio($precio) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">No hay ítems en este pedido.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="totales">
                    <table>
                        @if($tasaPedido !== null)
                        <tr><td>Tasa del pedido</td><td>{{ $fmtPrecio($tasaPedido) }} Bs/USD</td></tr>
                        @endif
                        <tr><td>Subtotal (USD)</td><td>$ {{ $fmt($subtotalUsd ?? 0) }}</td></tr>
                        <tr><td>Subtotal (Bs)</td><td>Bs {{ $fmt($subtotalBs ?? 0) }}</td></tr>
                        <tr class="total"><td>Monto Total (USD)</td><td>$ {{ $fmt($montoTotalUsd ?? 0) }}</td></tr>
                        <tr class="total"><td>Monto Total (Bs)</td><td>Bs {{ $fmt($montoTotalBs ?? 0) }}</td></tr>
                    </table>
                </div>
            </div>
        </div>

        @if(empty($pdf))
        <footer class="footer">
            @php $fechaDia = date('Y-m-d', strtotime($pedido->fecha_factura ?? $pedido->created_at)); @endphp
            <a href="{{ route('reportes.cuadre-diario.dia', ['fecha' => $fechaDia]) }}{{ $pedido->maquina_fiscal ? '?maquina_fiscal=' . urlencode($pedido->maquina_fiscal) : '' }}" class="btn btn-back">&larr; Volver al día</a>
            <a href="{{ route('reportes.cuadre-diario.pedido.export', ['id' => $pedido->id]) }}" class="btn btn-export">Exportar CSV</a>
            <a href="{{ route('reportes.cuadre-diario.pedido.pdf', ['id' => $pedido->id]) }}" class="btn btn-export">Descargar PDF</a>
            <button type="button" class="btn btn-print" onclick="window.print();">Imprimir</button>
        </footer>
        @endif
    </div>
</body>
</html>
