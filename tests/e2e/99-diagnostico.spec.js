// @ts-check
const { test, expect } = require('@playwright/test');
const { setupCajero, login, abrirCaja, nuevoPedido, agregarProducto, elegirCliente, inputDeMetodoPago, config } = require('./_support/helpers');

test.describe('DIAGNÓSTICO ESTRICTO', () => {
    test('Venta efectivo SIMPLE — DEBE facturar (estado=true)', async ({ page }) => {
        const consoleLogs = [];
        page.on('console', msg => consoleLogs.push(`[${msg.type()}] ${msg.text()}`));

        await setupCajero(page);
        await login(page);
        await abrirCaja(page);
        const idPedido = await nuevoPedido(page);
        await agregarProducto(page);
        await expect(page.locator('text=Sin productos aún')).toBeHidden({ timeout: 8000 });

        // Producto vale 0.15 USD = 80.31 Bs. Pago exacto.
        await inputDeMetodoPago(page, 'Efectivo USD').fill('0.15');

        const respPromise = page.waitForResponse((r) => r.url().includes('/setPagoPedido'), { timeout: 20000 });
        await page.getByRole('button', { name: /Fact\.|Facturar/i }).first().click();
        const r = await respPromise;
        const body = await r.json();

        console.log('\n========== RESPUESTA setPagoPedido ==========');
        console.log('Status:', r.status());
        console.log('Body:', JSON.stringify(body, null, 2));
        console.log('=============================================\n');
        console.log('Console logs del browser:');
        consoleLogs.slice(-30).forEach(l => console.log('  ', l));

        // STRICT: tiene que pasar
        expect(body.estado).toBe(true);
        expect(body.id_pedido).toBe(idPedido);
    });

    test('Venta con descuento DEBE crear solicitud metodo_pago en central', async ({ page }) => {
        const networkCalls = [];
        page.on('response', async r => {
            const url = r.url();
            if (url.includes('setDescuento') || url.includes('setPagoPedido') || url.includes('solicitud')) {
                try {
                    const body = await r.json();
                    networkCalls.push({ url: url.split('/').pop(), status: r.status(), body });
                } catch (e) { /* */ }
            }
        });

        await setupCajero(page);
        await login(page);
        await abrirCaja(page);
        const idPedido = await nuevoPedido(page);
        await agregarProducto(page);
        await elegirCliente(page);
        await expect(page.locator('text=Sin productos aún')).toBeHidden({ timeout: 8000 });

        // Aplicar descuento total: monto final 0.10 (sobre 0.15 = ~33% desc)
        const btnDesc = page.locator('button[data-index]').filter({ hasText: /^\s*\d+(\.\d+)?%\s*$/ }).first();
        await btnDesc.click({ force: true });
        const inputDesc = page.getByPlaceholder(/monto final/i).first();
        await inputDesc.fill('0.10');
        await inputDesc.press('Enter');
        await page.waitForTimeout(1500);

        // Pagar en efectivo USD (el descuento debería arrastrarse)
        await inputDeMetodoPago(page, 'Efectivo USD').fill('0.10');

        const respPromise = page.waitForResponse((r) => r.url().includes('/setPagoPedido'), { timeout: 20000 });
        await page.getByRole('button', { name: /Fact\.|Facturar/i }).first().click();
        const r = await respPromise;
        const body = await r.json();

        console.log('\n========== DIAGNÓSTICO DESCUENTO ==========');
        console.log('Pedido ID:', idPedido);
        console.log('setPagoPedido response:', JSON.stringify(body, null, 2));
        console.log('\nTODAS las llamadas relevantes:');
        networkCalls.forEach(c => console.log(`  ${c.url} → status=${c.status}  body=${JSON.stringify(c.body).substring(0, 200)}`));
        console.log('============================================\n');
    });
});
