// @ts-check
const { test, expect } = require('@playwright/test');
const { setupCajero, login, abrirCaja, nuevoPedido, agregarProducto, elegirCliente, inputDeMetodoPago, config } = require('./_support/helpers');

test.describe('Autovalidar transferencia', () => {
    test('Abrir modal autovalidar → no se cuelga', async ({ page }) => {
        await setupCajero(page);
        await login(page);
        await abrirCaja(page);
        await nuevoPedido(page);
        await agregarProducto(page);
        await elegirCliente(page);
        await expect(page.locator('text=Sin productos aún')).toBeHidden({ timeout: 8000 });

        await inputDeMetodoPago(page, 'Transferencia').fill('1');

        const btnRef = page.locator('button').filter({ hasText: /\+\s*Ref|nueva ref|agregar.*ref/i }).first();
        if (await btnRef.isVisible({ timeout: 3000 }).catch(() => false)) {
            await btnRef.click();
        }

        // Tab "AutoValidar" si está
        const tabAuto = page.getByRole('tab', { name: /autovalidar/i }).or(page.getByRole('button', { name: /autovalidar/i })).first();
        if (await tabAuto.isVisible({ timeout: 2000 }).catch(() => false)) {
            await tabAuto.click();
        }

        // Si el botón Facturar sigue habilitado el flujo no se cuelga
        await expect(page.getByRole('button', { name: /Fact\.|Facturar/i }).first()).toBeEnabled({ timeout: 5000 });
    });
});
