# Cuadre diario por máquina fiscal

El comando `php artisan cuadre:pedidos-diario {archivo}` procesa un CSV **o XLSX** con filas por día, máquina fiscal y tipo de movimiento. El valor **CONCEPTO** (o MAQUINA_FISCAL en formato antiguo) del CSV se guarda en **pedidos.maquina_fiscal**.

Para el proceso completo de una sucursal (restaurar respaldo, importar Titanio POS, cuadrar y medir) ver `RUNBOOK_CUADRE_ANACO.md` (`php artisan cuadre:completo <carpeta>`), que acepta varios archivos mensuales y los fusiona.

Fechas aceptadas: `YYYY-MM-DD`, `DD/MM/YYYY`, `DD-MM-YYYY`, `YYYY/MM/DD`, fechas reales de Excel. Montos: `12345.67`, `12.345,67`, `12,345.67`.

## Campo en pedidos

La tabla `pedidos` tiene el campo **maquina_fiscal** (string, nullable). Se rellena con el valor que viene en la columna CONCEPTO (o MAQUINA_FISCAL) del CSV al procesar cada fila.

```bash
php artisan migrate   # añade la columna maquina_fiscal a pedidos si existe la migración
```

## Formato del CSV (nuevo)

Columnas (cabecera obligatoria):

| Columna          | Descripción |
|------------------|-------------|
| **FECHA**        | Fecha del día (YYYY-MM-DD). |
| **CONCEPTO**     | Identificador de la máquina fiscal (ej. ZZN0027439). Se guarda en pedidos.maquina_fiscal. |
| **FACTURA**      | Rango `inicio-fin` (ej. `1-48`) o un solo número para factura unitaria (ej. `49`). |
| **VENTA**        | Monto en Bs (formato xxxxx.xx). Positivo para ventas, negativo para notas de crédito. |
| **TIPO**         | Uno de: **FISCAL RANGO**, **FISCAL UNITARIA**, **REDUCE EL TOTAL DE ESE DIA**. |

Otras columnas (CEDULA, SERIE, NOTA DE CREDITO, AFECTADA, NUMERO DE Z) se aceptan y se ignoran.

### Tipos de fila (TIPO)

- **FISCAL RANGO**: Carga pedidos por rango. CONCEPTO = máquina fiscal. FACTURA = `inicio-fin` (ej. `1-48`). VENTA = monto positivo. Se asignan tantos pedidos como números en el rango.
- **FISCAL UNITARIA**: Una factura para un solo pedido. CONCEPTO = máquina fiscal. FACTURA = un solo número (sin guión). VENTA = monto positivo.
- **REDUCE EL TOTAL DE ESE DIA**: Notas de crédito. VENTA = monto negativo. Reducen el monto objetivo del día (suma de positivos menos negativos). Si CONCEPTO está vacío, la reducción se aplica al primer grupo (fecha, máquina) de ese día.

El **monto objetivo** de un día para una máquina es: suma de VENTA de filas FISCAL RANGO y FISCAL UNITARIA de esa (fecha, CONCEPTO), más la reducción del día si esa máquina es la primera del día.

### Ejemplo CSV (formato nuevo)

```csv
FECHA,CONCEPTO,CEDULA,SERIE,NOTA DE CREDITO,AFECTADA,NUMERO DE Z,FACTURA,VENTA,TIPO
2024-01-15,ZZN0027439,,,,,,1-48,25152.30,FISCAL RANGO
2024-01-15,ZZN0027439,,,,,,49,150.00,FISCAL UNITARIA
2024-01-15,ZZN0028263,,,,,,1-44,28970.22,FISCAL RANGO
2024-01-15,,,,,,,,,-95.00,REDUCE EL TOTAL DE ESE DIA
```

## Libro de ventas mensual (XLSX de la contadora)

`CuadreCsvReader` reconoce directamente los libros de ventas mensuales (formato SENIAT: título, cabecera de dos filas
`FECHA / CLIENTE / C.I./RIF. / N° DE FACTURA SERIE / Nº NOTA DE CREDITO / Nº FC. AFECTADA / Nº. Z / Nº. FACTURA / TOTAL VENTA / …`,
saltos de página con VAN…/…VIENEN) y los convierte al formato canónico:

