<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Inventario - Planilla Inventario</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
            color: #333;
        }
        .header h2 {
            margin: 5px 0;
            font-size: 14px;
            color: #666;
        }
        .info {
            margin-bottom: 15px;
            font-size: 9px;
        }
        .info span {
            margin-right: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
            font-size: 9px;
        }
        td {
            font-size: 9px;
        }
        .producto-header {
            background-color: #e8f4f8;
            font-weight: bold;
            font-size: 10px;
        }
        .producto-header td {
            padding: 8px;
        }
        .movimiento-row {
            background-color: #fff;
        }
        .movimiento-row:nth-child(even) {
            background-color: #f9f9f9;
        }
        .movimiento-resta {
            background-color: #ffebee !important;
            color: #c62828;
        }
        .movimiento-resta td {
            color: #c62828;
            font-weight: bold;
        }
        .movimiento-adicion {
            background-color: #fff;
        }
        .total-row {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .footer {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-size: 9px;
            text-align: center;
            color: #666;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>REPORTE DE INVENTARIO</h1>
        <h2>PLANILLA INVENTARIO</h2>
    </div>

    <div class="info">
        <span><strong>Fecha Desde:</strong> {{ date('d/m/Y', strtotime($fechaDesde)) }}</span>
        <span><strong>Fecha Hasta:</strong> {{ date('d/m/Y', strtotime($fechaHasta)) }}</span>
        <span><strong>Total Productos:</strong> {{ $totalProductos }}</span>
        <span><strong>Total Movimientos:</strong> {{ $totalMovimientos }}</span>
    </div>

    @foreach($productosAgrupados as $idProducto => $grupo)
        <table>
            <thead>
                <tr class="producto-header">
                    <td colspan="7">
                        <strong>PRODUCTO:</strong> {{ $grupo['producto']->descripcion ?? 'N/A' }} | 
                        <strong>Código Barras:</strong> {{ $grupo['producto']->codigo_barras ?? 'N/A' }} | 
                        <strong>Código Proveedor:</strong> {{ $grupo['producto']->codigo_proveedor ?? 'N/A' }} | 
                        <strong>Unidad:</strong> {{ $grupo['producto']->unidad ?? 'N/A' }}
                    </td>
                </tr>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 15%;">Fecha</th>
                    <th style="width: 15%;">Hora</th>
                    <th style="width: 15%;">Usuario</th>
                    <th style="width: 15%;" class="text-right">Cantidad Anterior</th>
                    <th style="width: 15%;" class="text-right">Cantidad Movimiento</th>
                    <th style="width: 20%;" class="text-right">Cantidad Final</th>
                </tr>
            </thead>
            <tbody>
                @foreach($grupo['movimientos'] as $index => $movimiento)
                    @php
                        $esResta = $movimiento->cantidad < 0;
                        $claseFila = $esResta ? 'movimiento-resta' : 'movimiento-adicion';
                    @endphp
                    <tr class="movimiento-row {{ $claseFila }}">
                        <td class="text-center">{{ $index + 1 }}</td>
                        <td>{{ date('d/m/Y', strtotime($movimiento->created_at)) }}</td>
                        <td>{{ date('H:i:s', strtotime($movimiento->created_at)) }}</td>
                        <td>{{ $movimiento->usuario->usuario ?? 'N/A' }}</td>
                        <td class="text-right">{{ number_format($movimiento->cantidadafter - $movimiento->cantidad, 4) }}</td>
                        <td class="text-right">
                            @if($esResta)
                                <span style="color: #c62828; font-weight: bold;">{{ number_format($movimiento->cantidad, 4) }}</span>
                            @else
                                {{ number_format($movimiento->cantidad, 4) }}
                            @endif
                        </td>
                        <td class="text-right"><strong>{{ number_format($movimiento->cantidadafter, 4) }}</strong></td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="4" class="text-right"><strong>RESUMEN DEL PRODUCTO:</strong></td>
                    <td class="text-right"><strong>-</strong></td>
                    <td class="text-right"><strong>{{ number_format($grupo['total_cantidad'], 4) }}</strong></td>
                    <td class="text-right"><strong>{{ number_format($grupo['cantidad_final'], 4) }}</strong></td>
                </tr>
            </tbody>
        </table>
        
        @if(!$loop->last)
            <div style="margin-bottom: 20px;"></div>
        @endif
    @endforeach

    <div class="footer">
        <p>Reporte generado el {{ date('d/m/Y H:i:s') }}</p>
        <p>Total de productos procesados: {{ $totalProductos }} | Total de movimientos: {{ $totalMovimientos }}</p>
    </div>
</body>
</html>

