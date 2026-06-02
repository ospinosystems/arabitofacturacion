// @ts-check
/**
 * STRICT-ASSERTIONS 2026-05-27
 *
 * Helpers de alto nivel para validar post-state DB tras una operación.
 * Cada función:
 *   - llama al backend testing endpoint
 *   - valida con expect()
 *   - tira mensaje descriptivo si falla
 *
 * Los tests viejos hacían `expect([true,false]).toContain(estado)` — eso pasa
 * SIEMPRE. Estas helpers validan el estado REAL.
 *
 * Convención de tipos de pago_pedidos:
 *   1 = transferencia
 *   2 = débito
 *   3 = efectivo
 *   4 = crédito
 *   5 = biopago
 *   6 = vuelto
 */

const { expect } = require('@playwright/test');
const db = require('./db-helpers');

const TIPO = {
    TRANSFERENCIA: 1,
    DEBITO: 2,
    EFECTIVO: 3,
    CREDITO: 4,
    BIOPAGO: 5,
    VUELTO: 6,
};

/**
 * Valida que un pedido quedó facturado (estado=1) con la cantidad esperada de items.
 *
 * @param {object} opts
 * @param {number} opts.idPedido
 * @param {number} [opts.itemsCount] — espera exactamente esta cantidad de items
 * @param {number} [opts.totalUsd] — valida pedidos.subtotal o monto contra esto (tolerancia 0.01)
 * @param {boolean} [opts.permitirSinItems=false] — para refund puro (cases B/D)
 */
async function assertPedidoFacturado(page, opts) {
    const { idPedido, itemsCount, totalUsd, permitirSinItems = false } = opts;
    const data = await db.getPedidoFull(page, idPedido);

    expect(data.pedido, `Pedido #${idPedido} no existe en BD`).toBeTruthy();
    expect(data.pedido.estado, `Pedido #${idPedido} estado=${data.pedido.estado} (esperado 1=facturado)`).toBe(1);

    if (typeof itemsCount === 'number') {
        expect(data.items_count, `Pedido #${idPedido} tiene ${data.items_count} items (esperado ${itemsCount})`).toBe(itemsCount);
    } else if (!permitirSinItems) {
        expect(data.items_count, `Pedido #${idPedido} sin items — usa permitirSinItems:true si es refund puro`).toBeGreaterThan(0);
    }

    if (typeof totalUsd === 'number') {
        const tot = parseFloat(data.pedido.total ?? data.pedido.subtotal ?? '0');
        expect(Math.abs(tot - totalUsd), `Pedido #${idPedido} total=${tot} != esperado ${totalUsd}`).toBeLessThan(0.05);
    }

    return data;
}

/**
 * Valida que existe EXACTAMENTE una fila en pago_pedidos de cierto tipo y monto.
 *
 * @param {object} opts
 * @param {number} opts.idPedido
 * @param {number} opts.tipo — usa TIPO.*
 * @param {number} [opts.montoEsperado] — valida con tolerancia ±0.05
 * @param {number} [opts.imborrable] — 0 o 1
 * @param {number} [opts.count=1] — cuántas filas esperar (default 1)
 */
async function assertPagoExiste(page, opts) {
    const { idPedido, tipo, montoEsperado, imborrable, count = 1 } = opts;
    const data = await db.getPagos(page, { id_pedido: idPedido, tipo });

    expect(
        data.rows.length,
        `Pedido #${idPedido} esperaba ${count} pago(s) tipo=${tipo}, encontró ${data.rows.length}`
    ).toBe(count);

    if (typeof montoEsperado === 'number' && data.rows.length > 0) {
        const monto = parseFloat(data.rows[0].monto);
        expect(
            Math.abs(monto - montoEsperado),
            `Pago tipo=${tipo} monto=${monto} != esperado ${montoEsperado} (pedido #${idPedido})`
        ).toBeLessThan(0.05);
    }

    if (typeof imborrable === 'number' && data.rows.length > 0) {
        expect(
            Number(data.rows[0].imborrable),
            `Pago tipo=${tipo} imborrable=${data.rows[0].imborrable} != esperado ${imborrable} (pedido #${idPedido})`
        ).toBe(imborrable);
    }

    return data.rows;
}

