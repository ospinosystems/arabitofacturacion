<?php $t_base = 0; ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="icon" type="image/png" href="{{ asset('images/favicon.ico') }}">
    <title>Detalle Créditos {{ $cliente->nombre ?? '' }}</title>

    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <style>
        @media print {
            .pagebreak { page-break-before: always; }
            @page {
                size: letter;
                margin: 0.25in;
            }
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 9px;
        }

        .report-wrapper {
            max-width: 8.5in;
            margin: 0 auto;
            padding: 0.15in;
        }

        .delivery-note {
            font-family: Arial, sans-serif;
            max-width: 100%;
            width: 8.5in;
            margin: 0 auto 0.15in auto;
            padding: 0.15in;
            box-sizing: border-box;
            font-size: 9px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .delivery-header {
            text-align: center;
            margin-bottom: 0.05in;
            border-bottom: 1px solid #333;
            padding-bottom: 0.03in;
        }

        .delivery-title {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 0;
        }

        .client-info {
            display: block;
            margin-bottom: 0.15in;
        }

        .client-section {
            border: 1px solid #ddd;
            padding: 0.1in;
            border-radius: 4px;
        }

        .client-section h4 {
            margin: 0 0 0.1in 0;
            color: #333;
            font-size: 10px;
        }

        .client-field {
            display: inline-block;
            width: 32%;
            vertical-align: top;
            margin-bottom: 0.05in;
        }

        .client-label {
            font-weight: bold;
            color: #555;
        }

        .products-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0.15in;
        }

        .products-table th {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            padding: 4px 3px;
            text-align: center;
            font-weight: bold;
            font-size: 9px;
            white-space: nowrap;
        }

        .products-table td {
            border: 1px solid #ddd;
            padding: 3px 3px;
            text-align: center;
            font-size: 8px;
            white-space: nowrap;
        }

        .products-table .product-description {
            text-align: left;
            max-width: 200px;
        }

        .products-table .quantity {
            text-align: center;
            font-weight: bold;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #333;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 0.15in;
        }

        .totals-table th,
        .totals-table td {
            border: 1px solid #ddd;
            padding: 4px 6px;
            text-align: right;
            font-size: 9px;
        }

        .totals-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #333;
            border-bottom: 2px solid #333;
        }

        .totals-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .totals-table tr:hover {
            background-color: #f0f0f0;
        }

        .total-row {
            font-weight: bold;
            font-size: 10px;
            background-color: #e9ecef !important;
            border-top: 1px solid #333;
        }

        .summary-header {
            margin-bottom: 0.15in;
        }

        .status-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
            margin-left: 6px;
        }

        .status-anulado {
            background-color: #dc3545;
            color: white;
        }

        .status-pendiente {
            background-color: #6c757d;
            color: white;
        }

        .abonos-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            font-size: 8px;
        }

        .abonos-table th,
        .abonos-table td {
            border: 1px solid #ddd;
            padding: 2px 4px;
            text-align: right;
            white-space: nowrap;
        }

        .abonos-table th {
            background-color: #f8f9fa;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="report-wrapper">

    <div class="summary-header">
        <h3>Resumen créditos de {{ $cliente->nombre ?? '' }}</h3>
        <table class="totals-table">
            <tr>
                <th class="text-left">Cantidad de movimientos</th>
                <td>{{ $resumen['cantidad_pedidos'] ?? 0 }}</td>
            </tr>
            <tr>
                <th class="text-left">Total Créditos</th>
                <td>{{ moneda($resumen['total_creditos'] ?? 0) }}</td>
            </tr>
            <tr>
                <th class="text-left">Cant. mov. crédito</th>
                <td>{{ $resumen['cantidad_mov_creditos'] ?? 0 }}</td>
            </tr>
            <tr>
                <th class="text-left">Total Abonos</th>
                <td>{{ moneda($resumen['total_abonos'] ?? 0) }}</td>
            </tr>
            <tr>
                <th class="text-left">Cant. mov. abono</th>
                <td>{{ $resumen['cantidad_mov_abonos'] ?? 0 }}</td>
            </tr>
            <tr class="total-row">
                <th class="text-left">Saldo (Abonos - Créditos)</th>
                <td>{{ moneda($resumen['saldo'] ?? 0) }}</td>
            </tr>
        </table>
    </div>

    @foreach ($pedidos as $pedido)
        <div class="delivery-note">
            <div class="delivery-header">
                <div class="delivery-title">
                    Detalle crédito #{{ sprintf('%08d', $pedido->id) }}
                    @if($pedido->estado==2)
                        <span class="status-badge status-anulado">ANULADO</span>
                    @endif
                    @if($pedido->estado==0)
                        <span class="status-badge status-pendiente">PENDIENTE</span>
                    @endif
                </div>
                <div style="font-size: 10px; margin-bottom: 0; display: flex; justify-content: space-between; align-items: center;">
                    <span>Fecha: {{ substr($pedido->created_at,0,10) }}</span>
                    <span>Vence: {{ $pedido->fecha_vence }}</span>
                </div>
            </div>

            <div class="client-info">
                <div class="client-section">
                    <h4>DATOS DEL CLIENTE</h4>
                    <div class="client-field">
                        <span class="client-label">Nombre/Razón Social:</span><br>
                        {{ $cliente->nombre ?? '' }}
                    </div>
                    <div class="client-field">
                        <span class="client-label">RIF/C.I./Pasaporte:</span><br>
                        {{ $cliente->identificacion ?? '' }}
                    </div>
                    <div class="client-field">
                        <span class="client-label">Dirección:</span><br>
                        {{ $cliente->direccion ?? '' }}
                    </div>
                </div>

                <div class="client-section">
                    <h4>RESUMEN PAGO CRÉDITO</h4>
                    <div class="client-field">
                        <span class="client-label">Crédito otorgado:</span>
                        {{ moneda($pedido->saldoDebe ?? 0) }}
                    </div>
                    <div class="client-field">
                        <span class="client-label">Abonos aplicados:</span>
                        {{ moneda($pedido->saldoAbono ?? 0) }}
                    </div>
                    <div class="client-field">
                        <span class="client-label">Saldo movimiento:</span>
                        {{ moneda(($pedido->saldoAbono ?? 0) - ($pedido->saldoDebe ?? 0)) }}
                    </div>
                    <div class="client-field">
                        @foreach($pedido->pagos as $ee)
                            @if($ee->tipo==1&&$ee->monto!=0)<span class="status-badge" style="background-color: #17a2b8;">Trans. {{$ee->monto}}</span> @endif
                            @if($ee->tipo==2&&$ee->monto!=0)<span class="status-badge" style="background-color: #6c757d;">Deb. {{$ee->monto}}</span> @endif
                            @if($ee->tipo==3&&$ee->monto!=0)<span class="status-badge" style="background-color: #28a745;">Efec. {{$ee->monto}}</span> @endif
                            @if($ee->tipo==6&&$ee->monto!=0)<span class="status-badge" style="background-color: #dc3545;">Vuel. {{$ee->monto}}</span> @endif
                            @if($ee->tipo==4&&$ee->monto!=0)<span class="status-badge" style="background-color: #6c757d; color: white;">Cred. {{$ee->monto}}</span> @endif
                        @endforeach
                    </div>

                    @php
                        $abonos = $pedido->pagos->where('cuenta', 0)->where('monto', '<>', 0);
                    @endphp
                    @if($abonos->count())
                        <div class="client-field">
                            <span class="client-label">Detalle de abonos:</span>
                            <table class="abonos-table">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Descripción</th>
                                        <th>Método</th>
                                        <th>Monto</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($abonos as $abono)
                                        <tr>
                                            <td>{{ isset($abono->updated_at) ? substr($abono->updated_at,0,10) : '' }}</td>
                                            <td>
                                                @php
                                                    $metodoDesc = '';
                                                    if ($abono->tipo==1) { $metodoDesc = 'Transferencia'; }
                                                    if ($abono->tipo==2) { $metodoDesc = 'Débito'; }
                                                    if ($abono->tipo==3) { $metodoDesc = 'Efectivo'; }
                                                    if ($abono->tipo==5) { $metodoDesc = 'Biopago'; }
                                                    if ($abono->tipo==6) { $metodoDesc = 'Vuelto'; }
                                                    if ($abono->tipo==4) { $metodoDesc = 'Crédito'; }
                                                @endphp
                                                Pago por {{ $metodoDesc }} de {{ $cliente->nombre ?? '' }}
                                            </td>
                                            <td>
                                                @if($abono->tipo==1) TRANSF @endif
                                                @if($abono->tipo==2) DEBITO @endif
                                                @if($abono->tipo==3) EFECTIVO @endif
                                                @if($abono->tipo==5) BIOPAGO @endif
                                                @if($abono->tipo==6) VUELTO @endif
                                                @if($abono->tipo==4) CRÉDITO @endif
                                            </td>
                                            <td>{{ moneda($abono->monto) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <table class="products-table">
                <thead>
                    <tr>
                        <th style="width: 15%;">Código</th>
                        <th style="width: 45%;">Descripción del Producto</th>
                        <th style="width: 10%;">Cantidad</th>
                        <th style="width: 15%;">Precio Unitario</th>
                        <th style="width: 15%;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pedido->items as $val)
                        <tr>
                            <td>{{ $val->producto->codigo_barras ?? '' }}</td>
                            <td class="product-description">{{ $val->producto->descripcion ?? '' }}</td>
                            <td class="quantity">{{ number_format($val->cantidad, 2) }}</td>
                            <td>
                                {{ moneda($val->producto->precio ?? 0) }}
                            </td>
                            <td>
                                {{ moneda(($val->cantidad) * ($val->producto->precio ?? 0)) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @php
                $totalProductos = $pedido->items->reduce(function($carry, $val) {
                    return $carry + (($val->cantidad) * ($val->producto->precio ?? 0));
                }, 0);
            @endphp
            <table class="totals-table" style="margin-top: 0; margin-bottom: 0.1in;">
                <tr class="total-row">
                    <th class="text-left">Total productos (precios actuales)</th>
                    <td style="width: 25%;">{{ moneda($totalProductos) }}</td>
                </tr>
            </table>
            <div style="font-size: 7px; color: #666; margin-bottom: 0.1in;">
                * El total de productos está calculado de acuerdo a los precios actuales.<br>
                * El valor de "Crédito otorgado" corresponde al precio del día de creación del pedido.
            </div>
        </div>
    @endforeach

    <div style="font-size: 8px; margin-top: 0.1in; text-align: left; color: #555;">
        Nota: Los créditos están sujetos a cambios de acuerdo a variaciones de precio del producto.
    </div>

</div>
</body>
</html>
