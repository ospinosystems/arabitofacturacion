<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Etiqueta</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            width: 57mm;
            height: 44mm;
            overflow: hidden;
        }

        @page {
            size: 57mm 44mm;
            margin: 0;
        }

        #divEtiqueta {
            width: 57mm;
            height: 44mm;
            padding: 2.5mm 3mm 2mm;
            overflow: hidden;
            font-family: Arial, Helvetica, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
        }

        .etiqueta-barcode {
            width: 100%;
            display: flex;
            justify-content: center;
            flex-shrink: 0;
            overflow: hidden;
        }

        .etiqueta-barcode svg {
            display: block;
            max-width: 100%;
            height: auto;
            max-height: 15mm;
        }

        .etiqueta-descripcion {
            font-size: 7.5pt;
            font-weight: 600;
            text-align: center;
            line-height: 1.15;
            width: 100%;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            padding: 0 1mm;
        }

        .etiqueta-precio {
            font-size: 20pt;
            font-weight: 900;
            text-align: center;
            letter-spacing: 0.5mm;
            width: 100%;
            flex-shrink: 0;
            line-height: 1;
            border-top: 0.3mm solid #000;
            padding-top: 1mm;
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

                var svg = document.getElementById('barcode');
                var origW = svg.getAttribute('width');
                var origH = svg.getAttribute('height');
                svg.setAttribute('viewBox', '0 0 ' + origW + ' ' + origH);
                svg.removeAttribute('width');
                svg.removeAttribute('height');

            } catch (e) {
                document.getElementById('barcode').style.display = 'none';
            }
        } else {
            document.getElementById('barcode').style.display = 'none';
        }

        setTimeout(function () {
            window.print();
        }, 1500);

        setTimeout(function () {
            window.close();
        }, 2500);

        window.onfocus = function () {
            setTimeout(function () { window.close(); }, 2500);
        };
    });
</script>

</body>
</html>
