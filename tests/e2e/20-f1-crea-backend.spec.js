// @ts-check
const { test, expect } = require('@playwright/test');
const { setupCajero, login, abrirCaja, config } = require('./_support/helpers');

const baseUrl = () => config.arabitofacturacion.baseUrl;

/**
 * F1-BACKEND 2026-05-27 — guardrail estricto: cuando el cajero pulsa F1, DEBE
 * crearse un pedido BACKEND (id numérico) y NO un pedido front-only (UUID azul).
 *
 * Este test caza el bug donde pagarMain renderizaba ListProductosInterno
 * sin pasarle addNewPedido como prop → typeof === 'function' fallaba →
 * caía al fallback addNewPedidoFront(). Verifica vía:
 *   - request a /addNewPedido salió
 *   - tabs nuevas tienen formato #NNNNN (numérico), no #UUID
 */
test.describe('F1 → backend pedido (guardrail)', () => {
    test('F1 dispara GET /addNewPedido + nuevo tab es numérico', async ({ page }) => {
        const requests = [];
        page.on('request', (req) => {
            if (req.url().includes('/addNewPedido')) {
                requests.push({ method: req.method(), url: req.url() });
            }
        });

        const watcher = await setupCajero(page);
        await login(page);
        await abrirCaja(page);
        // Esperar a que la barra de tabs esté montada
        await page.waitForTimeout(800);

        // Snapshot tabs ANTES de F1
        const tabsBefore = await page.evaluate(() => {
            const tabs = document.querySelectorAll('[class*="flex-shrink-0 min-w-"]');
            return Array.from(tabs).map((el) => el.textContent?.trim().slice(0, 20));
        });

        // Disparar F1
        await page.keyboard.press('F1');
        await page.waitForTimeout(2000);

        // Snapshot tabs DESPUÉS
        const tabsAfter = await page.evaluate(() => {
            const tabs = document.querySelectorAll('[class*="flex-shrink-0 min-w-"]');
            return Array.from(tabs).map((el) => el.textContent?.trim().slice(0, 20));
        });

        // HARD #1: al menos 1 request /addNewPedido salió → F1 fue al backend
        expect(
            requests.length,
            `F1 NO disparó /addNewPedido. Posiblemente cayó al fallback front (addNewPedidoFront). Tabs antes: ${JSON.stringify(tabsBefore)}, después: ${JSON.stringify(tabsAfter)}`
        ).toBeGreaterThanOrEqual(1);

        // HARD #2: las tabs nuevas son numéricas (ej "#88104"), no UUIDs (ej "#c968270b")
        const tabsNuevas = tabsAfter.filter((t) => !tabsBefore.includes(t));
        for (const t of tabsNuevas) {
            const esUuid = /^#[0-9a-f]{8}\b/i.test(t || '');
            expect(
                esUuid,
                `Tab nueva "${t}" parece UUID front-only (azul). F1 debería crear pedido backend (numérico). Tabs nuevas: ${JSON.stringify(tabsNuevas)}`
            ).toBe(false);
        }

        await watcher.assertClean();
    });
});
