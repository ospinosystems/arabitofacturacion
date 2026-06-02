// @ts-check
const { defineConfig, devices } = require('@playwright/test');

/**
 * Playwright config para E2E de arabitofacturacion.
 *
 * Convenciones:
 *  - baseURL apunta al PHP server local (artisan serve en :8000 o tu mix host).
 *    Si usás puerto diferente, exportá BASE_URL antes de correr los tests.
 *  - Cada test corre con su propia cookie/session — el helper login.js maneja auth.
 *  - Los mocks (Pinpad, central) se levantan via globalSetup/globalTeardown.
 *
 * Comandos útiles:
 *   npm run test:e2e               → todos los tests
 *   npm run test:e2e -- --headed   → ver browser
 *   npm run test:e2e -- --debug    → step-through
 *   npm run test:e2e -- tests/e2e/01-venta-efectivo.spec.js  → uno solo
 */
module.exports = defineConfig({
    testDir: './tests/e2e',
    timeout: 60_000,
    expect: { timeout: 10_000 },

    fullyParallel: false, // BD compartida → secuencial por seguridad
    workers: 1,
    retries: process.env.CI ? 2 : 0,

    reporter: [
        ['list'],
        ['html', { outputFolder: 'tests/e2e/.report', open: 'never' }],
    ],

    use: {
        baseURL: process.env.BASE_URL || 'http://localhost:8000',
        trace: 'on',                    // grabar trace SIEMPRE (replay step-by-step en HTML report)
        screenshot: 'on',               // screenshot al final de cada test
        video: 'on',                    // grabar video de TODOS los runs
        actionTimeout: 8_000,
        navigationTimeout: 20_000,
        viewport: { width: 1440, height: 900 },
        locale: 'es-VE',
        timezoneId: 'America/Caracas',
    },

    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],

    // Mocks de Pinpad / Megasoft / central se levantan acá una sola vez.
    globalSetup: require.resolve('./tests/e2e/_support/global-setup.js'),
    globalTeardown: require.resolve('./tests/e2e/_support/global-teardown.js'),

    // Si no tenés `php artisan serve` corriendo, podés descomentar esto:
    // webServer: {
    //     command: 'php artisan serve --port=8000',
    //     port: 8000,
    //     reuseExistingServer: true,
    //     timeout: 60_000,
    // },
});
