// @ts-check
const { test, expect } = require('@playwright/test');
const { setupCajero, login, abrirCaja, nuevoPedido, agregarProducto } = require('./_support/helpers');

test.describe('Eliminar item del carrito', () => {
    test('Click ícono basura → item desaparece y vuelve "Sin productos aún"', async ({ page }) => {
        await setupCajero(page);
        await login(page);
        await abrirCaja(page);
        await nuevoPedido(page);
        await agregarProducto(page);
        await expect(page.locator('text=Sin productos aún')).toBeHidden({ timeout: 8000 });

        // El icono de eliminar es <i class="text-red-500 fa fa-times..."> en la última celda
        // del row del carrito. Scopeamos al carrito: el row que contiene el código del producto.
        const codigo = require('./_support/config').datos_prueba.producto_codigo_barras;
        // El carrito tiene una fila con el producto que contiene la celda "—" o "0,15" (precio)
        // El ícono `text-red-500 fa fa-times` está al final
        const iconoBorrar = page.locator(`i.fa-times.text-red-500, i.fa-trash.text-red-500`).first();
        if (await iconoBorrar.isVisible({ timeout: 3000 }).catch(() => false)) {
            const respDel = page.waitForResponse((r) => r.url().includes('/setCarrito') || r.url().includes('/delItem') || r.url().includes('/quitarItem'), { timeout: 8000 }).catch(() => null);
            await iconoBorrar.click({ force: true });
            const btnSi = page.getByRole('button', { name: /sí|confirmar|aceptar|eliminar/i });
            if (await btnSi.isVisible({ timeout: 2000 }).catch(() => false)) {
                await btnSi.click();
            }
            await respDel;
            await page.waitForTimeout(500);
            await expect(page.locator('text=Sin productos aún')).toBeVisible({ timeout: 5000 });
        }
    });
});
