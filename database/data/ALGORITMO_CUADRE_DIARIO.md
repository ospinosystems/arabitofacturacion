# Cómo funciona el algoritmo de cuadre diario (versión actual)

Objetivo: que **cada día y cada máquina fiscal** quede con exactamente N facturas consecutivas cuya suma en Bs
coincida con el monto objetivo del CSV, con un **único ajuste pequeño** y reversible.

## 1. Qué entra por grupo (fecha + máquina)

Del CSV (`database/data/FORMATO_CUADRE_DIARIO.md`) se obtiene por (FECHA, CONCEPTO):

- **N** = cantidad de facturas del rango (`inicio-fin`, más las FISCAL UNITARIA).
- **Objetivo** = suma de VENTA (Bs) menos las filas `REDUCE EL TOTAL DE ESE DIA` del día (aplicadas a la
  primera máquina del día).

Si ya existen pedidos `valido=1` con esa fecha y esa máquina, el grupo se **omite** (así el comando es reanudable).

## 2. Candidatos

Pedidos del día (`DATE(COALESCE(fecha_factura, created_at)) = fecha`) que:

- no estén ya marcados (`valido` nulo o 0),
- tengan `estado = 1` (facturados; se desactiva con `--sin-filtro-estado`),
- y cuyo monto en Bs sea > 0 (`SUM(COALESCE(monto_bs, monto × tasa))` de sus ítems). Devoluciones y pedidos
  sin ítems quedan fuera.

## 3. Selección de los N pedidos

- Si hay ≤ N candidatos, se toman todos (el comando avisa que el rango queda corto).
- Si hay más, se busca un **subconjunto de exactamente N** pedidos cuya suma se acerque al objetivo:
  1. 20 corridas *greedy* con orden aleatorio (cada paso agrega el pedido que deja la suma más cerca).
  2. *Simulated annealing* (intercambios aleatorios entre seleccionados y no seleccionados) partiendo de la
     mejor solución, hasta agotar `--max-segundos` (15 s por defecto).
  3. La búsqueda se detiene antes al llegar a `|suma − objetivo| ≤ --tolerancia-bs` (1 Bs por defecto): más
     precisión no aporta porque el ajuste final cubre la diferencia.

Los pedidos **no elegidos** siguen en la BD sin número de factura y con `valido` nulo. Así es como un mes con
ventas por 1.000.000 Bs se lleva a un objetivo de 500.000 Bs: se eligen los pedidos que suman el objetivo.

## 4. Numeración

Los N elegidos se ordenan cronológicamente y reciben `numero_factura` consecutivo desde `inicio`,
`maquina_fiscal` = CONCEPTO y `valido = 1`. Todo el grupo se escribe en **una transacción**: si el proceso muere,
el grupo queda completo o no queda.

## 5. Ajuste

Diferencia = objetivo − suma de los N elegidos (normalmente ≤ 1 Bs por la tolerancia; puede ser mayor si el
día tiene pocos pedidos o se agotó el tiempo). Se aplica sobre el **ítem de mayor monto del último pedido** del
grupo:

- nuevo precio unitario (USD) = (monto_bs actual + diferencia) / tasa / cantidad, **redondeado a 1 decimal**
  (precio "creíble"); se recalculan `monto` y `monto_bs`,
- se recalcula el primer `pago_pedidos` del pedido con la suma real de ítems (o se crea uno si no existe),
- se guarda en **`cuadre_ajustes`** el valor original del ítem y del pago, el ajuste pedido y el aplicado.

Por el redondeo a 1 decimal, la suma real puede diferir del objetivo en unos Bs por grupo (≤ 0,05 USD ×
cantidad × tasa). El comando reporta la suma **real**; la validación web tolera 2 Bs o 0,05 % del objetivo.

## 6. Reverso

`cuadre:pedidos-reset` (o `cuadre:pedidos-diario --desde-cero`) usa `CuadreResetService`: restaura los ítems y
pagos desde `cuadre_ajustes` (del ajuste más reciente al más antiguo), borra pagos creados por el cuadre, elimina
los "ítems de ajuste" del mecanismo antiguo y limpia `numero_factura`, `maquina_fiscal` y `valido`. Verificado con
huella MD5 de ítems y pagos: tras el reset quedan idénticos al respaldo previo.

## 7. Costo

Por grupo: 2 consultas (pedidos y suma de ítems) y la búsqueda acotada en tiempo. Medido: ~0,35 s por grupo con
tolerancia 1 Bs; peor caso `--max-segundos` por grupo. Ver `RUNBOOK_CUADRE_ANACO.md` para la estimación completa.