/**
 * Valida la suma de filas de pago_pedidos por tipo. Útil para multipago y sobrante.
 *
 * @param {object} opts
 * @param {number} opts.idPedido
 * @param {{[tipo:number]: number}} opts.sumasEsperadas — { 1: -50, 3: 100 }
 */
async function assertSumasPagosPorTipo(page, opts) {
    const { idPedido, sumasEsperadas } = opts;
    const data = await db.getPagos(page, { id_pedido: idPedido });

    const sumas = {};
    for (const fila of data.rows) {
        sumas[fila.tipo] = (sumas[fila.tipo] || 0) + parseFloat(fila.monto);
    }

    for (const [tipo, esperado] of Object.entries(sumasEsperadas)) {
        const real = sumas[tipo] || 0;
        expect(
            Math.abs(real - esperado),
            `Pedido #${idPedido} suma de tipo=${tipo}: ${real} != esperado ${esperado}. Filas: ${JSON.stringify(data.rows)}`
        ).toBeLessThan(0.05);
    }
}

/**
 * Valida que pos_operaciones_rechazadas tiene una fila nueva (últimos 5 minutos).
 *
 * @param {object} opts
 * @param {string} [opts.numeroOrdenContiene] — substring para narrow match (ej id_pedido stringificado)
 * @param {number} [opts.countMinimo=1]
 */
async function assertPosRechazadoRegistrado(page, opts = {}) {
    const { numeroOrdenContiene, countMinimo = 1 } = opts;
    const data = await db.getPosRechazadas(page, {
        numero_orden_contiene: numeroOrdenContiene,
        since_minutes: 5,
    });
    expect(
        data.count,
        `Esperaba ≥${countMinimo} fila(s) en pos_operaciones_rechazadas (substr: ${numeroOrdenContiene}), encontró ${data.count}`
    ).toBeGreaterThanOrEqual(countMinimo);
    return data.rows;
}

/**
 * Valida que existe ref en pagos_referencias para un pedido.
 *
 * @param {object} opts
 * @param {number} opts.idPedido
 * @param {string} [opts.descripcion] — match exacto
 * @param {string} [opts.banco]
 * @param {string} [opts.estatusEsperado] — 'pendiente' | 'aprobada'
 * @param {number} [opts.count=1]
 */
async function assertRefExiste(page, opts) {
    const { idPedido, descripcion, banco, estatusEsperado, count = 1 } = opts;
    const data = await db.getRefs(page, { id_pedido: idPedido, descripcion, banco });

    expect(
        data.rows.length,
        `Pedido #${idPedido} esperaba ${count} ref(s) (desc=${descripcion}, banco=${banco}), encontró ${data.rows.length}`
    ).toBe(count);

    if (estatusEsperado && data.rows.length > 0) {
        expect(
            data.rows[0].estatus,
            `Ref del pedido #${idPedido} estatus=${data.rows[0].estatus} != esperado ${estatusEsperado}`
        ).toBe(estatusEsperado);
    }

    return data.rows;
}

/**
 * Valida que NO existe ninguna ref en pagos_referencias para esos criterios.
 * Útil para verificar limpieza o estado inicial.
 */
async function assertRefNoExiste(page, opts) {
    const { idPedido, descripcion, banco } = opts;
    const data = await db.getRefs(page, { id_pedido: idPedido, descripcion, banco });
    expect(
        data.rows.length,
        `Esperaba 0 refs (id_pedido=${idPedido}, desc=${descripcion}, banco=${banco}), encontró ${data.rows.length}`
    ).toBe(0);
}

module.exports = {
    TIPO,
    assertPedidoFacturado,
    assertPagoExiste,
    assertSumasPagosPorTipo,
    assertPosRechazadoRegistrado,
    assertRefExiste,
    assertRefNoExiste,
};
