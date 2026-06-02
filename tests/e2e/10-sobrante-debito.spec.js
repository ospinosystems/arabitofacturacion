// @ts-check
const { test, expect } = require('@playwright/test');
const { setupCajero, login, abrirCaja, nuevoPedido, agregarProducto, elegirCliente, inputDeMetodoPago, config } = require('./_support/helpers');

test.describe('Sobrante débito en factura abierta (Caso A)', () => {
    test('Toggle Sobrante + débito>total + transfer negativa = botón Facturar enabled', async ({ page }) => {
        await setupCajero(page);
        await login(page);
        await abrirCaja(page);
        await nuevoPedido(page);
        await agregarProducto(page);
        await elegirCliente(page);
        await expect(page.locator('text=Sin productos aún')).toBeHidden({ timeout: 8000 });

        const tasaBs = config.tasas_esperadas?.bs_por_usd ?? 50;

        // Activar toggle Sobrante — el span no es clickeable; hay que clickear el div parent
        const toggleSobrante = page.locator('div').filter({ has: page.locator('text=/^Sobrante$/i') }).filter({ has: page.locator('text=/ON|OFF/i') }).first();
        if (await toggleSobrante.isVisible({ timeout: 3000 }).catch(() => false)) {
            await toggleSobrante.click({ force: true });
        }

        // Cargar débito en Bs (más que el total)
        const debitoBs = (1 + 4) * tasaBs; // 4 USD de sobrante
        const inputDebito = inputDeMetodoPago(page, 'Débito');
        await inputDebito.fill(debitoBs.toFixed(2));

        // Cargar transferencia negativa
        const inputTransfer = inputDeMetodoPago(page, 'Transferencia');
        await inputTransfer.fill('-4.00');

        // Botón Facturar (Fact.) debe quedar enabled
        await expect(page.getByRole('button', { name: /Fact\.|Facturar/i }).first()).toBeEnabled({ timeout: 5000 });
    });
});
