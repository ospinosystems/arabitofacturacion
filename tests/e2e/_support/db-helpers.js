// @ts-check
/**
 * DB-HELPERS 2026-05-27
 *
 * Wrappers sobre los endpoints de TestingAssertionsController. Cada función:
 *   - hace una llamada POST autenticada via page.request
 *   - retorna data parseada
 *   - NO hace assertions — el caller decide qué es válido
 *
 * Para assertions de alto nivel ver strict-assertions.js.
 */

const config = require('./config');

const baseUrl = () => config.arabitofacturacion.baseUrl;

async function getPedidoFull(page, id_pedido) {
    const r = await page.request.post(baseUrl() + '/testing/pedido-full', {
        data: { id_pedido },
    });
    if (!r.ok()) {
        throw new Error(`testing/pedido-full HTTP ${r.status()}: ${await r.text()}`);
    }
    return r.json();
}

async function getPagos(page, filtros = {}) {
    const r = await page.request.post(baseUrl() + '/testing/pagos', {
        data: filtros,
    });
    if (!r.ok()) throw new Error(`testing/pagos HTTP ${r.status()}`);
    return r.json();
}

async function getRefs(page, filtros = {}) {
    const r = await page.request.post(baseUrl() + '/testing/refs', {
        data: filtros,
    });
    if (!r.ok()) throw new Error(`testing/refs HTTP ${r.status()}`);
    return r.json();
}

async function getPosRechazadas(page, filtros = {}) {
    const r = await page.request.post(baseUrl() + '/testing/pos-rechazadas', {
        data: filtros,
    });
    if (!r.ok()) throw new Error(`testing/pos-rechazadas HTTP ${r.status()}`);
    return r.json();
}

async function getSolicitudesDescuento(page, id_pedido) {
    const r = await page.request.post(baseUrl() + '/testing/solicitudes-descuento', {
        data: { id_pedido },
    });
    if (!r.ok()) throw new Error(`testing/solicitudes-descuento HTTP ${r.status()}`);
    return r.json();
}

async function fixtureRefEnCentral(page, { banco, descripcion, monto, id_pedido = null }) {
    const r = await page.request.post(baseUrl() + '/testing/fixture-ref-central', {
        data: { banco, descripcion, monto, id_pedido },
    });
    if (!r.ok()) throw new Error(`testing/fixture-ref-central HTTP ${r.status()}`);
    return r.json();
}

async function cleanupRefs(page, id_pedido) {
    const r = await page.request.post(baseUrl() + '/testing/cleanup-refs', {
        data: { id_pedido },
    });
    if (!r.ok()) throw new Error(`testing/cleanup-refs HTTP ${r.status()}`);
    return r.json();
}

module.exports = {
    getPedidoFull,
    getPagos,
    getRefs,
    getPosRechazadas,
    getSolicitudesDescuento,
    fixtureRefEnCentral,
    cleanupRefs,
};
