<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bultos - {{ $sucursal }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        @page {
            size: 57mm 44mm;
            margin: 0;
        }

        html, body {
            width: 57mm;
            background: #fff;
            font-family: Arial, Helvetica, sans-serif;
        }

        .etiqueta {
            width: 57mm;
            height: 44mm;
            padding: 3mm;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        /* Corte SOLO entre etiquetas: la última no lleva salto → sin página en blanco al final. */
        .etiqueta { page-break-after: always; break-after: page; }
        .etiqueta:last-child { page-break-after: auto; break-after: auto; }

        .cab { text-align: center; }
        .sucursal { font-size: 12pt; font-weight: bold; display: block; }
        .id-pedido { font-size: 10pt; font-weight: bold; display: block; margin-top: 1mm; }
        .numbultos { font-size: 22pt; font-weight: bold; display: block; margin-top: 2mm; }
        .pie { display: flex; justify-content: space-between; font-size: 8pt; }
        .pie b { font-weight: bold; }

        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    @foreach ($bultos as $num_bulto => $e)
        <div class="etiqueta">
            <div class="cab">
                <span class="sucursal">{{ $sucursal }}</span>
                @if (!empty($id_pedido_etiqueta))
                    <span class="id-pedido">Pedido #{{ $id_pedido_etiqueta }}</span>
                @endif
                <span class="numbultos">{{ $num_bulto }} / {{ $total }}</span>
            </div>
            <div class="pie">
                <span>ORIGEN: <b>{{ $origen }}</b></span>
                <span><b>{{ $fecha }}</b></span>
            </div>
        </div>
    @endforeach

    <script>
        window.onload = function () {
            setTimeout(function () { window.print(); }, 500);
        };
        window.onafterprint = function () { window.close(); };
        window.onfocus = function () { setTimeout(function () { window.close(); }, 3000); };
    </script>
</body>
</html>
