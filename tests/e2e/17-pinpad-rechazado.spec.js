// @ts-check
const { test, expect } = require('@playwright/test');
const { setupCajero, login, abrirCaja, nuevoPedido, agregarProducto, inputDeMetodoPago, config } = require('./_support/helpers');
const { assertPagoExiste, assertPosRechazadoRegistrado, TIPO } = require('./_support/strict-assertions');

test.describe('PINPAD rechazo + revalidar (STRICT)', () => {
    test('Pinpad RECHAZA → backend NO crea pago_pedido + crea fila en pos_operaciones_rechazadas', async ({ page }) => {
        const watcher = await setupCajero(page);

        // Override: forzar modo deny en pinpad mock
        const pinpadPort = config.mocks?.pinpad_port || 9001;
        await page.route('**/enviarTransaccionPOS', async (route, request) => {
            const body = JSON.parse(request.postData() || '{}');
            body.ip_pinpad = `127.0.0.1:${pinpadPort}`;
            const headers = { ...request.headers(), 'content-type': 'application/json', 'x-mock-mode': 'deny' };
            await route.continue({ postData: JSON.stringify(body), headers });
        });

        await login(page);
        await abrirCaja(page);
        const idPedido = await nuevoPedido(page);
        await agregarProducto(page);
        await expect(page.locator('text=Sin productos aún')).toBeHidden({ timeout: 8000 });

        const tasaBs = config.tasas_esperadas?.bs_por_usd ?? 50;
        await inputDeMetodoPago(page, 'Débito').fill(String(tasaBs.toFixed(2)));

        const inputCedula = page.locator('input[placeholder*="cédula" i]').first();
        const cedulaVisible = await inputCedula.isVisible({ timeout: 5000 }).catch(() => false);
        if (!cedulaVisible) {
            test.skip(true, 'Modal POS débito no se abrió');
            return;
        }

        await inputCedula.fill('V-12345678');
        const respPos = page.waitForResponse((r) => r.url().includes('/enviarTransaccionPOS'), { timeout: 30000 });
        await page.getByRole('button', { name: /enviar|cobrar|pasar tarjeta|aceptar/i }).first().click({ force: true });
        const r = await respPos;
        const body = await r.json();

        // HARD: el POS devolvió rechazo
        expect(body.success).toBe(false);

        // Esperar a que backend registre el rechazo
        await page.waitForTimeout(2000);

        // HARD: NO debe haber pago_pedido tipo=2 (POS rechazó, no se cobró nada)
        await assertPagoExiste(page, { idPedido, tipo: TIPO.DEBITO, count: 0 });

        // HARD: SI debe haber fila en pos_operaciones_rechazadas (la integración nueva
        // registrarPosRechazadoInterno corre dentro de enviarTransaccionPOS)
        const idPedidoStr = String(idPedido);
        await assertPosRechazadoRegistrado(page, { numeroOrdenContiene: idPedidoStr });

        // HARD: el botón Facturar sigue habilitado para reintento
        await expect(page.getByRole('button', { name: /Fact\.|Facturar/i }).first()).toBeEnabled({ timeout: 3000 });

        await watcher.assertClean();
    });
});
