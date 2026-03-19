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
        body * {
            visibility: hidden !important;
        }
        #divEtiqueta, #divEtiqueta * {
            visibility: visible !important;
        }
        #divEtiqueta {
            position: fixed;
            top: 0;
            left: 0;
            margin: 0;
        }
        @page {
            size: 57mm 44mm;
            margin: 0;
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
        align-items: flex-start;
        justify-content: space-between;
        box-sizing: border-box;
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
        /* Firefox no respeta bien -webkit-line-clamp; forzamos recorte con altura máxima */
        display: block;
        max-height: 17.5pt; /* 2 lineas aprox: 7.5pt * 1.15 * 2 */
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
