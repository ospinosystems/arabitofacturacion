// @ts-check
const config = require('./config');

/**
 * Helpers para arabitocentral — abre un browser context separado, logea como admin
 * y ejecuta acciones de aprobación.
 *
 * Uso típico en un test cross-project:
 *
 *   test('descuento con aprobación', async ({ browser }) => {
 *       // 1) Cajero pide descuento en arabitofacturacion
 *       const cajeroCtx = await browser.newContext();
 *       const cajeroPage = await cajeroCtx.newPage();
 *       await setupCajero(cajeroPage);
 *       await login(cajeroPage);
 *       // ... agregar item, pedir descuento ...
 *
 *       // 2) Admin aprueba en arabitocentral (contexto separado)
 *       const { ctx: centralCtx, page: centralPage } = await abrirCentral(browser);
 *       await aprobarUltimaSolicitudDescuento(centralPage);
 *
 *       // 3) Cajero verifica
 *       await cajeroPage.click('button:has-text("Verificar aprobación")');
 *       await expect(...).toBeVisible();
 *   });
 */

async function abrirCentral(browser) {
    const ctx = await browser.newContext();
    const page = await ctx.newPage();
    await page.goto(config.arabitocentral.baseUrl + '/login');
    await page.fill('input[name="usuario"], input[name="email"], input[type="email"]', config.arabitocentral.admin.usuario);
    await page.fill('input[name="password"], input[type="password"]', config.arabitocentral.admin.password);
    await Promise.all([
        page.waitForLoadState('networkidle', { timeout: 15_000 }),
        page.click('button[type="submit"]'),
    ]);
    return { ctx, page };
}

/**
 * Va al panel de solicitudes de descuento y aprueba la más reciente.
 * Los selectores son aproximados — si rompen, ajustar con `data-test=` o
 * con el texto exacto de los botones del admin panel.
 */
async function aprobarUltimaSolicitudDescuento(page) {
    await page.goto(config.arabitocentral.baseUrl + '/solicitudes-descuentos');
    await page.waitForLoadState('networkidle');
    const filaUltima = page.locator('tr').filter({ hasText: /enviado|pendiente/i }).first();
    await filaUltima.locator('button:has-text("Aprobar"), a:has-text("Aprobar")').first().click();
    // Confirmación si la hay
    const btnConfirmar = page.getByRole('button', { name: /confirmar|sí|aprobar/i }).last();
    if (await btnConfirmar.isVisible({ timeout: 2_000 }).catch(() => false)) {
        await btnConfirmar.click();
    }
    await page.waitForLoadState('networkidle');
}

/** Aprueba la transferencia más reciente que está pendiente. */
async function aprobarUltimaTransferencia(page) {
    await page.goto(config.arabitocentral.baseUrl + '/transferencias-aprobacion');
    await page.waitForLoadState('networkidle');
    const filaUltima = page.locator('tr').filter({ hasText: /pendiente|por aprobar/i }).first();
    await filaUltima.locator('button:has-text("Aprobar"), a:has-text("Aprobar")').first().click();
    const btnConfirmar = page.getByRole('button', { name: /confirmar|sí|aprobar/i }).last();
    if (await btnConfirmar.isVisible({ timeout: 2_000 }).catch(() => false)) {
        await btnConfirmar.click();
    }
    await page.waitForLoadState('networkidle');
}

/** Aprueba la solicitud de crédito más reciente */
async function aprobarUltimoCredito(page) {
    await page.goto(config.arabitocentral.baseUrl + '/solicitudes-credito');
    await page.waitForLoadState('networkidle');
    const filaUltima = page.locator('tr').filter({ hasText: /enviado|pendiente/i }).first();
    await filaUltima.locator('button:has-text("Aprobar")').first().click();
    const btnConfirmar = page.getByRole('button', { name: /confirmar|sí|aprobar/i }).last();
    if (await btnConfirmar.isVisible({ timeout: 2_000 }).catch(() => false)) {
        await btnConfirmar.click();
    }
}

module.exports = {
    abrirCentral,
    aprobarUltimaSolicitudDescuento,
    aprobarUltimaTransferencia,
    aprobarUltimoCredito,
};
