<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket - {{ $producto->codigo_barras }}</title>
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
            font-size: 10px;
            line-height: 1.2;
            padding: 2mm;
            background: white;
            overflow: hidden;
        }
        
        .ticket {
            width: 100%;
            height: 100%;
            max-width: 53mm;
            display: flex;
            flex-direction: column;
        }
        
        .barcode-container {
            text-align: center;
            margin: 1mm 0;
            padding: 1mm;
            background: white;
            border: 1px solid #000;
            max-width: 100%;
            overflow: hidden;
        }
        
        .barcode {
            width: 100%;
            max-width: 100%;
            height: 12mm;
            background: white;
        }
        
        .barcode-text {
            font-size: 11px;
            margin-top: 0.5mm;
            font-weight: bold;
        }
        
        .info-section {
            flex: 1;
            font-size: 10px;
        }
        
        .producto-nombre {
            font-weight: bold;
            margin-bottom: 1mm;
            line-height: 1.3;
            font-size: 13px;
        }
        
        .info-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 0.5mm 0;
        }
        
        .label {
            font-weight: bold;
            font-size: 7px;
        }
        
        .value {
            text-align: right;
            font-size: 14px;
            font-weight: bold;
        }
        
        .footer {
            text-align: center;
            font-size: 7px;
            margin-top: 0.5mm;
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
        <!-- Código de Barras -->
        <div class="barcode-container">
            <svg class="barcode" id="barcode"></svg>
            <div class="barcode-text">{{ $producto->codigo_barras }}</div>
        </div>
        
        <!-- Información del Producto -->
        <div class="info-section">
            <div class="producto-nombre">
                {{ \Illuminate\Support\Str::limit($producto->descripcion, 65) }}
            </div>
            
            <div class="info-line">
                <span class="label">Cód. Barras:</span>
                <span class="value">{{ $producto->codigo_barras }}</span>
            </div>
            
            @if($producto->codigo_proveedor)
            <div class="info-line">
                <span class="label">Cód. Proveedor:</span>
                <span class="value">{{ $producto->codigo_proveedor }}</span>
            </div>
            @endif
            
            @if($producto->marca)
            <div class="info-line">
                <span class="label">Marca:</span>
                <span class="value">{{ $producto->marca }}</span>
            </div>
            @endif
        </div>
    </div>
    
    <!-- JsBarcode Library -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    
    <script>
        // Generar código de barras
        JsBarcode("#barcode", "{{ $producto->codigo_barras }}", {
            format: "CODE128",
            width: 1.7,
            height: 40,
            displayValue: false,
            margin: 0,
            marginLeft: 0,
            marginRight: 0,
            fontSize: 14
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

