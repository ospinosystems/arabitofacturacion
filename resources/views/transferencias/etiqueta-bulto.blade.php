<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Etiqueta {{ $bulto->codigo_barras }}</title>
    <style>
        @page { size: 57mm 44mm; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body { width: 57mm; font-family: Arial, sans-serif; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .etq { width: 57mm; height: 44mm; padding: 2mm; text-align: center; }
        .titulo { font-size: 11px; font-weight: bold; }
        .orden { font-size: 9px; color: #333; margin-bottom: 1mm; }
        .numero { font-size: 20px; font-weight: bold; line-height: 1; }
        svg.barcode { width: 100%; height: 13mm; }
        .codigo { font-family: monospace; font-size: 11px; font-weight: bold; letter-spacing: 1px; }
        .items { font-size: 8px; color: #444; }
    </style>
</head>
<body>
    <div class="etq">
        <div class="titulo">BULTO DE DESPACHO</div>
        <div class="orden">Orden #{{ $bulto->id_transferencia }} · Destino {{ $bulto->transferencia->id_destino ?? '—' }}</div>
        <div class="numero">N° {{ $bulto->numero }}</div>
        <svg class="barcode" id="barcode"></svg>
        <div class="codigo">{{ $bulto->codigo_barras }}</div>
        <div class="items">{{ $bulto->items->count() }} producto(s) · {{ number_format($bulto->items->sum('cantidad'), 0) }} u.</div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <script>
        JsBarcode("#barcode", "{{ $bulto->codigo_barras }}", { format: "CODE128", width: 1.4, height: 44, displayValue: false, margin: 0 });
        window.onload = function () { setTimeout(function () { window.print(); }, 400); };
        window.onafterprint = function () { window.close(); };
    </script>
</body>
</html>
