// @ts-check
/**
 * globalSetup — corre UNA vez antes de toda la suite.
 *
 * 1. Valida que .config.json esté completo.
 * 2. Levanta el mock de Pinpad/Megasoft en el puerto definido en config.
 *    No tocamos la BD del cajero: usamos Playwright `page.route` para inyectar
 *    `ip_pinpad` en el body de cada llamada a /enviarTransaccionPOS (ver helpers.js).
 *
 * No tocamos arabitocentral: los tests asumen que ya está corriendo en el puerto
 * configurado y que existe un admin con permisos para aprobar solicitudes.
 */
const config = require('./config');
const { startPinpadMock } = require('./mocks/pinpad-mock');

async function globalSetup() {
    // Forzar lectura del config — si falta o está mal, falla acá con mensaje claro
    const cfg = config; // proxy carga al primer get

    const pinpadPort = cfg.mocks?.pinpad_port || 9001;
    console.log(`\n[setup] Levantando mock Pinpad en 127.0.0.1:${pinpadPort}`);
    await startPinpadMock(pinpadPort);

    console.log(`[setup] arabitofacturacion: ${cfg.arabitofacturacion.baseUrl}`);
    console.log(`[setup] arabitocentral:    ${cfg.arabitocentral.baseUrl}`);
    console.log(`[setup] cajero:            ${cfg.arabitofacturacion.cajero.usuario}`);
    console.log(`[setup] admin central:     ${cfg.arabitocentral.admin.usuario}`);
    console.log(`[setup] Listo.\n`);
}

module.exports = globalSetup;
