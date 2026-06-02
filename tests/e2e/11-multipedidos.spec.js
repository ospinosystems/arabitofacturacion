// @ts-check
const { test, expect } = require('@playwright/test');
const { setupCajero, login, abrirCaja, nuevoPedido, agregarProducto } = require('./_support/helpers');

test.describe('Multipedidos: tabs simultáneos', () => {
    test('Crear 3 pedidos backend, agregar items distintos, alternar tabs', async ({ page }) => {
        await setupCajero(page);
        await login(page);
        await abrirCaja(page);

        const ids = [];

        ids.push(await nuevoPedido(page));
        await agregarProducto(page, undefined, 1);

        ids.push(await nuevoPedido(page));
        await agregarProducto(page, undefined, 2);

        ids.push(await nuevoPedido(page));
        await agregarProducto(page, undefined, 3);

        expect(new Set(ids).size).toBe(3);

        // En barraPedLateral los tabs son botones tipo "#1234"
        const tabsCount = await page.locator('button').filter({ hasText: /^#\d+$/ }).count();
        expect(tabsCount).toBeGreaterThanOrEqual(3);
    });
});
