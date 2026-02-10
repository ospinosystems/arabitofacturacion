import { api } from './api';

const IMAGE_PLACEHOLDER = 'https://placehold.co/140x140/E5E7EB/6B7280?text=Producto';

/**
 * Mapea un item de inventario (API getinventario) al formato del carrito.
 * Usa bs (precio en Bs) y precio (precio en USD) que vienen del API.
 * @param {Object} inv - Item de inventario (id, codigo_barras, descripcion, precio, bs, unidad, etc.)
 * @returns {Object} Producto en formato carrito { id, ean, name, price, precio, unit, image }
 */
export function mapInventarioToCartProduct(inv) {
  const bs = parseFloat(inv.bs);
  const precio = parseFloat(inv.precio);
  const cantidad = inv.cantidad != null ? Number(inv.cantidad) : null;
  return {
    id: inv.id,
    ean: inv.codigo_barras || '',
    codigo_proveedor: inv.codigo_proveedor ?? '',
    name: inv.descripcion || 'Sin nombre',
    price: !Number.isNaN(bs) ? bs : (!Number.isNaN(precio) ? precio : 0),
    precio: !Number.isNaN(precio) ? precio : null,
    unit: inv.unidad || 'pza',
    image: inv.imagen || IMAGE_PLACEHOLDER,
    category: inv.categoria?.descripcion || null,
    stock: cantidad != null && !Number.isNaN(cantidad) ? cantidad : null,
  };
}

/**
 * Busca producto por código de barras (exacto).
 * @param {string} barcode - Código de barras
 * @returns {Promise<Object|null>} Producto mapeado o null si no existe
 */
export async function searchByBarcode(barcode) {
  if (!barcode || barcode.trim().length < 8) return null;
  const { data } = await api.getInventario({
    exacto: 'si',
    qProductosMain: barcode.trim(),
    num: 1,
    itemCero: false,
  });
  if (Array.isArray(data) && data.length > 0) {
    const inv = data[0];
    if (inv.cantidad > 0) {
      return mapInventarioToCartProduct(inv);
    }
  }
  return null;
}

/**
 * Busca productos por texto (descripción, código, etc.).
 * @param {string} query - Término de búsqueda
 * @param {number} limit - Máximo de resultados
 * @returns {Promise<Object[]>} Lista de productos mapeados
 */
export async function searchProducts(query, limit = 20) {
  if (!query || query.trim().length < 2) return [];
  const { data } = await api.getInventario({
    qProductosMain: query.trim(),
    num: limit,
    itemCero: false,
  });
  if (Array.isArray(data)) {
    return data
      .filter((inv) => inv.cantidad > 0)
      .map(mapInventarioToCartProduct);
  }
  return [];
}
