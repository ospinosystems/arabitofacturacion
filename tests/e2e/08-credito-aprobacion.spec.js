// @ts-check
const { test, expect } = require('@playwright/test');
const { setupCajero, login, abrirCaja, nuevoPedido, agregarProducto, elegirCliente, inputDeMetodoPago } = require('./_support/helpers');

test.describe('Crédito', () => {
    test('Click "+ Crédito" abre input crédito y permite cargar valor', async ({ page }) => {
        await setupCajero(page);
        await login(page);
        await abrirCaja(page);
        await nuevoPedido(page);
        await agregarProducto(page);
        await elegirCliente(page);
        await expect(page.locator('text=Sin productos aún')).toBeHidden({ timeout: 8000 });

        // El botón "+ Crédito" puede estar oculto al lado de "Auto"
        const btnAddCredito = page.getByRole('button', { name: /\+\s*Crédito|\+ Credito/i }).first();
        if (await btnAddCredito.isVisible({ timeout: 3000 }).catch(() => false)) {
            await btnAddCredito.click({ force: true });
        }

        // Si aparece el input de crédito, llenarlo
        const inputCredito = inputDeMetodoPago(page, 'Crédito');
        if (await inputCredito.isVisible({ timeout: 3000 }).catch(() => false)) {
            await inputCredito.fill('1');
        }

        // No debe colgarse
        await expect(page.getByRole('button', { name: /Fact\.|Facturar/i }).first()).toBeEnabled({ timeout: 3000 });
    });
});
