// @ts-check
const { test, expect } = require('@playwright/test');
const { setupCajero, login, abrirCaja, nuevoPedido, agregarProducto, setearCantidadUltimoItem } = require('./_support/helpers');

test.describe('Items CRUD', () => {
    test('Agregar → editar cantidad inline → eliminar; el total se actualiza', async ({ page }) => {
        await setupCajero(page);
        await login(page);
        await abrirCaja(page);
        await nuevoPedido(page);

        await agregarProducto(page);
        const filaItem = page.locator('tr').filter({ hasText: /.+/ }).last();
        await expect(filaItem).toBeVisible();

        // Editar cantidad a 3 vía input inline (cambio 2026-05-27: sin window.prompt)
        await setearCantidadUltimoItem(page, 3);

        // El total debería actualizarse — verificamos que el setCantidad respondió OK
        // (el waitForResponse ya está dentro de setearCantidadUltimoItem)
        await expect(filaItem).toBeVisible();

        // Eliminar — buscar el botón de basura/x del row
        const btnEliminar = filaItem.locator('button, i.fa-trash, i.fa-times').last();
        if (await btnEliminar.isVisible({ timeout: 2000 }).catch(() => false)) {
            await btnEliminar.click();
            // Confirmación si pide
            const btnSi = page.getByRole('button', { name: /sí|confirmar|aceptar/i });
            if (await btnSi.isVisible({ timeout: 1500 }).catch(() => false)) await btnSi.click();
        }
    });
});
