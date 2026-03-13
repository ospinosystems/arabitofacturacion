# Cuadre diario por máquina fiscal

El comando `php artisan cuadre:pedidos-diario {archivo}` procesa un CSV con **una fila por día y por máquina fiscal**. El valor **MAQUINA_FISCAL** del CSV se guarda en el campo **pedidos.maquina_fiscal** (no se usa tabla de mapeo).

## Campo en pedidos

La tabla `pedidos` tiene el campo **maquina_fiscal** (string, nullable). Se rellena con el valor que viene en la columna MAQUINA_FISCAL del CSV al procesar cada fila.

```bash
php artisan migrate   # añade la columna maquina_fiscal a pedidos si existe la migración
```

## Formato del CSV

Columnas (cabecera obligatoria; se acepta mayúsculas/minúsculas):

| Columna          | Descripción |
|------------------|-------------|
| **FECHA**        | Fecha del día (YYYY-MM-DD). |
| **MAQUINA_FISCAL** | Identificador de la máquina (ej. ZZN0027439). Se guarda en pedidos.maquina_fiscal. |
| **N_Z**          | Opcional (puede ir vacío). |
| **RANGO_FACTURA** | Rango de números de factura para esa máquina ese día: `inicio-fin` (ej. `131-204`, `1-1`). |
| **TOTAL_VENTA**  | Monto total en Bs que debe sumar ese día para esa máquina. |

Cada fila = un día + una máquina. El mismo día puede tener varias filas (una por máquina). Se procesan en el orden del CSV: para cada fila se toman pedidos de esa fecha aún no válidos, se elige la ventana óptima y se les asigna el rango de facturas y **maquina_fiscal** (valor del CSV).

## Ejemplo (resumen_ventas_maracay_ordenado.csv)

```csv
FECHA,MAQUINA_FISCAL,N_Z,RANGO_FACTURA,TOTAL_VENTA
2023-11-03,ZZN0027439,0001,1-1,0.696
2023-11-03,ZZN0028263,0001,1-1,0.696
2023-11-06,ZZN0027439,0002,2-48,25152.303200000002
2023-11-06,ZZN0028263,0002,2-44,28970.2252
```

- Filas con `RANGO_FACTURA` vacío o que no cumplan el formato `inicio-fin` se omiten.

## Resetear para empezar de nuevo

Si hubo un error y quiere volver a ejecutar el cuadre desde cero (o desde una fecha):

```bash
# Resetea TODOS los pedidos validos (numero_factura, maquina_fiscal, valido + borra items de ajuste)
php artisan cuadre:pedidos-reset

# Resetea solo desde una fecha (incluye esa fecha hacia adelante)
php artisan cuadre:pedidos-reset --fecha_desde=2024-01-01

# Resetea solo un rango de fechas
php artisan cuadre:pedidos-reset --fecha_desde=2024-01-01 --fecha_hasta=2024-06-30

# Ver que se resetearia sin tocar la BD
php artisan cuadre:pedidos-reset --dry-run
```

El comando pide confirmacion (salvo en --dry-run), borra los ítems de ajuste creados por el cuadre, recalcula el pago de esos pedidos y limpia numero_factura, maquina_fiscal y valido. Despues puede volver a ejecutar `cuadre:pedidos-diario` con el CSV.

## Uso

```bash
php artisan cuadre:pedidos-diario "C:\ruta\resumen_ventas_maracay_ordenado.csv"
php artisan cuadre:pedidos-diario database/data/resumen.csv --dry-run
```

## Reporte

**GET** `/reportes/cuadre-diario` — muestra por fecha el monto Bs y rango de facturas (pedidos válidos).
