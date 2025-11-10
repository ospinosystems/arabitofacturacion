<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket - {{ $inventario->warehouse->codigo }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        @page {
            size: 57mm 44mm;
            margin: 0;
        }
        
        html {
            width: 57mm;
            height: 44mm;
        }
        
        body {
            width: 57mm;
            height: 44mm;
            max-width: 57mm;
            max-height: 44mm;
            font-family: 'Courier New', monospace;
            font-size: 7px;
            line-height: 1.1;
            padding: 1.5mm;
            background: white;
            overflow: hidden;
        }
        
        .ticket {
            width: 100%;
            height: 100%;
            max-width: 54mm;
            display: flex;
            flex-direction: column;
        }
        
        .ubicacion-box {
            background: #000;
            color: #fff;
            padding: 1mm;
            text-align: center;
            margin-bottom: 0.5mm;
        }
        
        .ubicacion-codigo {
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 0.5px;
        }
        
        .barcode-container {
            text-align: center;
            margin: 0.5mm 0;
            padding: 0.5mm;
            background: white;
            border: 1px solid #000;
            max-width: 100%;
            overflow: hidden;
        }
        
        .barcode {
            width: 100%;
            max-width: 100%;
            height: 8mm;
            background: white;
        }
        
        .barcode-text {
            font-size: 6px;
            margin-top: 0.3mm;
            font-weight: bold;
        }
        
        .info-section {
            flex: 1;
            font-size: 7px;
        }
        
        .producto-nombre {
            font-weight: bold;
            margin-bottom: 0.5mm;
            line-height: 1.2;
            font-size: 7.5px;
        }
        
        .info-line {
            display: flex;
            justify-content: space-between;
            margin: 0.3mm 0;
            font-size: 6.5px;
        }
        
        .label {
            font-weight: bold;
        }
        
        .value {
            text-align: right;
        }
        
        .cantidad-box {
            background: #f5f5f5;
            border: 1px solid #000;
            padding: 1mm;
            text-align: center;
            margin-top: 0.5mm;
        }
        
        .cantidad-label {
            font-size: 5px;
        }
        
        .cantidad-valor {
            font-size: 12px;
            font-weight: bold;
        }
        
        .footer {
            text-align: center;
            font-size: 5px;
            margin-top: 0.3mm;
        }
        
        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <div class="ticket">
        <!-- Ubicación -->
        <div class="ubicacion-box">
            <div class="ubicacion-codigo">{{ $inventario->warehouse->codigo }}</div>
        </div>
        
        <!-- Código de Barras -->
        <div class="barcode-container">
            <svg class="barcode" id="barcode"></svg>
            <div class="barcode-text">WH-{{ str_pad($inventario->id, 6, '0', STR_PAD_LEFT) }}</div>
        </div>
        
        <!-- Información del Producto -->
        <div class="info-section">
            <div class="producto-nombre">
                {{ \Illuminate\Support\Str::limit($inventario->inventario->descripcion, 65) }}
            </div>
            
            <div class="info-line">
                <span class="label">Cod:</span>
                <span class="value">{{ $inventario->inventario->codigo_barras }}</span>
            </div>
            
            @if($inventario->lote)
            <div class="info-line">
                <span class="label">Lote:</span>
                <span class="value">{{ $inventario->lote }} - {{ $inventario->fecha_vencimiento ? \Carbon\Carbon::parse($inventario->fecha_vencimiento)->format('d/m/Y') : '' }}</span>
            </div>
            @elseif($inventario->fecha_vencimiento)
            <div class="info-line">
                <span class="label">Vence:</span>
                <span class="value">{{ \Carbon\Carbon::parse($inventario->fecha_vencimiento)->format('d/m/Y') }}</span>
            </div>
            @endif
        </div>
        
        <!-- Cantidad -->
        <div class="cantidad-box">
            <div class="cantidad-label">CANT</div>
            <div class="cantidad-valor">
                @php
                    $cantidad = $inventario->cantidad;
                    $esEntero = floor($cantidad) == $cantidad;
                @endphp
                {{ $esEntero ? number_format($cantidad, 0) : number_format($cantidad, 2) }}
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            {{ \Carbon\Carbon::parse($inventario->fecha_entrada ?? now())->format('d/m/Y H:i') }}
        </div>
    </div>
    
    <!-- JsBarcode Library -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    
    <script>
        // Generar código de barras
        JsBarcode("#barcode", "WH{{ str_pad($inventario->id, 6, '0', STR_PAD_LEFT) }}", {
            format: "CODE128",
            width: 1.3,
            height: 30,
            displayValue: false,
            margin: 0,
            marginLeft: 0,
            marginRight: 0,
            fontSize: 10
        });
        
        // Auto-imprimir al cargar
        window.onload = function() {
            // Esperar un momento para que el código de barras se genere
            setTimeout(function() {
                window.print();
            }, 500);
        };
        
        // Cerrar ventana después de imprimir o cancelar
        window.onafterprint = function() {
            window.close();
        };
    </script>
</body>
</html>

