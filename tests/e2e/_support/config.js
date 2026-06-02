// @ts-check
/**
 * Carga la configuración del entorno de tests desde `tests/e2e/.config.json`.
 *
 * Si el archivo no existe, tira un error con instrucciones claras (copiá .config.example.json).
 * Validamos los campos críticos al arrancar para fallar rápido en vez de en mitad de un test.
 */
const fs = require('fs');
const path = require('path');

const configPath = path.resolve(__dirname, '..', '.config.json');

let cache = null;
function load() {
    if (cache) return cache;
    if (!fs.existsSync(configPath)) {
        throw new Error(
            `tests/e2e/.config.json no existe.\n` +
            `Copialo desde tests/e2e/.config.example.json y llená los datos de tu entorno:\n` +
            `   cp tests/e2e/.config.example.json tests/e2e/.config.json\n`
        );
    }
    const raw = JSON.parse(fs.readFileSync(configPath, 'utf8'));
    validar(raw);
    cache = raw;
    return cache;
}

function validar(cfg) {
    const errores = [];
    if (!cfg?.arabitofacturacion?.baseUrl) errores.push('arabitofacturacion.baseUrl');
    if (!cfg?.arabitofacturacion?.cajero?.usuario || cfg.arabitofacturacion.cajero.usuario === 'CAMBIAR')
        errores.push('arabitofacturacion.cajero.usuario');
    if (!cfg?.arabitofacturacion?.cajero?.password || cfg.arabitofacturacion.cajero.password === 'CAMBIAR')
        errores.push('arabitofacturacion.cajero.password');
    if (!cfg?.arabitocentral?.baseUrl) errores.push('arabitocentral.baseUrl');
    if (!cfg?.arabitocentral?.admin?.usuario || cfg.arabitocentral.admin.usuario === 'CAMBIAR')
        errores.push('arabitocentral.admin.usuario');
    if (!cfg?.datos_prueba?.producto_codigo_barras || cfg.datos_prueba.producto_codigo_barras === 'CAMBIAR')
        errores.push('datos_prueba.producto_codigo_barras');
    if (!cfg?.datos_prueba?.cliente_identificacion) errores.push('datos_prueba.cliente_identificacion');
    if (errores.length > 0) {
        throw new Error(`tests/e2e/.config.json tiene campos sin completar: ${errores.join(', ')}`);
    }
}

module.exports = new Proxy({}, {
    get(_, key) {
        return load()[key];
    },
});
