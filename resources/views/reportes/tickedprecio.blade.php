@extends('layouts.app')
@section('tittle'," Mucho más que una ferreteria")
@section('content')

<div id="divEtiqueta">
    <div class="ticket-container">
        <!-- Código de barras -->
        <div class="codigo-barras">{{ $codigo_barras }}</div>
        
        <div class="codigo-barras-visual">
            <svg viewBox="0 0 100 25" preserveAspectRatio="none">
                <rect x="0" y="0" width="3" height="25" fill="#000"/>
                <rect x="5" y="0" width="2" height="25" fill="#000"/>
                <rect x="9" y="0" width="4" height="25" fill="#000"/>
                <rect x="15" y="0" width="2" height="25" fill="#000"/>
                <rect x="19" y="0" width="3" height="25" fill="#000"/>
                <rect x="24" y="0" width="2" height="25" fill="#000"/>
                <rect x="28" y="0" width="5" height="25" fill="#000"/>
                <rect x="35" y="0" width="2" height="25" fill="#000"/>
                <rect x="39" y="0" width="3" height="25" fill="#000"/>
                <rect x="44" y="0" width="2" height="25" fill="#000"/>
                <rect x="48" y="0" width="4" height="25" fill="#000"/>
                <rect x="54" y="0" width="2" height="25" fill="#000"/>
                <rect x="58" y="0" width="3" height="25" fill="#000"/>
                <rect x="63" y="0" width="5" height="25" fill="#000"/>
                <rect x="70" y="0" width="2" height="25" fill="#000"/>
                <rect x="74" y="0" width="3" height="25" fill="#000"/>
                <rect x="79" y="0" width="2" height="25" fill="#000"/>
                <rect x="83" y="0" width="4" height="25" fill="#000"/>
                <rect x="89" y="0" width="2" height="25" fill="#000"/>
                <rect x="93" y="0" width="3" height="25" fill="#000"/>
                <rect x="97" y="0" width="3" height="25" fill="#000"/>
            </svg>
        </div>

        <!-- Descripción del producto -->
        <div class="descripcion">{{ mb_strimwidth($descripcion, 0, 70, '...') }}</div>

        <!-- Precio -->
        <div class="precio">
            <span class="monto">{{ $pu }}</span>
        </div>
    </div>
</div>

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

    body {
        margin: 0;
        padding: 0;
    }

    #divEtiqueta {
        width: 57mm;
        height: 44mm;
        overflow: hidden;
        background: #fff;
        position: relative;
        page-break-after: always;
    }

    .ticket-container {
        width: 100%;
        height: 100%;
        padding: 2mm 2.5mm;
        display: flex;
        flex-direction: column;
        justify-content: space-around;
        position: relative;
        border: 1.5px solid #000;
    }

    /* Código de barras */
    .codigo-barras {
        font-family: 'Courier New', monospace;
        font-size: 7pt;
        font-weight: bold;
        color: #000;
        text-align: center;
        letter-spacing: 0.8pt;
        margin-bottom: 0.3mm;
    }

    .codigo-barras-visual {
        width: 90%;
        height: 7mm;
        margin: 0 auto 1mm;
        background: #fff;
        padding: 0.3mm;
        border: 1px solid #000;
    }

    .codigo-barras-visual svg {
        width: 100%;
        height: 100%;
    }

    /* Descripción */
    .descripcion {
        font-family: 'Arial', sans-serif;
        font-size: 11pt;
        font-weight: 700;
        color: #000;
        text-align: center;
        line-height: 1.15;
        word-wrap: break-word;
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        padding: 2mm 1.5mm;
        border-top: 1px solid #000;
        border-bottom: 1px solid #000;
        margin: 1.5mm 0;
        min-height: 12mm;
        max-height: 12mm;
    }

    /* Precio */
    .precio {
        font-family: 'Arial Black', 'Arial', sans-serif;
        color: #000;
        display: flex;
        align-items: baseline;
        justify-content: center;
        line-height: 1;
        padding: 1.5mm 0;
        border: 2px solid #000;
        background: #fff;
        min-height: 14mm;
    }

    .simbolo {
        font-size: 16pt;
        font-weight: 900;
        margin-right: 1.5mm;
    }

    .monto {
        font-size: 26pt;
        font-weight: 900;
        letter-spacing: 0.8pt;
    }

    /* Estilos de impresión */
    @media print {
        body {
            margin: 0 !important;
            padding: 0 !important;
        }

        #divEtiqueta {
            border: none;
        }

        .ticket-container {
            border: 1.5px solid #000;
        }

        .codigo-barras-visual {
            border: 1px solid #000;
        }

        .descripcion {
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
        }

        .precio {
            border: 2px solid #000;
        }
    }
</style>

<script>
    // Esperar a que se cargue completamente antes de imprimir
    window.addEventListener('load', function() {
        setTimeout(() => {
            window.print();
        }, 1500);
    });

    // Cerrar después de imprimir o cancelar
    setTimeout(() => {
        window.close();
    }, 4000);

    window.onfocus = function() {
        setTimeout(function() {
            window.close();
        }, 2000);
    }
</script>
@endsection




