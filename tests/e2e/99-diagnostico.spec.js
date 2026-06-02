// @ts-check
const { test, expect } = require('@playwright/test');
const { setupCajero, login, abrirCaja, nuevoPedido, agregarProducto, inputDeMetodoPago, config } = require('./_support/helpers');

/**
 * DIAGNÓSTICO: tests adicionales que ayudan a triagear regresiones cuando la suite
 * principal falla. Estos NO sustituyen a los tests funcionales strict (02-18) — los
 * complementan con logging detallado.
 *
 * El segundo test (descuento UI) se eliminó porque está mejor cubierto por:
 *   - 07-descuento-aprobacion.spec.js (strict API-direct)
 *   - 99-bug-descuento.spec.js (regression check)
 */
test.describe('DIAGNÓSTICO', () => {
    test('Venta efectivo SIMPLE — log detallado de la respuesta y console del browser', async ({ page }) => {
        const consoleLogs = [];
        page.on('console', msg => consoleLogs.push(`[${msg.type()}] ${msg.text()}`));

        const watcher = await setupCajero(page);
        await login(page);
        await abrirCaja(page);
        const idPedido = await nuevoPedido(page);
        await agregarProducto(page);
        await expect(page.locator('text=Sin productos aún')).toBeHidden({ timeout: 8000 });

        // Producto vale poco — pago en USD = clean_total
        await inputDeMetodoPago(page, 'Efectivo USD').fill('0.15');

        const respPromise = page.waitForResponse((r) => r.url().includes('/setPagoPedido'), { timeout: 20000 });
        await page.getByRole('button', { name: /Fact\.|Facturar/i }).first().click();
        const r = await respPromise;
        const body = await r.json();

        // Logging completo para triage en caso de fallo
        console.log('\n========== RESPUESTA setPagoPedido ==========');
        console.log('Status:', r.status());
        console.log('Body:', JSON.stringify(body, null, 2));
        console.log('=============================================\n');
        console.log('Console logs del browser (últimos 30):');
        consoleLogs.slice(-30).forEach(l => console.log('  ', l));

        // STRICT: tiene que pasar
        expect(body.estado, `setPagoPedido falló: ${body.msj}`).toBe(true);
        expect(body.id_pedido).toBe(idPedido);

        await watcher.assertClean();
    });
});
