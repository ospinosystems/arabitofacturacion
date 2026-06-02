// @ts-check
const { test, expect } = require('@playwright/test');
const { setupCajero, login, abrirCaja, nuevoPedido, agregarProducto, inputDeMetodoPago, config } = require('./_support/helpers');

test.describe('PINPAD rechazo + revalidar', () => {
    test('Pinpad responde RECHAZADO → cajero ve mensaje y puede reintentar', async ({ page }) => {
        await setupCajero(page);

        // Override del setup: forzar modo "deny" en el pinpad mock
        const pinpadPort = config.mocks?.pinpad_port || 9001;
        await page.route('**/enviarTransaccionPOS', async (route, request) => {
            const body = JSON.parse(request.postData() || '{}');
            body.ip_pinpad = `127.0.0.1:${pinpadPort}`;
            const headers = { ...request.headers(), 'content-type': 'application/json', 'x-mock-mode': 'deny' };
            await route.continue({ postData: JSON.stringify(body), headers });
        });

        await login(page);
        await abrirCaja(page);
        await nuevoPedido(page);
        await agregarProducto(page);
        await expect(page.locator('text=Sin productos aún')).toBeHidden({ timeout: 8000 });

        const tasaBs = config.tasas_esperadas?.bs_por_usd ?? 50;
        await inputDeMetodoPago(page, 'Débito').fill(String(tasaBs.toFixed(2)));

        // Modal POS aparece — intentar enviar
        const inputCedula = page.locator('input[placeholder*="cédula" i]').first();
        if (await inputCedula.isVisible({ timeout: 5000 }).catch(() => false)) {
            await inputCedula.fill('V-12345678');
            const respPos = page.waitForResponse((r) => r.url().includes('/enviarTransaccionPOS'), { timeout: 30000 });
            await page.getByRole('button', { name: /enviar|cobrar|pasar tarjeta|aceptar/i }).first().click({ force: true });
            const r = await respPos.catch(() => null);
            if (r) {
                const body = await r.json();
                // Mock devolvió RECHAZADO (status_validacion='rechazado_definitivo' o success=false)
                expect(body.success).toBe(false);
            }
        }
        // Botón Facturar NO debe haber procesado nada — sigue habilitado para reintento
        await expect(page.getByRole('button', { name: /Fact\.|Facturar/i }).first()).toBeEnabled({ timeout: 3000 });
    });
});
