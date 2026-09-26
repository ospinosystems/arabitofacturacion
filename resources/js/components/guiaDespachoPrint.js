// Impresión de la GUÍA DE DESPACHO paginada.
//
// Cada hoja física lleva su propio número: número base (id del pedido, 8 dígitos) seguido del
// número de hoja, sin separador. Ej.: base 00109942 → hoja 1 "001099421", hoja 2 "001099422".
// Para poder numerar así, la paginación NO se deja al navegador: el HTML generado mide las filas
// ya renderizadas (mismo ancho que la hoja impresa), las reparte en páginas según la altura útil
// y arma cada página con su encabezado (espacio superior en blanco para la forma libre preimpresa,
// bloque Cliente/Origen a la izquierda, número y emisión a la derecha) y la fila de columnas.
// Las firmas van solo en la última hoja.
//
// Tamaños: la tabla de productos puede imprimirse en varios tamaños de letra (el encabezado de la
// hoja no cambia). En modo "automatico" se elige la letra más grande con la que TODA la orden entra
// en una sola hoja; si no entra en ninguna, se usa el tamaño mínimo en varias hojas.
//
// Se usa desde la Torre de transferencias (TransferenciasModule.jsx).

const MM_A_PX = 96 / 25.4;
const CARTA_ANCHO_MM = 215.9;
const CARTA_ALTO_MM = 279.4;

// Opciones del selector "Tamaño guía". El orden de las claves es el orden del selector y, para el
// modo automático, el orden de prueba (de mayor a menor letra) es el de `ORDEN_AUTO`.
export const TAMANOS_GUIA = {
    automatico: { etiqueta: 'Automático', auto: true },
    normal:     { etiqueta: 'Normal',     fontSize: '16px', padding: '6px 10px' },
    mediano:    { etiqueta: 'Mediano',    fontSize: '14px', padding: '4px 8px' },
    compacto:   { etiqueta: 'Compacto',   fontSize: '12px', padding: '2px 6px' },
    minimo:     { etiqueta: 'Mínimo',     fontSize: '11px', padding: '1px 5px' },
};
export const TAMANO_GUIA_DEFAULT = 'normal';
const ORDEN_AUTO = ['normal', 'mediano', 'compacto', 'minimo'];

/** "40mm 5mm 35mm 5mm" → { top, right, bottom, left } en mm. */
const parseMargenesMm = (s) => {
    const p = String(s || '').trim().split(/\s+/).map(v => parseFloat(v) || 0);
    const [t, r = t, b = t, l = r] = p;
    return { top: t, right: r, bottom: b, left: l };
};

