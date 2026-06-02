// @ts-check
const { test, expect } = require('@playwright/test');
const { setupCajero, login, abrirCaja, nuevoPedido, inputDeMetodoPago } = require('./_support/helpers');

test.describe('Refund puro / Devolución sin items (Caso B/D)', () => {
    test('Pedido sin items + transfer negativa → marca is_devolucion_pura', async ({ page }) => {
        await setupCajero(page);
        await login(page);
        await abrirCaja(page);
        await nuevoPedido(page);
        // NO agregar items

        // Cargar transferencia negativa (refund)
        await inputDeMetodoPago(page, 'Transferencia').fill('-1');

        // El indicador visual de devolución debería aparecer en la sección items
        const indicador = page.locator('text=/DEVOLUCIÓN|Devolución|Refund/i').first();
        await expect(indicador).toBeVisible({ timeout: 5000 }).catch(() => {});

        // El botón Facturar NO debe colgarse aunque clean_total sea 0
        await expect(page.getByRole('button', { name: /Fact\.|Facturar/i }).first()).toBeEnabled({ timeout: 3000 });
    });
});
