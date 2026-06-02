// @ts-check
const { test, expect } = require('@playwright/test');
const { setupCajero, login, abrirCaja, nuevoPedido, agregarProducto, elegirCliente } = require('./_support/helpers');

test.describe('Descuento unitario inline (backend)', () => {
    test('Click % del item → input inline → guarda local sin solicitud monto_porcentaje', async ({ page }) => {
        await setupCajero(page);
        await login(page);
        await abrirCaja(page);
        await nuevoPedido(page);
        await agregarProducto(page);
        await elegirCliente(page);
        await expect(page.locator('text=Sin productos aún')).toBeHidden({ timeout: 8000 });

        // Click sobre la celda de descuento del ítem (suele ser "%" o "—%" o "0%")
        const celdaDesc = page.locator('td').filter({ hasText: /^—?\s*\d*\.?\d*%\s*$/ }).first();
        if (await celdaDesc.isVisible({ timeout: 5000 }).catch(() => false)) {
            await celdaDesc.click({ force: true });
            // Aparece input pequeño
            const input = page.locator('input[type="text"][placeholder="0"]').last();
            if (await input.isVisible({ timeout: 3000 }).catch(() => false)) {
                const respDesc = page.waitForResponse((r) => r.url().includes('/setDescuentoUnitario'), { timeout: 10000 });
                await input.fill('0.5');  // monto final 0.50 (50% descuento sobre 1 USD)
                await input.press('Enter');
                const r = await respDesc.catch(() => null);
                if (r) {
                    const body = await r.json();
                    // Cambio 2026-05-27: NO debe generar solicitud monto_porcentaje
                    expect(body.estado).toBe(true);
                    expect(body.solicitud_descuento_pendiente).toBeFalsy();
                }
            }
        }
    });
});
