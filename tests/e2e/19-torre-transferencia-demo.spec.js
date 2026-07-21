// @ts-check
/**
 * DEMO AUTOMÁTICA — el script maneja la app solo (sin tocar nada vos) y crea una
 * transferencia desde la Torre de Transferencia (usuario DICI), enviándola a central.
 *
 * Para VERLO como un video (navegador real moviéndose):
 *   npm run test:e2e -- --headed tests/e2e/19-torre-transferencia-demo.spec.js
 * Para ir paso a paso:
 *   npm run test:e2e -- --debug  tests/e2e/19-torre-transferencia-demo.spec.js
 * El reporte con video/trace queda en tests/e2e/.report (npm run test:e2e:report).
 *
 * Requisitos: app en :8000, central real en :8001, front compilado, usuario dici/1234.
 */
const { test, expect } = require('@playwright/test');
const { execSync } = require('child_process');
const { config } = require('./_support/helpers');

// channel 'chrome': usa el Chrome del sistema (evita el "spawn UNKNOWN" del chromium
// empaquetado en headed sobre Windows). slowMo: pausa ~350ms cada acción → se ve como video.
test.use({ channel: 'chrome', launchOptions: { slowMo: 700 } });

// Asegurar stock del producto de prueba (cada demo descuenta) → demo repetible.
test.beforeAll(() => {
    try {
        execSync('php artisan test:ensure-stock 173277 100', { stdio: 'ignore' });
    } catch (e) {
        /* best-effort */
    }
});

const DICI = { usuario: 'dici', password: '1234' };
const PRODUCTO_BARRAS = '6975085806912'; // LLAVE HEXAGONAL — sembrado en la matriz de central
const DESTINO = 'calabozo';              // misma sucursal (el bloqueo está desactivado en local)

async function loginDici(page) {
    await page.goto(config.arabitofacturacion.baseUrl + '/login');
    await page.getByRole('textbox', { name: /usuario/i }).fill(DICI.usuario);
    await page.getByRole('textbox', { name: /contraseña|password/i }).fill(DICI.password);
    await Promise.all([
        page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {}),
        page.getByRole('button', { name: /iniciar sesión|ingresar|login/i }).click(),
    ]);
}

test.describe('Torre de Transferencia — demo automática', () => {
    test('DICI crea una transferencia con un producto y la envía a central', async ({ page }) => {
        await loginDici(page);
        await page.waitForTimeout(2000); // dejar asentar el render inicial (la app hace polling)

        // La app re-renderiza de fondo (polling) → los elementos rara vez quedan "stable",
        // por eso scrolleamos + force:true: el elemento existe y es visible igual.
        const clickFuerte = async (loc) => {
            const el = loc.first();
            await el.waitFor({ state: 'visible', timeout: 10000 });
            await el.scrollIntoViewIfNeeded().catch(() => {});
            await el.click({ force: true });
            await page.waitForTimeout(600);
        };

        // ── Abrir el sidebar (la hamburguesa setea open=true; idempotente) ──
        const hb = page.locator('header button').filter({ has: page.locator('i.fa-bars') }).first();
        if (await hb.isVisible({ timeout: 4000 }).catch(() => false)) {
            await hb.click({ force: true });
            await page.waitForTimeout(700);
        }

        // ── Entrar a DICI ──
        await clickFuerte(page.getByRole('button', { name: /DICI/i }));

        // ── Torre de Transferencia ──
        await clickFuerte(page.getByRole('button', { name: /Torre de Transferencia/i }));

        // ── TCD · Despacho (Enviar Pedidos → TransferenciasModule) ──
        await clickFuerte(page.getByRole('button', { name: /TCD.*Despacho/i }));

        // ── Nueva Transferencia ──
        await clickFuerte(page.getByRole('button', { name: /Nueva Transferencia/i }));

        // ── Buscar y agregar el producto ──
        const buscador = page.getByPlaceholder('Buscar producto...');
        await buscador.click();
        await buscador.fill(PRODUCTO_BARRAS);
        const resultado = page.locator('li').filter({ hasText: PRODUCTO_BARRAS }).first();
        await resultado.waitFor({ state: 'visible', timeout: 8000 }); // el buscador tiene debounce
        await resultado.click({ force: true });

        // modal cantidad — tipear (dispara onChange de React) y confirmar con Enter (handleKeyDown)
        await expect(page.getByText('Seleccionar Cantidad')).toBeVisible({ timeout: 8000 });
        const inputCant = page.locator('#cantidad');
        await inputCant.click();
        await inputCant.fill('');
        await inputCant.pressSequentially('2', { delay: 60 });
        await inputCant.press('Enter');
        // por si el Enter no alcanzó, reforzamos con el botón Agregar
        if (await page.getByText('Seleccionar Cantidad').isVisible({ timeout: 1500 }).catch(() => false)) {
            await page.getByRole('button', { name: 'Agregar', exact: true }).click({ force: true });
        }
        await expect(page.getByText('Seleccionar Cantidad')).toBeHidden({ timeout: 8000 }); // el modal debe cerrarse

        // el producto debe aparecer en la tabla de ítems
        await expect(page.locator('table').getByText(PRODUCTO_BARRAS).first()).toBeVisible({ timeout: 8000 });

        // ── Elegir destino (nueva transferencia: arranca desbloqueado con buscador) ──
        const buscadorDestino = page.getByPlaceholder('Buscar sucursal...');
        if (await buscadorDestino.isVisible({ timeout: 3000 }).catch(() => false)) {
            await buscadorDestino.fill(DESTINO);
            await page.locator('li').filter({ hasText: new RegExp(DESTINO, 'i') }).first().click();
        }

        // ── Crear Transferencia → enviar a central ──
        await page.getByRole('button', { name: /Crear Transferencia/i }).click();

        // ── Resultado ──
        await expect(page.getByText(/creada exitosamente/i)).toBeVisible({ timeout: 15000 });

        // dejar la ventana abierta un rato para alcanzar a verla (si no, Playwright cierra al terminar)
        await page.waitForTimeout(8000);
    });
});
