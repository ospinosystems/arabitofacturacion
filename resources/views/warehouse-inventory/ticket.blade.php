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
            padding: 0.5mm;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border: 0.5mm solid #000;
        }
        
        .header {
            text-align: center;
            margin-bottom: 0.2mm;
        }
        
        .ubicacion-codigo {
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 0.1px;
            background: #000;
            color: #fff;
            padding: 0.4mm 0.6mm;
            display: inline-block;
        }
        
        .barcode-wrapper {
            text-align: center;
            margin: 0.2mm 0;
            padding: 0.8mm;
            border: 2.5mm solid #000 !important;
            background: white;
            position: relative;
            min-height: 10mm;
            max-height: 10mm;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            box-sizing: border-box;
        }
        
        .barcode {
            width: 100%;
            height: auto;
            max-height: 6mm;
            min-height: 6mm;
            display: block;
            margin: 0 auto;
        }
        
        .barcode-text {
            font-size: 4.5px;
            margin-top: 0.2mm;
            font-weight: bold;
            letter-spacing: 0.1px;
            font-family: 'Courier New', monospace;
        }
        
        .info-section {
            font-size: 4.5px;
            line-height: 1;
            margin: 0.15mm 0;
        }
        
        .producto-nombre {
            font-weight: bold;
            font-size: 5px;
            margin-bottom: 0.15mm;
            line-height: 1;
            word-wrap: break-word;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
        }
        
        .info-line {
            display: flex;
            justify-content: space-between;
            margin: 0.1mm 0;
            font-size: 4px;
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
            padding: 0.4mm;
            text-align: center;
            margin-top: 0.15mm;
        }
        
        .cantidad-label {
            font-size: 3.5px;
            font-weight: bold;
        }
        
        .cantidad-valor {
            font-size: 8px;
            font-weight: bold;
            margin-top: 0.1mm;
        }
        
        .footer {
            text-align: center;
            font-size: 3.5px;
            margin-top: 0.1mm;
        }
        
        @media print {
            html, body {
                width: 57mm !important;
                height: 44mm !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            
            .ticket {
                width: 57mm !important;
                height: 44mm !important;
                margin: 0 !important;
                padding: 0.5mm !important;
                border: 0.5mm solid #000 !important;
            }
            
            .barcode-wrapper {
                border: 2.5mm solid #000 !important;
                padding: 0.8mm !important;
                min-height: 10mm !important;
                max-height: 10mm !important;
                box-sizing: border-box !important;
            }
            
            .barcode {
                max-height: 6mm !important;
                min-height: 6mm !important;
            }
            
            body {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            @page {
                margin: 0 !important;
                size: 57mm 44mm !important;
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
        
        <!-- Código de Barras con Borde Grueso para Identificar Extremos -->
        <div class="barcode-wrapper">
            <svg class="barcode" id="barcode"></svg>
            <div class="barcode-text">{{ $inventario->warehouse->codigo }}</div>
        </div>
        
        <!-- Información Mínima -->
        <div class="info-section">
            <div class="producto-nombre">
                {{ \Illuminate\Support\Str::limit($inventario->inventario->descripcion, 35) }}
            </div>
            
            <div class="info-line">
                <span class="label">Cod:</span>
                <span class="value">{{ \Illuminate\Support\Str::limit($inventario->inventario->codigo_barras, 10) }}</span>
            </div>
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
            {{ \Carbon\Carbon::parse($inventario->fecha_entrada ?? now())->format('d/m/Y') }}
        </div>
    </div>
    
    <!-- JsBarcode Library -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    
    <script>
        // Generar código de barras con el código de ubicación - Compacto y optimizado para escaneo
        JsBarcode("#barcode", "{{ $inventario->warehouse->codigo }}", {
            format: "CODE128",
            width: 0.5,
            height: 6,
            displayValue: false,
            margin: 1.5,
            marginLeft: 1.5,
            marginRight: 1.5,
            fontSize: 0,
            background: "#ffffff",
            lineColor: "#000000"
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

