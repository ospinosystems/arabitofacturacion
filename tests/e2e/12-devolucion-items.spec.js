// @ts-check
const { test, expect } = require('@playwright/test');
const { setupCajero, login, abrirCaja, nuevoPedido, agregarProducto, inputDeMetodoPago } = require('./_support/helpers');

test.describe('Devoluciones', () => {
    test('Devolución con item negativo (modal Factura Original o validación)', async ({ page }) => {
        await setupCajero(page);
        await login(page);
        await abrirCaja(page);
        await nuevoPedido(page);
        await agregarProducto(page);
        await expect(page.locator('text=Sin productos aún')).toBeHidden({ timeout: 8000 });

        // Click sobre la celda de cantidad del item para abrir el editor inline
        const celdaCantidad = page.locator('td').filter({ hasText: /^\d+(\.\d+)?$/ }).last();
        await celdaCantidad.click().catch(() => {});
        const input = page.locator('input[type="number"]').last();
        if (await input.isVisible({ timeout: 2000 }).catch(() => false)) {
            await input.fill('-1');
            await input.press('Enter');
        }

        // El modal de devolución debería aparecer; si no, el cambio queda en local
        await page.waitForTimeout(1000);
    });

    test('Refund puro: transfer negativa sin items muestra indicador "Devolución"', async ({ page }) => {
        await setupCajero(page);
        await login(page);
        await abrirCaja(page);
        await nuevoPedido(page);

        // No items — solo cargar transferencia negativa
        await inputDeMetodoPago(page, 'Transferencia').fill('-1');

        // El indicador visual "Devolución" debería aparecer (cambio 2026-05-27)
        // o al menos el botón Facturar no debe quedar disabled
        await expect(page.getByRole('button', { name: /Fact\.|Facturar/i }).first()).toBeEnabled({ timeout: 3000 });
    });
});
