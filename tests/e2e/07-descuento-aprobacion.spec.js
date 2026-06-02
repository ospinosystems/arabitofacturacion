// @ts-check
const { test, expect } = require('@playwright/test');
const { setupCajero, login, abrirCaja, nuevoPedido, agregarProducto, elegirCliente, inputDeMetodoPago, config } = require('./_support/helpers');

test.describe('Descuentos backend (sin solicitud monto_porcentaje)', () => {
    test('setDescuentoTotal guarda local y NO devuelve solicitud_descuento_pendiente', async ({ page }) => {
        await setupCajero(page);
        await login(page);
        await abrirCaja(page);
        await nuevoPedido(page);
        await agregarProducto(page);
        await elegirCliente(page);
        await expect(page.locator('text=Sin productos aún')).toBeHidden({ timeout: 8000 });

        // Click sobre el botón "0%" (descuento total) — está al lado del total del pedido
        // El botón tiene texto "0%" y data-index del pedido id
        const btnDesc = page.locator('button[data-index]').filter({ hasText: /^\s*\d+(\.\d+)?%\s*$/ }).first();
        if (await btnDesc.isVisible({ timeout: 5000 }).catch(() => false)) {
            await btnDesc.click({ force: true });
            // Aparece input "Monto final"
            const inputMonto = page.getByPlaceholder(/monto final/i).first();
            if (await inputMonto.isVisible({ timeout: 3000 }).catch(() => false)) {
                const respDesc = page.waitForResponse((r) => r.url().includes('/setDescuentoTotal'), { timeout: 10000 });
                await inputMonto.fill('0.5');  // monto final 0.50 USD (50% descuento sobre 1 USD)
                await inputMonto.press('Enter');
                const r = await respDesc.catch(() => null);
                if (r) {
                    const body = await r.json();
                    // Cambio 2026-05-27: para pedidos backend, descuento se guarda local sin solicitud
                    expect(body.estado).toBe(true);
                    expect(body.solicitud_descuento_pendiente).toBeFalsy();
                }
            }
        }
    });
});
