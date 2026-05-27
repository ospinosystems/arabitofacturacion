/**
 * Helpers compartidos para tratar valores del campo `banco` cuando provienen
 * del catálogo central (`bancosCentral`).
 *
 * Formato persistido en BD (sucursal y central) desde el FIX 2026-05-26:
 *   - Nuevos selects guardan `"bid:<id>"` (ej. "bid:50").
 *   - Datos legacy todavía pueden traer el codigo string (ej. "0134") o el
 *     codigo descriptivo viejo (ej. "0134 BANESCO TITANIO EL HENAOUI 2765").
 *
 * Estos helpers resuelven cualquier formato a un objeto banco del catálogo,
 * para mostrar la descripción correcta al usuario o decidir comportamientos
 * (moneda, etc.) sin importar cómo se guardó originalmente.
 */

/**
 * Busca el objeto banco en `bancosCentral` que corresponde al `value` dado.
 * `value` puede ser:
 *   - "bid:<id>"  → busca por b.id
 *   - "<codigo>"  → busca por b.codigo exacto
 *   - "" / null   → devuelve null
 *
 * Devuelve el objeto banco del catálogo o null si no se resuelve.
 */
export function resolverBancoPorValue(value, bancos) {
    if (!value) return null;
    const lista = Array.isArray(bancos) ? bancos : [];
    const v = String(value);
    if (v.startsWith('bid:')) {
        const id = Number(v.slice(4));
        if (!isNaN(id)) {
            return lista.find(b => Number(b.id) === id) || null;
        }
        return null;
    }
    return lista.find(b => b.codigo === v) || null;
}

/**
 * Descripción para mostrar en UI. Cae al value crudo si no encuentra el banco
 * en el catálogo (banco eliminado, catálogo aún no cargó, datos legacy raros).
 */
export function descripcionBanco(value, bancos) {
    if (!value) return '';
    const b = resolverBancoPorValue(value, bancos);
    return b ? (b.descripcion || b.codigo) : String(value);
}

/**
 * Normaliza un value persistido (probablemente legacy con codigo string) al
 * formato canónico "bid:<id>" si se puede resolver el banco en el catálogo.
 * Si no se encuentra el banco, devuelve el value original para que el select
 * lo conserve y el cajero pueda re-elegir.
 */
export function normalizarValueBanco(value, bancos) {
    if (!value) return '';
    const v = String(value);
    if (v.startsWith('bid:')) return v;
    const b = resolverBancoPorValue(v, bancos);
    return b ? `bid:${b.id}` : v;
}

/**
 * True si el banco seleccionado opera en divisas (moneda='dolar'). Antes esto
 * estaba hardcoded como `['ZELLE','BINANCE','AirTM'].includes(value)`; ahora
 * se infiere del catálogo, que es la fuente de verdad.
 *
 * Compat: si el banco no se encuentra (legacy) pero el value es uno de los
 * nombres conocidos en mayúsculas, devuelve true para preservar comportamiento.
 */
export function bancoSeleccionadoEsDivisa(value, bancos) {
    if (!value) return false;
    const b = resolverBancoPorValue(value, bancos);
    if (b) {
        return (b.moneda || '').toLowerCase() === 'dolar';
    }
    const v = String(value).toUpperCase();
    return v === 'ZELLE' || v === 'BINANCE' || v === 'AIRTM';
}
