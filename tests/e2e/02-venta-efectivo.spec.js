// @ts-check
const { test, expect } = require('@playwright/test');
const { setupCajero, login, abrirCaja, nuevoPedido, agregarProducto, facturar, inputDeMetodoPago, config } = require('./_support/helpers');
const { assertPedidoFacturado, assertPagoExiste, TIPO } = require('./_support/strict-assertions');

test.describe('Venta efectivo (STRICT)', () => {
    test('1 producto + efectivo USD 1 → estado true, pago_pedido tipo=3, sin console errors', async ({ page }) => {
        const watcher = await setupCajero(page);
        await login(page);
        await abrirCaja(page);
        const idPedido = await nuevoPedido(page);

        await agregarProducto(page, undefined, 1);

        await expect(page.locator('text=Sin productos aún')).toBeHidden({ timeout: 8000 });

        // Leer el clean_total real del pedido y pagar exactamente eso (sin sobrante)
        const ped = await (await page.request.post(config.arabitofacturacion.baseUrl + '/getPedido', {
            data: { id: idPedido },
        })).json();
        const totalUSD = parseFloat(ped.clean_total);
        expect(totalUSD, 'pedido sin total').toBeGreaterThan(0);

        const inputEfectivoUsd = inputDeMetodoPago(page, 'Efectivo USD');
        await inputEfectivoUsd.fill(String(totalUSD));

        const r = await facturar(page);
        const body = await r.json();

        // HARD: el endpoint TIENE que devolver éxito
        expect(body.estado, `setPagoPedido falló: ${body.msj}`).toBe(true);
        expect(body.id_pedido).toBe(idPedido);

        // HARD: pedido facturado en DB con 1 item
        await assertPedidoFacturado(page, { idPedido, itemsCount: 1 });

        // HARD: existe EXACTAMENTE 1 fila pago_pedidos tipo=3 (efectivo) con monto > 0
        const pagos = await assertPagoExiste(page, { idPedido, tipo: TIPO.EFECTIVO });
        expect(parseFloat(pagos[0].monto)).toBeGreaterThan(0);

        // HARD: ninguna otra fila de pago_pedidos (no debe haber transferencia, débito, etc.)
        await assertPagoExiste(page, { idPedido, tipo: TIPO.TRANSFERENCIA, count: 0 });
        await assertPagoExiste(page, { idPedido, tipo: TIPO.DEBITO, count: 0 });

        // HARD: console del browser limpia
        await watcher.assertClean();
    });
});
