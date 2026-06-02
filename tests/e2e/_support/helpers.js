// @ts-check
const config = require('./config');
const { attachConsoleWatcher } = require('./console-watcher');

/**
 * Helpers reutilizables para los tests de arabitofacturacion (cajero).
 */

/**
 * Setup que debe correrse en CADA test al inicio. Hace:
 *  1) Intercepta /enviarTransaccionPOS y le inyecta ip_pinpad=127.0.0.1:{mock_port}
 *     así no tocamos la columna ip_pinpad del cajero en la BD real.
 *  2) Engancha console-watcher para capturar console.error y pageerror.
 *     El test debe llamar `await watcher.assertClean()` al final para validar.
 *
 * Retorna el watcher para que el test pueda hacer assertClean() o getErrors().
 */
async function setupCajero(page) {
    const pinpadPort = config.mocks?.pinpad_port || 9001;
    const ipPinpadMock = `127.0.0.1:${pinpadPort}`;

    await page.route('**/enviarTransaccionPOS', async (route, request) => {
        let body = {};
        try { body = JSON.parse(request.postData() || '{}'); } catch (e) { /* */ }
        body.ip_pinpad = ipPinpadMock;
        // Pasamos un header para que el mock pueda diferenciar modos si querés
        const headers = { ...request.headers(), 'content-type': 'application/json' };
        await route.continue({
            postData: JSON.stringify(body),
            headers,
        });
    });

    return attachConsoleWatcher(page);
}

async function login(page) {
    await page.goto(config.arabitofacturacion.baseUrl + '/login');
    // Login form usa label/placeholder "Usuario" y "Contraseña" (sin name=)
    await page.getByRole('textbox', { name: /usuario/i }).fill(config.arabitofacturacion.cajero.usuario);
    await page.getByRole('textbox', { name: /contraseña|password/i }).fill(config.arabitofacturacion.cajero.password);
    await Promise.all([
        page.waitForLoadState('networkidle', { timeout: 15_000 }).catch(() => {}),
        page.getByRole('button', { name: /iniciar sesión|ingresar|login/i }).click(),
    ]);
}

async function abrirCaja(page) {
    const btnAbrir = page.getByRole('button', { name: /abrir caja|abrir/i });
    if (await btnAbrir.isVisible({ timeout: 2_000 }).catch(() => false)) {
        await btnAbrir.click();
    }
}

/** Click en el botón "+ Nuevo" — crea pedido en backend. Devuelve el id_pedido. */
async function nuevoPedido(page) {
    const waitR = page.waitForResponse((r) => r.url().includes('/addNewPedido'));
    // El botón es "+ Nuevo" (con el +)
    await page.getByRole('button', { name: /\+?\s*Nuevo$/i }).first().click();
    const r = await waitR;
    const idPedido = await r.json();
    await page.waitForLoadState('networkidle', { timeout: 10_000 });
    return Number(idPedido);
}

async function agregarProducto(page, codigo, cantidad = 1) {
    const code = codigo || config.datos_prueba.producto_codigo_barras;
    // 1) Filtrar en la grilla de productos (input "Agregar...(Esc)")
    const inputBusqueda = page.getByRole('textbox', { name: /agregar/i }).first();
    await inputBusqueda.fill(code);
    await page.waitForTimeout(500); // debounce 100ms + delay react
    // 2) Click sobre la fila → setSelectedProduct → aparece input de cantidad enfocado
    const fila = page.getByRole('row').filter({ hasText: code }).first();
    await fila.click();
    // 3) Tipear cantidad + Enter → dispara handleAddToCart → setCarrito en backend
    await page.waitForTimeout(300);
    const respCarrito = page.waitForResponse((r) => r.url().includes('/setCarrito') || r.url().includes('/getCarrito'), { timeout: 10_000 }).catch(() => null);
    await page.keyboard.type(String(cantidad));
    await page.keyboard.press('Enter');
    await respCarrito;
    await page.waitForTimeout(400);
}

/** Localiza el input de un método de pago por el label de su sección (ej. "Efectivo USD") */
function inputDeMetodoPago(page, label) {
    // Estructura: <section> <label>X</label> ... <input/> ... </section>
    return page.locator(
        `xpath=//*[contains(normalize-space(.), '${label}')]/following::input[1]`
    ).first();
}

async function setearCantidadUltimoItem(page, cantidad) {
    const filaCantidad = page.locator('td').filter({ hasText: /^\d+(\.\d+)?$/ }).last();
    await filaCantidad.click();
    const input = page.locator('input[type="number"]').last();
    await input.fill(String(cantidad));
    const resp = page.waitForResponse((r) => r.url().includes('/setCantidad'), { timeout: 8000 }).catch(() => null);
    await input.press('Enter');
    await resp;
}

async function elegirCliente(page, identificacion) {
    const id = identificacion || config.datos_prueba.cliente_identificacion;
    // El botón está abreviado como "Client." en el panel de acciones
    const btnCliente = page.getByRole('button', { name: /Client\.|^Cliente$/i }).first();
    if (await btnCliente.isVisible({ timeout: 3000 }).catch(() => false)) {
        await btnCliente.click();
        const inputCi = page.locator('input[placeholder*="cédula" i], input[placeholder*="ci" i], input[name*="identif" i], input[placeholder*="identif" i]').first();
        if (await inputCi.isVisible({ timeout: 3000 }).catch(() => false)) {
            await inputCi.fill(id);
            await inputCi.press('Enter');
        }
    }
}

async function facturar(page) {
    const respPromise = page.waitForResponse((r) => r.url().includes('/setPagoPedido'), { timeout: 20_000 });
    // El botón aparece abreviado como "Fact." en el panel de acciones
    await page.getByRole('button', { name: /Fact\.|Facturar/i }).first().click();
    return respPromise;
}

module.exports = {
    config,
    setupCajero,
    login,
    abrirCaja,
    nuevoPedido,
    agregarProducto,
    setearCantidadUltimoItem,
    elegirCliente,
    facturar,
    inputDeMetodoPago,
};
