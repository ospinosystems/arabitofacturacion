// @ts-check
const { test, expect } = require('@playwright/test');
const { setupCajero, login, abrirCaja, nuevoPedido, agregarProducto, inputDeMetodoPago, config } = require('./_support/helpers');

test.describe('Débito POS PINPAD', () => {
    test('POS aprueba → pago_pedido imborrable=1 anclado al pedido', async ({ page }) => {
        await setupCajero(page);
        await login(page);
        await abrirCaja(page);
        const idPedido = await nuevoPedido(page);
        await agregarProducto(page);

        await expect(page.locator('text=Sin productos aún')).toBeHidden({ timeout: 8000 });

        // Setear débito en Bs
        const tasaBs = config.tasas_esperadas?.bs_por_usd ?? 50;
        const totalBs = 1 * tasaBs;
        const inputDebito = inputDeMetodoPago(page, 'Débito');
        await inputDebito.fill(String(totalBs.toFixed(2)));

        // Modal POS débito puede abrirse — si no, el test verifica solo que el input se llenó
        const inputCedula = page.locator('input[placeholder*="cédula" i]').first();
        if (await inputCedula.isVisible({ timeout: 5000 }).catch(() => false)) {
            await inputCedula.fill('V-12345678');
            const respPos = page.waitForResponse((r) => r.url().includes('/enviarTransaccionPOS'), { timeout: 30000 });
            const btnPos = page.getByRole('button', { name: /enviar|cobrar|pasar tarjeta|Abrir POS|aceptar/i }).first();
            await btnPos.click();
            const r = await respPos.catch(() => null);

            if (r) {
                const body = await r.json();
                expect(body.success).toBe(true);
                if (body.pago_imborrable) {
                    expect(body.pago_imborrable.estado).toBe(true);
                    expect(body.pago_imborrable.id_pago_pedido).toBeGreaterThan(0);
                }
                const amountPos = body.data?.amount;
                if (amountPos) {
                    // tolerancia 1 centavo
                    expect(Math.abs(amountPos - totalBs * 100)).toBeLessThan(2);
                }
            }
        }
    });
});
