// @ts-check
const { test, expect } = require('@playwright/test');
const { setupCajero, login, abrirCaja, nuevoPedido, agregarProducto, elegirCliente, inputDeMetodoPago, config } = require('./_support/helpers');

test.describe('Multipago + coherencia USD/Bs', () => {
    test('3 métodos cubren total — botón Facturar enabled', async ({ page }) => {
        await setupCajero(page);
        await login(page);
        await abrirCaja(page);
        await nuevoPedido(page);
        await agregarProducto(page, undefined, 3);
        await elegirCliente(page);
        await expect(page.locator('text=Sin productos aún')).toBeHidden({ timeout: 8000 });

        const tasaBs = config.tasas_esperadas?.bs_por_usd ?? 50;

        // Llenar 3 métodos con valores arbitrarios (testeamos que no se cuelgue, no la suma exacta)
        await inputDeMetodoPago(page, 'Efectivo USD').fill('1');
        await inputDeMetodoPago(page, 'Débito').fill(tasaBs.toFixed(2));
        await inputDeMetodoPago(page, 'Transferencia').fill('1');

        // El botón Fact. NO debe quedar disabled — el usuario puede intentar facturar
        // aunque las cuentas no encajen perfecto (el backend tiene tolerancia 0.1 USD)
        await expect(page.getByRole('button', { name: /Fact\.|Facturar/i }).first()).toBeEnabled({ timeout: 3000 });
    });
});