| Fila del libro | Resultado |
|---|---|
| `RESUMEN DE VENTAS <máquina>` con Nº FACTURA `inicio-fin` | FISCAL RANGO, CONCEPTO = máquina, VENTA = TOTAL VENTA (fórmula evaluada) |
| `RESUMEN DE VENTAS <máquina>` con Nº NOTA DE CREDITO | REDUCE EL TOTAL DE ESE DIA (monto negativo) para esa máquina |
| Cliente con `SERIE R 000123` (factura manual) | FISCAL UNITARIA, CONCEPTO = `SERIE R`, FACTURA = 123 |
| Cliente con nota de crédito | REDUCE; la máquina se infiere del número de factura afectada |
| `ANULADA`, Z sin ventas (factura `0`), filas solo de retención, totales | Se omiten |
| Sección con otra `SUCURSAL …` pegada en el mismo libro | Se omite (aviso) |

Correcciones automáticas, siempre con aviso en el log del paso `preparar` (`avisos()` del lector): año mal digitado
cuando el mes coincide con el del libro, y rangos `inicio-fin` mal digitados reconstruidos por continuidad de la
numeración de cada máquina (la primera factura de un día sigue a la última del anterior).

## Formato antiguo (compatible)

Se sigue aceptando el formato anterior:

| Columna          | Descripción |
|------------------|-------------|
| **FECHA**        | Fecha del día. |
| **MAQUINA_FISCAL** | Identificador de la máquina. |
| **RANGO_FACTURA** | Rango `inicio-fin` (ej. `131-204`). |
| **TOTAL_VENTA**  | Monto en Bs. |

```csv
FECHA,MAQUINA_FISCAL,N_Z,RANGO_FACTURA,TOTAL_VENTA
2023-11-03,ZZN0027439,0001,1-1,0.696
2023-11-06,ZZN0027439,0002,2-48,25152.303200000002
```

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

El comando pide confirmación (salvo con `--si` o `--dry-run`), **restaura los ítems y pagos ajustados** usando la auditoría `cuadre_ajustes` (valores originales de precio_unitario, monto, monto_bs y del pago), borra los ítems de ajuste del mecanismo antiguo y limpia numero_factura, maquina_fiscal y valido. Después puede volver a ejecutar `cuadre:pedidos-diario` con el CSV.

## Uso

```bash
php artisan cuadre:pedidos-diario "c:\Users\alvar\Downloads\maracay2023-2024-2025-2026\resumen_ventas_maracay_ordenado.csv"
php artisan cuadre:pedidos-diario database/data/resumen.csv --dry-run      # solo valida y agrupa el archivo
php artisan cuadre:pedidos-diario database/data/resumen.csv --simular      # selección real sin escribir
php artisan cuadre:pedidos-diario resumen.csv --si --max-segundos=15 --tolerancia-bs=1 --reporte=storage/app/cuadre.csv
```

Opciones: `--desde-cero` (resetea el rango del archivo antes, restaurando ajustes), `--si` (sin confirmación),
`--solo-fecha=`, `--anio=`, `--desde=`, `--hasta=` (filtros de grupos), `--max-segundos=15` (búsqueda por grupo),
`--tolerancia-bs=1` (corte de la búsqueda), `--umbral-ajuste=5` (% para avisar), `--sin-filtro-estado` (incluir
pedidos con estado ≠ 1), `--reporte=` (CSV por grupo: objetivo, candidatos, seleccionados, suma, ajuste, %, real).

Por defecto solo son candidatos los pedidos con `estado = 1` y monto en Bs > 0. Cada ajuste queda registrado en la
tabla `cuadre_ajustes`. Los grupos (fecha + máquina) ya cuadrados se omiten, por lo que el comando puede
interrumpirse y volver a ejecutarse.

## Reporte

**GET** `/reportes/cuadre-diario` — muestra por fecha el monto Bs y rango de facturas (pedidos válidos).
