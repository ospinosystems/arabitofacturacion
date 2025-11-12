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
        
        html, body {
            width: 57mm;
            height: 44mm;
            margin: 0;
            padding: 0;
            font-family: 'Courier New', monospace;
            background: white;
            overflow: hidden;
        }
        
        .ticket {
            width: 57mm;
            height: 44mm;
            padding: 1mm;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border: 0.5mm solid #000;
        }
        
        .header {
            text-align: center;
            margin-bottom: 0.5mm;
        }
        
        .ubicacion-codigo {
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 0.3px;
            background: #000;
            color: #fff;
            padding: 0.8mm 1mm;
            display: inline-block;
        }
        
        .barcode-wrapper {
            text-align: center;
            margin: 0.5mm 0;
            padding: 1mm;
            border: 1.5mm solid #000;
            background: white;
            position: relative;
        }
        
        .barcode {
            width: 100%;
            height: auto;
            max-height: 12mm;
            display: block;
            margin: 0 auto;
        }
        
        .barcode-text {
            font-size: 5.5px;
            margin-top: 0.5mm;
            font-weight: bold;
            letter-spacing: 0.2px;
        }
        
        .info-section {
            font-size: 5.5px;
            line-height: 1.1;
            margin: 0.3mm 0;
        }
        
        .producto-nombre {
            font-weight: bold;
            font-size: 6px;
            margin-bottom: 0.3mm;
            line-height: 1.1;
            word-wrap: break-word;
        }
        
        .info-line {
            display: flex;
            justify-content: space-between;
            margin: 0.2mm 0;
            font-size: 5px;
        }
        
        .label {
            font-weight: bold;
        }
        
        .value {
            text-align: right;
            font-family: 'Courier New', monospace;
        }
        
        .cantidad-box {
            background: #f0f0f0;
            border: 0.5mm solid #000;
            padding: 0.8mm;
            text-align: center;
            margin-top: 0.3mm;
        }
        
        .cantidad-label {
            font-size: 4.5px;
            font-weight: bold;
        }
        
        .cantidad-valor {
            font-size: 10px;
            font-weight: bold;
            margin-top: 0.2mm;
        }
        
        .footer {
            text-align: center;
            font-size: 4.5px;
            margin-top: 0.2mm;
        }
        
        @media print {
            html, body {
                width: 57mm;
                height: 44mm;
                margin: 0;
                padding: 0;
            }
            
            .ticket {
                width: 57mm;
                height: 44mm;
                margin: 0;
                padding: 1mm;
            }
            
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            @page {
                margin: 0;
                size: 57mm 44mm;
            }
        }
    </style>
</head>
<body>
    <div class="ticket">
        <!-- Header con Ubicación -->
        <div class="header">
            <div class="ubicacion-codigo">{{ $inventario->warehouse->codigo }}</div>
        </div>
        
        <!-- Código de Barras con Borde -->
        <div class="barcode-wrapper">
            <svg class="barcode" id="barcode"></svg>
            <div class="barcode-text">{{ $inventario->warehouse->codigo }}</div>
        </div>
        
        <!-- Información Compacta -->
        <div class="info-section">
            <div class="producto-nombre">
                {{ \Illuminate\Support\Str::limit($inventario->inventario->descripcion, 50) }}
            </div>
            
            <div class="info-line">
                <span class="label">Cod:</span>
                <span class="value">{{ \Illuminate\Support\Str::limit($inventario->inventario->codigo_barras, 15) }}</span>
            </div>
            
            @if($inventario->lote)
            <div class="info-line">
                <span class="label">Lote:</span>
                <span class="value">{{ \Illuminate\Support\Str::limit($inventario->lote, 12) }}</span>
            </div>
            @endif
        </div>
        
        <!-- Cantidad -->
        <div class="cantidad-box">
            <div class="cantidad-label">CANTIDAD</div>
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
            {{ \Carbon\Carbon::parse($inventario->fecha_entrada ?? now())->format('d/m/Y') }}
        </div>
    </div>
    
    <!-- JsBarcode Library -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    
    <script>
        // Generar código de barras con el código de ubicación
        JsBarcode("#barcode", "{{ $inventario->warehouse->codigo }}", {
            format: "CODE128",
            width: 1,
            height: 12,
            displayValue: false,
            margin: 2,
            marginLeft: 2,
            marginRight: 2,
            fontSize: 0,
            background: "#ffffff"
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
            setTimeout(function() {
                window.close();
            }, 100);
        };
    </script>
</body>
</html>

