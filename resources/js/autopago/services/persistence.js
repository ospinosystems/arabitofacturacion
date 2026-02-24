/**
 * Persistencia de sesión del kiosco de autopago en IndexedDB.
 * Guarda carrito, datos del cliente, paso actual y pagos validados
 * para recuperar el progreso al recargar la página.
 */

const DB_NAME = 'autopago_db';
const STORE_NAME = 'session';
const KEY = 'current';
const SESSION_TTL_MS = 24 * 60 * 60 * 1000; // 24 horas

function openDB() {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(DB_NAME, 1);
    req.onerror = () => reject(req.error);
    req.onsuccess = () => resolve(req.result);
    req.onupgradeneeded = (e) => {
      const db = e.target.result;
      if (!db.objectStoreNames.contains(STORE_NAME)) {
        db.createObjectStore(STORE_NAME);
      }
    };
  });
}

/**
 * @returns {Promise<{ cart: { items: any[], orderId: string|null }, userData: object, currentStep: string, validatedPayments: any[], savedAt: number } | null>}
 */
export function getSession() {
  return openDB().then((db) => {
    return new Promise((resolve, reject) => {
      const tx = db.transaction(STORE_NAME, 'readonly');
      const store = tx.objectStore(STORE_NAME);
      const req = store.get(KEY);
      req.onerror = () => reject(req.error);
      req.onsuccess = () => {
        const session = req.result ?? null;
        db.close();
        if (session && session.savedAt && Date.now() - session.savedAt > SESSION_TTL_MS) {
          resolve(null);
          return;
        }
        resolve(session);
      };
    });
  }).catch(() => null);
}

/**
 * @param {{ cart: { items: any[], orderId: string|null }, userData: object, currentStep: string, validatedPayments: any[] }} session
 */
export function setSession(session) {
  if (!session) return Promise.resolve();
  const payload = {
    cart: session.cart ?? { items: [], orderId: null },
    userData: session.userData ?? { id: '', nombre: '', telefono: '' },
    currentStep: session.currentStep ?? 'welcome',
    validatedPayments: Array.isArray(session.validatedPayments) ? session.validatedPayments : [],
    savedAt: Date.now(),
  };
  return openDB().then((db) => {
    return new Promise((resolve, reject) => {
      const tx = db.transaction(STORE_NAME, 'readwrite');
      const store = tx.objectStore(STORE_NAME);
      const req = store.put(payload, KEY);
      req.onerror = () => reject(req.error);
      req.onsuccess = () => {
        db.close();
        resolve();
      };
    });
  }).catch(() => {});
}

export function clearSession() {
  return openDB().then((db) => {
    return new Promise((resolve, reject) => {
      const tx = db.transaction(STORE_NAME, 'readwrite');
      const store = tx.objectStore(STORE_NAME);
      const req = store.delete(KEY);
      req.onerror = () => reject(req.error);
      req.onsuccess = () => {
        db.close();
        resolve();
      };
    });
  }).catch(() => {});
}
