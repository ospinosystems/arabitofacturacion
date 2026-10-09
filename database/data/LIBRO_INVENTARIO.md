# Libro de entradas y salidas de inventario

Registro de entradas y salidas de mercancías (Art. 177 del Reglamento de la LISLR) reconstruido **solo con documentos**,
sin stock inicial ni stock actual:

| | Fuente | Documento | Cantidad | Costo |
|---|---|---|---|---|
| **Entradas** | `pedidos` de central con destino la sucursal: `FACTURA` (CxP con factura fiscal), `NOTA` (CxP sin factura fiscal) y `TRANSFERENCIA` (pedidos sin CxP: traslados entre sucursales). Los tres cuentan por defecto (criterio del usuario, 09-oct-2026); la vista permite acotar a solo FACTURA | N° de factura del proveedor (`numfact`) y nota de entrega (`numnota`) | `items_pedidos.cantidad` (la facturada; `ct_real` se guarda aparte) | `items_pedidos.base` (USD) y `tasa_bs` de la factura |
| **Salidas** | Facturas de venta cuadradas (`pedidos.valido = 1`) | N° de factura + máquina fiscal | `items_pedidos.cantidad` (negativa = devolución dentro de un cambio) | Costo promedio ponderado móvil del producto; Bs a la tasa de la venta |

Existencia de cada producto = Σ entradas − Σ salidas desde el primer documento. Si queda negativa, se marca (hubo
ventas sin factura de compra previa registrada: traslados o notas no incluidos, o compras anteriores a los datos).

## Flujo

1. **Importar entradas** (solo lectura sobre central; la conexión se abre con `SET SESSION TRANSACTION READ ONLY`):

   ```bash
   php artisan inventario:importar-entradas --sucursal-central=46 --central-env=/home/master/applications/dckjgythht/public_html/.env
   ```

   `--sucursal-central` es el id de la sucursal en `sucursals` de central (Punto Fijo = 46). Las credenciales se toman
   del `.env` de central indicado, o de `DB_CENTRAL_HOST/PORT/DATABASE/USERNAME/PASSWORD` en el `.env` local.
   Se puede repetir: actualiza por `central_pedido_id`. Para otra sucursal basta cambiar `--sucursal-central` y
   `INVENTARIO_SUCURSAL_CENTRAL_ID`.

   Mapeo de productos: el id del producto es el mismo en central (`inventario_sucursals.idinsucursal`) y en la
   sucursal (`inventarios.id`); si no existe localmente se busca por código de barras, código de proveedor o
   descripción del almacén de origen, y si tampoco existe se crea en `inventarios` con el mismo id (`mapeo = creado`).

2. **Consultar**: `/reportes/libro-inventario` (resumen por producto con filtros de período, tipos de entrada, búsqueda
   y existencia negativa), `/reportes/libro-inventario/producto/{id}` (kardex), `/reportes/libro-inventario/entradas`
   (facturas de compra). Exportaciones: resumen CSV, movimientos CSV (todo el período o un producto), entradas CSV y
   el libro en PDF (membrete con razón social, RIF, sucursal y período).

Tablas: `inventario_entradas` (cabecera por pedido de central) e `inventario_entrada_items`
(migración `2026_10_08_120000_create_inventario_entradas_tables`, ejecutar con `--path`).
