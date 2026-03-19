@extends('layouts.app')
@section('tittle'," Mucho más que una ferreteria")
@section('content')

<div id="divEtiqueta">
    <div class="etiqueta-barcode">
        <svg id="barcode"></svg>
    </div>
    <div class="etiqueta-descripcion">{{ substr($descripcion, 0, 60) }}</div>
    <div class="etiqueta-precio">{{ $pu }}</div>
</div>

<style>
    @media print {
        @page {
            size: 57mm 44mm;
            margin: 0;
        }

        html, body {
            width: 100% !important;
            height: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            overflow: hidden !important;
            min-width: 0 !important;
        }

        #app, .modal, .modal-backdrop, .modal-dialog, nav, header, footer {
            display: none !important;
        }

        #divEtiqueta {
            width: 100% !important;
            height: 100% !important;
            margin: 0 !important;
            padding: 3mm 3.5mm 2.5mm !important;
            position: relative !important;
        }

        .etiqueta-barcode svg {
            width: 90% !important;
            max-height: 34% !important;
        }

        .etiqueta-descripcion {
            font-size: 8pt !important;
        }

        .etiqueta-precio {
            font-size: 22pt !important;
        }
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
        box-sizing: border-box;
    }

    .etiqueta-barcode {
        width: 100%;
        display: flex;
        justify-content: center;
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
@endsection
