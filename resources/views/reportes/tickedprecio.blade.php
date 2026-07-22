<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Etiqueta - {{ $codigo_barras }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        @page {
            size: 57mm 44mm;
            margin: 0;
        }

        html, body {
            width: 57mm;
            height: 44mm;
            overflow: hidden;          /* clave: evita que el documento genere una 2ª página en blanco */
            background: #fff;
        }

        #divEtiqueta {
            width: 57mm;
            height: 44mm;
            padding: 2.5mm 3mm 2mm;
            overflow: hidden;
            font-family: Arial, Helvetica, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: space-between;
        }

        .etiqueta-barcode {
            width: 100%;
            display: flex;
            justify-content: flex-start;
            flex-shrink: 0;
        }

        .etiqueta-barcode svg {
            width: 48mm;
            height: auto;
            max-height: 15mm;
        }

        .etiqueta-descripcion {
            font-size: 7.5pt;
            font-weight: 600;
            text-align: left;
            line-height: 1.15;
            width: 100%;
            overflow: hidden;
            display: block;
            max-height: 17.5pt; /* ~2 líneas */
            padding: 0 1mm 0 0.5mm;
        }

        .etiqueta-precio {
            font-size: 20pt;
            font-weight: 900;
            text-align: left;
            letter-spacing: 0.5mm;
            width: 100%;
            flex-shrink: 0;
            line-height: 1;
            border-top: 0.3mm solid #000;
            padding-top: 1mm;
        }

        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div id="divEtiqueta">
        <div class="etiqueta-barcode">
            <svg id="barcode"></svg>
        </div>
        <div class="etiqueta-descripcion">{{ substr($descripcion, 0, 60) }}</div>
        <div class="etiqueta-precio">{{ $pu }}</div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var codigoBarras = @json($codigo_barras);

            if (codigoBarras) {
                try {
                    JsBarcode("#barcode", codigoBarras, {
                        format: "CODE128",
                        width: 1.4,
                        height: 28,
                        displayValue: true,
                        fontSize: 9,
                        font: "Arial",
                        margin: 0,
                        textMargin: 1
                    });
                } catch (e) {
                    document.getElementById('barcode').style.display = 'none';
                }
            } else {
                document.getElementById('barcode').style.display = 'none';
            }

            setTimeout(function () { window.print(); }, 800);
        });

        window.onafterprint = function () { window.close(); };
        window.onfocus = function () { setTimeout(function () { window.close(); }, 2500); };
    </script>
</body>
</html>