const escHtml = (s) => String(s == null ? '' : s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

/** Cantidad para la tabla: entera sin decimales, si no con 2. */
export const formatearCantidadGuia = (cantidad) => {
    const cant = Number(cantidad);
    if (!Number.isFinite(cant)) return '0';
    return cant % 1 === 0 ? String(cant) : cant.toFixed(2);
};

/**
 * Construye el documento HTML completo (con el script que pagina e imprime).
 * @param {object} o
 * @param {string} o.numeroBase   Número de guía sin el sufijo de hoja (ej. "00109942").
 * @param {string} o.tituloVentana Título del documento/ventana.
 * @param {string} o.fechaEmision  "dd/mm/aaaa".
 * @param {string} o.clienteRazon
 * @param {string} o.clienteRif
 * @param {string} o.clienteDir
 * @param {string} o.origenNombre
 * @param {Array<{cod:string, codProv:string, desc:string, cant:string}>} o.filas
 * @param {string} o.margenesMm   Shorthand CSS en mm: "TOP RIGHT BOTTOM LEFT".
 * @param {string} [o.tamano]     Clave de TAMANOS_GUIA ('automatico' | 'normal' | 'mediano' | 'compacto' | 'minimo').
 */
export const construirHtmlGuiaDespacho = (o) => {
    const m = parseMargenesMm(o.margenesMm);
    const anchoPx = Math.floor((CARTA_ANCHO_MM - m.left - m.right) * MM_A_PX);
    const altoPx = Math.floor((CARTA_ALTO_MM - m.top - m.bottom) * MM_A_PX);
    const tamano = TAMANOS_GUIA[o.tamano] ? o.tamano : TAMANO_GUIA_DEFAULT;
    // Tamaños a probar, en orden: en automático todos (de mayor a menor); si no, solo el elegido.
    const candidatos = TAMANOS_GUIA[tamano].auto ? ORDEN_AUTO : [tamano];
    // Una clase CSS por tamaño (.t-normal, .t-mediano, ...) para que el script pueda cambiar de
    // tamaño y volver a medir sin regenerar el documento.
    const cssTamanos = ORDEN_AUTO.map(k => {
        const t = TAMANOS_GUIA[k];
        return `.t-${k} table{font-size:${t.fontSize};} .t-${k} th,.t-${k} td{padding:${t.padding};}`;
    }).join(' ');
    // Datos de filas embebidos como JSON; se escapa "<" para que nunca cierre el <script>.
    const filasJson = JSON.stringify(o.filas || []).replace(/</g, '\\u003c');

    return `<!DOCTYPE html><html><head><meta charset="utf-8"><title>${escHtml(o.tituloVentana)}</title>
<style>
@page{size:letter portrait;margin:${escHtml(o.margenesMm)};}
html,body{margin:0;padding:0;} body{font-family:sans-serif;}
table{border-collapse:collapse;width:100%;} th,td{border:1px solid #ccc;text-align:left;} th{background:#f3f4f6;}
${cssTamanos}
.pagina{width:${anchoPx}px;page-break-after:always;} .pagina:last-child{page-break-after:auto;}
.enc{position:relative;height:180px;line-height:1.25;}
.enc .header{position:absolute;left:0;bottom:1rem;margin:0;}
.enc .titulo-guia{position:absolute;right:0;bottom:1rem;margin:0;text-align:right;font-weight:bold;}
.firmas-wrap{overflow:hidden;} .firmas{margin-top:2rem;display:flex;gap:2rem;justify-content:center;width:100%;}
.medir{position:absolute;left:-10000px;top:0;visibility:hidden;}
</style></head><body>
<template id="tpl-enc">
<div class="enc">
    <div class="header">
        <div><strong>Cliente</strong></div>
        <div>Razón Social: ${escHtml(o.clienteRazon)}</div>
        <div>RIF: ${escHtml(o.clienteRif)}</div>
        <div>Dirección: ${escHtml(o.clienteDir)}</div>
        <div style="margin-top:0.5rem;"><strong>Origen:</strong> ${escHtml(o.origenNombre)}</div>
    </div>
    <div class="titulo-guia">Guía de Despacho N°: ${escHtml(o.numeroBase)}__PAG__<br>Emisión-${escHtml(o.fechaEmision)}</div>
</div>
</template>
<template id="tpl-cols"><tr><th>#</th><th>Código</th><th>Cód. proveedor</th><th>Descripción</th><th style="text-align:right">Cantidad</th></tr></template>
<template id="tpl-firmas">
<div class="firmas-wrap"><div class="firmas">
    <div><div style="border-top:1px solid #333;padding-top:4px;width:140px;text-align:center;">Firma del Despachador</div></div>
    <div><div style="border-top:1px solid #333;padding-top:4px;width:140px;text-align:center;">Firma del Receptor</div></div>
</div></div>
</template>
<div id="hojas"></div>
<script>
(function () {
    var FILAS = ${filasJson};
    var ALTO_UTIL = ${altoPx};
    var ANCHO_UTIL = ${anchoPx};
    var CANDIDATOS = ${JSON.stringify(candidatos)};
    var SEGURIDAD = 30; // px de holgura para que ninguna hoja se pase por redondeos.
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
    function fila(f, i) {
        return '<tr><td>' + (i + 1) + '</td><td>' + esc(f.cod) + '</td><td>' + esc(f.codProv) + '</td><td>' + esc(f.desc) + '</td><td style="text-align:right">' + esc(f.cant) + '</td></tr>';
    }
    var tplEnc = document.getElementById('tpl-enc').innerHTML;
    var cols = document.getElementById('tpl-cols').innerHTML;
    var firmas = document.getElementById('tpl-firmas').innerHTML;
    function enc(nroHoja) { return tplEnc.replace('__PAG__', String(nroHoja)); }
    // Anchos de columna fijos (px). Sin esto cada hoja repartiría las columnas según sus propias
    // filas (p. ej. "#" de 1 o 2 dígitos) y las alturas medidas no coincidirían con las impresas.
    function tabla(filasHtml, anchosCol) {
        var colgroup = anchosCol ? '<colgroup>' + anchosCol.map(function (w) { return '<col style="width:' + w + 'px">'; }).join('') + '</colgroup>' : '';
        var estilo = anchosCol ? ' style="table-layout:fixed"' : '';
        return '<table' + estilo + '>' + colgroup + '<thead>' + cols + '</thead><tbody>' + filasHtml + '</tbody></table>';
    }
    var todasHtml = FILAS.map(fila).join('');

    // Mide y pagina con un tamaño dado. Devuelve { tam, anchosCol, paginas } (paginas = índices de filas).
    function paginar(tam) {
        var medidor = document.createElement('div');
        medidor.className = 'pagina medir t-' + tam;
        document.body.appendChild(medidor);

        // 1) Anchos de columna: primero la tabla completa con layout automático, para obtener anchos
        //    que acomodan todo el contenido.
        medidor.innerHTML = enc(1) + tabla(todasHtml, null) + firmas;
        var filaRef = medidor.querySelector('tbody tr') || medidor.querySelector('thead tr');
        var anchosCol = Array.prototype.map.call(filaRef.children, function (c) { return c.offsetWidth; });
        // Reparto igual al de la impresión original: #, Código, Cód. proveedor y Cantidad al ancho de
        // su contenido (los títulos de columna se parten si hace falta) y TODO el resto para Descripción.
        if (FILAS.length) {
            medidor.innerHTML = '<table style="width:auto;table-layout:auto"><tbody>' + todasHtml + '</tbody></table>';
            var mc = Array.prototype.map.call(medidor.querySelector('tbody tr').children, function (c) { return c.offsetWidth; });
            // Ancho mínimo de cada título de columna (su palabra más larga), para que no se desborde.
            medidor.innerHTML = '<table style="width:1px;table-layout:auto"><thead>' + cols + '</thead></table>';
            var mh = Array.prototype.map.call(medidor.querySelector('thead tr').children, function (c) { return c.offsetWidth; });
            for (var k = 0; k < mc.length; k++) { if (mh[k] > mc[k]) { mc[k] = mh[k]; } }
            var resto = ANCHO_UTIL - (mc[0] + mc[1] + mc[2] + mc[4]);
            if (resto >= 200) { anchosCol = [mc[0], mc[1], mc[2], resto, mc[4]]; }
        }

        // 2) Alturas reales con esos anchos fijos (igual que saldrá cada hoja).
        medidor.innerHTML = enc(1) + tabla(todasHtml, anchosCol) + firmas;
        var hEnc = medidor.querySelector('.enc').offsetHeight;
        var hCols = medidor.querySelector('thead').offsetHeight;
        var hFirmas = medidor.querySelector('.firmas-wrap').offsetHeight;
        var alturas = Array.prototype.map.call(medidor.querySelectorAll('tbody tr'), function (tr) { return tr.offsetHeight; });
        document.body.removeChild(medidor);

        // 3) Repartir filas en páginas. La última página debe dejar lugar para las firmas.
        var presupuesto = ALTO_UTIL - hEnc - hCols - SEGURIDAD;
        var paginas = [];
        var actual = [];
        var usado = 0;
        for (var i = 0; i < FILAS.length; i++) {
            var extra = (i === FILAS.length - 1) ? hFirmas : 0;
            if (actual.length && usado + alturas[i] + extra > presupuesto) {
                paginas.push(actual);
                actual = [];
                usado = 0;
            }
            actual.push(i);
            usado += alturas[i];
        }
        paginas.push(actual);
        return { tam: tam, anchosCol: anchosCol, paginas: paginas };
    }

    // Automático: el primer tamaño (de mayor a menor) con el que todo entra en UNA hoja; si ninguno,
    // el último (el más chico) en varias hojas. Con tamaño fijo hay un solo candidato.
    var elegido = null;
    for (var c = 0; c < CANDIDATOS.length; c++) {
        elegido = paginar(CANDIDATOS[c]);
        if (elegido.paginas.length === 1) { break; }
    }

    // 4) Armar cada hoja con su número propio (base + n° de hoja).
    var html = '';
    for (var p = 0; p < elegido.paginas.length; p++) {
        var filasHtml = elegido.paginas[p].map(function (idx) { return fila(FILAS[idx], idx); }).join('');
        var esUltima = (p === elegido.paginas.length - 1);
        html += '<div class="pagina t-' + elegido.tam + '">' + enc(p + 1) + tabla(filasHtml, elegido.anchosCol) + (esUltima ? firmas : '') + '</div>';
    }
    document.getElementById('hojas').innerHTML = html;
    window.__guiaPaginada = { hojas: elegido.paginas.length, tamano: elegido.tam };
})();
</script>
</body></html>`;
};

/** Abre la ventana, escribe la guía paginada e imprime. */
export const imprimirGuiaDespachoPaginada = (o) => {
    const ventana = window.open('', '_blank');
    if (!ventana) {
        alert('Habilitá las ventanas emergentes para poder imprimir la guía.');
        return false;
    }
    ventana.document.write(construirHtmlGuiaDespacho(o));
    ventana.document.close();
    ventana.focus();
    setTimeout(() => { ventana.print(); ventana.close(); }, 300);
    return true;
};
