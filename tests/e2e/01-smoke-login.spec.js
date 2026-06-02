// @ts-check
const { test, expect } = require('@playwright/test');
const { setupCajero, login, abrirCaja, nuevoPedido, config } = require('./_support/helpers');

test.describe('Smoke', () => {
    test('Cajero logea y crea pedido — devuelve id numérico', async ({ page }) => {
        await setupCajero(page);
        await login(page);
        await abrirCaja(page);

        const idPedido = await nuevoPedido(page);
        expect(idPedido).toBeGreaterThan(0);
        expect(Number.isInteger(idPedido)).toBe(true);
    });

    test('Config cargada y URLs accesibles', async ({ request }) => {
        const facturacion = await request.get(config.arabitofacturacion.baseUrl + '/');
        expect(facturacion.status()).toBeLessThan(500);
        const central = await request.get(config.arabitocentral.baseUrl + '/');
        expect(central.status()).toBeLessThan(500);
    });
});
