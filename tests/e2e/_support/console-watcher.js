// @ts-check
/**
 * CONSOLE-WATCHER 2026-05-27
 *
 * Captura console.error y pageerror durante un test. Si el browser tira un error
 * JS mientras se ejecuta el flujo, el test falla — antes pasaban verdes mientras
 * la consola gritaba.
 *
 * Uso:
 *   const watcher = attachConsoleWatcher(page);
 *   ... test flow ...
 *   await watcher.assertClean();
 *
 * Whitelist: hay errores conocidos no relacionados con el flujo que se ignoran
 * (ej: extensiones de browser, recursos 404 esperados). Mantenerla CORTA y bien
 * documentada — cada entrada nueva debe tener un comentario explicando por qué.
 */

const IGNORE_PATTERNS = [
    // Favicon es opcional; algunos browsers loguean 404 si falta.
    /favicon\.ico/i,
    // websockets de soketi/pusher caídos en local — ya tenemos SafeBroadcast en backend
    /WebSocket connection.*failed|pusher.*disconnect|soketi/i,
    // React DevTools en producción suele tirar este warning informativo
    /Download the React DevTools/i,
    // Sourcemap errors no son bugs de la app
    /Failed to load resource:.*\.map\b/i,
    // Browser security: a veces las extensiones loguean ruido
    /chrome-extension:/i,
    // jQuery deprecation warnings — no son nuestros
    /jQuery.*deprecated/i,
];

function shouldIgnore(text) {
    return IGNORE_PATTERNS.some((re) => re.test(text));
}

function attachConsoleWatcher(page) {
    const consoleErrors = [];
    const pageErrors = [];

    page.on('console', (msg) => {
        if (msg.type() === 'error') {
            const text = msg.text();
            if (!shouldIgnore(text)) {
                consoleErrors.push({
                    text,
                    location: msg.location?.() || {},
                });
            }
        }
    });

    page.on('pageerror', (err) => {
        const text = err.message || String(err);
        if (!shouldIgnore(text)) {
            pageErrors.push({
                message: text,
                stack: err.stack,
            });
        }
    });

    return {
        consoleErrors,
        pageErrors,
        /**
         * Acumula errores en arrays. No tira excepciones — el caller decide.
         */
        getErrors() {
            return [...consoleErrors, ...pageErrors];
        },
        /**
         * Falla el test si hubo cualquier console.error o pageerror.
         * Llamar al FINAL del test (después de que el flujo termine + un waitForTimeout
         * para dar tiempo a que errores asíncronos lleguen).
         */
        async assertClean(extraIgnorePatterns = []) {
            // Dar tiempo a que errores async lleguen a la cola
            await page.waitForTimeout(500);
            const extraIgnore = (text) => extraIgnorePatterns.some((re) => re.test(text));
            const allErrors = [
                ...consoleErrors.filter((e) => !extraIgnore(e.text)),
                ...pageErrors.filter((e) => !extraIgnore(e.message)),
            ];
            if (allErrors.length > 0) {
                const formatted = allErrors.map((e, i) => {
                    const what = e.text || e.message;
                    const where = e.location?.url ? ` (${e.location.url}:${e.location.lineNumber})` : '';
                    return `  [${i + 1}] ${what}${where}`;
                }).join('\n');
                throw new Error(
                    `Test produjo ${allErrors.length} error(es) en consola del browser:\n${formatted}\n` +
                    `Si alguno es benigno (no relacionado al flujo), agregalo a IGNORE_PATTERNS en console-watcher.js con un comentario.`
                );
            }
        },
        clear() {
            consoleErrors.length = 0;
            pageErrors.length = 0;
        },
    };
}

module.exports = { attachConsoleWatcher };
