import { openDB } from "idb";

const DB_NAME = "arabitofacturacion";
const DB_VERSION = 1;
const STORE_NAME = "pedidosFront";
const KEY = "pendientes";
const KEY_PENDIENTE_DESCUENTO_METODO_PAGO = "pendienteDescuentoMetodoPago";
const KEY_METODOS_PAGO_APROBADOS_POR_UUID = "metodosPagoAprobadosPorUuid";

let dbPromise = null;

function getDB() {
    if (!dbPromise) {
        dbPromise = openDB(DB_NAME, DB_VERSION, {
            upgrade(db) {
                if (!db.objectStoreNames.contains(STORE_NAME)) {
                    db.createObjectStore(STORE_NAME);
                }
            },
        });
    }
    return dbPromise;
}

/**
 * Obtiene los pedidos pendientes de front guardados en IndexedDB.
 * @returns {Promise<Object>} Objeto { [uuid]: pedido }
 */
export async function getPedidosFront() {
    try {
        const db = await getDB();
        const data = await db.get(STORE_NAME, KEY);
        return data && typeof data === "object" ? data : {};
    } catch (e) {
        console.warn("pedidosFrontStorage getPedidosFront:", e);
        return {};
    }
}

/**
 * Guarda los pedidos pendientes de front en IndexedDB.
 * @param {Object} pedidos - Objeto { [uuid]: pedido }
 */
export async function setPedidosFront(pedidos) {
    try {
        const db = await getDB();
        await db.put(STORE_NAME, pedidos, KEY);
    } catch (e) {
        console.warn("pedidosFrontStorage setPedidosFront:", e);
    }
}

/**
 * Obtiene las solicitudes de descuento por método de pago pendientes (por uuid) desde IndexedDB.
 * @returns {Promise<Object>} Objeto { [uuid]: { submittedAt, metodos_pago } }
 */
export async function getPendienteDescuentoMetodoPago() {
    try {
        const db = await getDB();
        const data = await db.get(STORE_NAME, KEY_PENDIENTE_DESCUENTO_METODO_PAGO);
        return data && typeof data === "object" ? data : {};
    } catch (e) {
        console.warn("pedidosFrontStorage getPendienteDescuentoMetodoPago:", e);
        return {};
    }
}

/**
 * Guarda las solicitudes de descuento por método de pago pendientes en IndexedDB.
 * @param {Object} data - Objeto { [uuid]: { submittedAt, metodos_pago } }
 */
export async function setPendienteDescuentoMetodoPagoStorage(data) {
    try {
        const db = await getDB();
        await db.put(STORE_NAME, data, KEY_PENDIENTE_DESCUENTO_METODO_PAGO);
    } catch (e) {
        console.warn("pedidosFrontStorage setPendienteDescuentoMetodoPagoStorage:", e);
    }
}

/**
 * Obtiene los métodos de pago aprobados por uuid (descuento por método de pago ya aprobado).
 * @returns {Promise<Object>} Objeto { [uuid]: metodos_pago[] }
 */
export async function getMetodosPagoAprobadosPorUuid() {
    try {
        const db = await getDB();
        const data = await db.get(STORE_NAME, KEY_METODOS_PAGO_APROBADOS_POR_UUID);
        return data && typeof data === "object" ? data : {};
    } catch (e) {
        console.warn("pedidosFrontStorage getMetodosPagoAprobadosPorUuid:", e);
        return {};
    }
}

/**
 * Guarda los métodos de pago aprobados por uuid en IndexedDB.
 * @param {Object} data - Objeto { [uuid]: metodos_pago[] }
 */
export async function setMetodosPagoAprobadosPorUuidStorage(data) {
    try {
        const db = await getDB();
        await db.put(STORE_NAME, data, KEY_METODOS_PAGO_APROBADOS_POR_UUID);
    } catch (e) {
        console.warn("pedidosFrontStorage setMetodosPagoAprobadosPorUuidStorage:", e);
    }
}
