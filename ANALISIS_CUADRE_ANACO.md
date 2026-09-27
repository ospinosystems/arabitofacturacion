# Análisis: cuadre por monto objetivo + importación Titanio POS (sucursal Anaco)

Fecha del análisis: 2026-09-27. Rama: `claude/anaco-order-transformation-command-6v5vg2`.

> **Actualización (misma fecha):** los hallazgos de la sección 2.4 y el plan de la sección 6 ya están
> implementados en esta rama: auditoría `cuadre_ajustes` + reset que restaura, `--simular`, filtro `estado=1`
> y monto > 0, `--si`, `--tolerancia-bs`, importador Titanio parametrizable (`--store-id`, `--sucursal`,
> `--desde/--hasta`) y el orquestador reanudable `cuadre:completo`. Instrucciones de uso, tiempos y servidor
> en `RUNBOOK_CUADRE_ANACO.md`. El texto siguiente se conserva como registro del estado previo.

Objetivo del trabajo: llevar la facturación de Anaco a un monto objetivo por día y máquina fiscal,
sobre una BD que cubre hasta el 30/08/2026 con el sistema viejo, y completarla con los pedidos
facturados en Titanio POS desde el 30/08/2026 hasta hoy.

---

## 1. Comandos involucrados

| Comando | Archivo | Rol |
|---|---|---|
| `cuadre:pedidos-diario {csv}` | `app/Console/Commands/CuadrePedidosDiario.php` | Selecciona por día y máquina fiscal los N pedidos que más se acercan al monto objetivo, les asigna número de factura y marca `valido=1`. |
| `cuadre:pedidos-reset` | `app/Console/Commands/CuadrePedidosReset.php` | Limpia `numero_factura`, `maquina_fiscal`, `valido` en un rango de fechas. |
| `cuadre:fix-unitarias` | `app/Console/Commands/CuadreFixUnitarias.php` | Parche puntual (IDs de pedido fijos de una corrida anterior). No es reutilizable tal cual. |
| `titanio:importar` | `app/Console/Commands/TitanioImportarPedidos.php` | Importa un día de pedidos desde la API de Titanio POS. |
| `titanio:importar-json` | `app/Console/Commands/TitanioImportarJson.php` | Importa desde un JSON exportado del navegador cuando la API no está disponible. |
| Reporte web | `app/Http/Controllers/CuadreReportController.php` | `/reportes/cuadre-diario` (resumen), `/reportes/cuadre-diario/validacion?csv=...` (compara resultado contra el CSV). |

Documentación existente: `database/data/FORMATO_CUADRE_DIARIO.md` (formato del CSV, vigente) y
`database/data/ALGORITMO_CUADRE_DIARIO.md` (describe un algoritmo anterior, ver 2.4).

---

## 2. Estado actual de `cuadre:pedidos-diario`

### 2.1 Entrada

CSV con cabecera `FECHA, CONCEPTO, ..., FACTURA, VENTA, TIPO`:

- `CONCEPTO` = máquina fiscal (se guarda en `pedidos.maquina_fiscal`).
- `FACTURA` = rango `inicio-fin` (FISCAL RANGO) o un número (FISCAL UNITARIA).
- `VENTA` = monto en **Bs**. Negativo con TIPO `REDUCE EL TOTAL DE ESE DIA` (notas de crédito).
- Se agrupa por (fecha, máquina). N = cantidad de facturas del rango. Objetivo = suma de VENTA del grupo
  menos las reducciones del día (aplicadas a la primera máquina del día).

Opciones: `--dry-run`, `--desde-cero` (resetea el rango del CSV antes de procesar), `--solo-fecha=`, `--anio=`.

### 2.2 Algoritmo real (el que está en el código hoy)

Para cada (fecha, máquina):

1. Si ya existen pedidos `valido=1` con esa fecha y esa máquina, se omite (idempotente).
2. Candidatos: todos los pedidos con `DATE(COALESCE(fecha_factura, created_at)) = fecha` y `valido` nulo o 0.
   No filtra por `estado`, ni por sucursal, ni excluye devoluciones.
3. Monto de cada candidato: `SUM(COALESCE(monto_bs, monto * tasa))` de sus ítems.
4. Selección:
   - Si hay ≤ N candidatos, se toman todos.
   - Si hay más, busca un **subconjunto** de exactamente N pedidos cuya suma se acerque al objetivo:
     20 corridas greedy con semilla aleatoria y luego simulated annealing hasta 15 s por grupo.
     Los pedidos no elegidos quedan con `valido` nulo (siguen en la BD pero fuera del universo fiscal).
     Así es como un mes de 1.000.000 se lleva a 500.000: se eligen los pedidos que suman el objetivo.
5. Los N elegidos se ordenan cronológicamente y reciben `numero_factura` consecutivo desde el inicio del rango,
   `maquina_fiscal` y `valido=1`.
6. Diferencia = objetivo − suma elegida. Se aplica **modificando el precio unitario del ítem más grande del último
   pedido** (redondeado a 1 decimal en USD) y se recalcula `pago_pedidos`. Siempre se aplica, aunque el ajuste supere 5 %
   (solo avisa en consola).

### 2.3 Robustez

- Reconexión a MySQL por iteración y reintentos ante "server has gone away" (pensado para Cloudways).
- Transacción corta solo para escrituras.
- Resumen final con filas procesadas, omitidas, pedidos marcados y monto acumulado.
- El interceptor `pedidos::saving` (bloqueo de cierres descuadrados) solo actúa en la transición `estado` 0→1,
  por lo que **no interfiere** con el cuadre, que solo toca `numero_factura`, `maquina_fiscal` y `valido`.

### 2.4 Hallazgos y riesgos

1. **El reset no deshace el ajuste de precio.** `cuadre:pedidos-reset` y `--desde-cero` borran "ítems de ajuste"
   (`id_producto` nulo, cantidad 1, monto 0), que era el mecanismo antiguo. El código actual modifica
   `precio_unitario`, `monto` y `monto_bs` de un ítem real, y eso **no se restaura**. Si se corre, se resetea y se
   vuelve a correr, los ajustes se acumulan sobre precios ya alterados.
   Mitigación obligatoria: `mysqldump` completo antes de la corrida. Mejora recomendada: guardar los valores
   originales del ítem ajustado (tabla de auditoría) para que el reset los restaure.
2. **`--dry-run` no simula la selección.** Solo valida y agrupa el CSV; nunca entra a `procesarDiaMaquina`.
   No hay forma de ver el ajuste por día antes de escribir. Mejora recomendada: modo simulación que corra la
   selección y reporte objetivo, suma elegida, diferencia y % de ajuste sin escribir.
3. **`monto_total_dia` reportado es el objetivo, no la suma real.** Por el redondeo a 1 decimal del precio unitario,
   la suma real difiere del objetivo en centavos. La validación web usa tolerancia de 0,01 Bs y puede marcar
   `check_csv=false` por ese redondeo. Conviene ampliar la tolerancia o reportar la suma real.
4. **Filtro de candidatos demasiado amplio.** No exige `estado=1` (facturado) ni excluye pedidos con monto ≤ 0
   (devoluciones/cambios). Puede elegir pedidos pendientes o negativos como facturas fiscales.
   Recomendación: restringir a `estado=1` y monto > 0.
5. **Si el objetivo supera la suma disponible del día**, el ajuste cae entero en un solo pedido y puede ser
   irreal. El comando avisa (`ajuste alto`) pero no detiene. Hay que revisar esas filas a mano.
6. **Documentación desactualizada.** `ALGORITMO_CUADRE_DIARIO.md` describe ventanas consecutivas e ítem de ajuste;
   el código hace selección de subconjunto y ajuste de precio.
7. Tiempo: hasta 15 s por (día, máquina). Para 13 meses con 2 máquinas son ≈ 800 grupos → hasta ~3,5 h en el
   peor caso. Aceptable, pero conviene correrlo en `screen`/`nohup`.

---

## 3. Estado actual de la importación desde Titanio POS

### 3.1 `titanio:importar` (API)

- `POST https://www.titanio-pos.com/api/orders/raw` con `{fecha, storeId}` y cabecera `x-secret`.
- Importa un **solo día** por ejecución (`--fecha=`). Para 30/08 → 27/09 son 29 ejecuciones o un bucle.
- **Está cableado a Guacara**: aborta si `sucursals.codigo !== 'guacara'` y usa `STORE_ID = 14`.
  Para Anaco hay que parametrizar `--store-id` y `--sucursal` (o quitar la comprobación).
- Requisitos en la BD destino:
  - Un usuario `caja{N}` en `usuarios` por cada `numero_caja` que venga de Titanio. Si falta, el pedido falla.
  - `items[].source_id` debe existir en `inventarios.id`. Si Anaco migró su inventario con `inventario:migrar`,
    debería coincidir. Si no, todos los ítems fallan.
  - Tipos de pago soportados: `pinpad`, `debito`, `efectivo_usd`, `efectivo_ves`, `transferencia`.
    Cualquier otro tipo lanza excepción y **el pedido completo se salta**.
- Escribe `pedidos` (`estado=1`, `valido` nulo, `fecha_factura = created_at` de Titanio), `items_pedidos`
  (con `monto_bs = monto × tasa`), `pago_pedidos`, `pagos_referencias`, y **descuenta inventario** con
  movimientos de origen `IMPORT.TITANIO`.
- Idempotente por `uuid`: si ya existe, repone inventario, borra y reinserta. `--reversar` deshace un día.
- `created_at` de la API se guarda tal cual (sin conversión de zona horaria). Verificar que no desplace
  pedidos de la noche al día siguiente, porque el cuadre agrupa por `DATE(fecha_factura)`.
- El secreto de la API está en el código fuente. Conviene moverlo a `.env`.

### 3.2 `titanio:importar-json` (respaldo del navegador)

- Misma lógica, pero lee `data.orders` de un JSON; resuelve producto por `codigo_barras`, caja por
  `daily_cash_count_id` (o `--caja=`), tasa por `exchange_rate`. Métodos de pago numéricos (2, 3, 5, 6).
- También cableado a Guacara. Sirve como plan B si la API no responde para Anaco.

### 3.3 Compatibilidad con el cuadre

Los pedidos importados quedan con `valido` nulo y `monto_bs` calculado, así que entran como candidatos del
cuadre sin cambios adicionales. La tasa usada es la inferida de los pagos del propio pedido (o la global de
`monedas`), por lo que el monto en Bs por día debería ser consistente con lo que reporta Titanio.

---

## 4. Estado del entorno de esta sesión

- El repositorio no trae `.env` ni `vendor/`; no hay MySQL ni Docker disponibles en el contenedor.
- Para ejecutar los comandos hace falta: restaurar el dump de Anaco en un MySQL, `composer install`, `.env`
  apuntando a esa BD, y acceso de red a `titanio-pos.com` (para la importación por API).

---

## 5. Insumos que faltan para ejecutar

1. **Dump de la BD de Anaco** (hasta el 30/08/2026).
2. **`storeId` de Anaco en Titanio POS** (Guacara es 14). Confirmar si la sucursal es `anaco` o `anaco2`
   (ambos códigos existen en `tickera.php`).
3. **CSV de montos objetivo** con el formato de `FORMATO_CUADRE_DIARIO.md` para todo el período a cuadrar,
   con VENTA en Bs por día y máquina fiscal y los rangos de factura reales.
4. Lista de **cajas** que operan en Anaco (para verificar usuarios `caja{N}`).
5. Confirmar si el inventario de Anaco fue migrado a Titanio con `inventario:migrar` (define si `source_id`
   coincide con `inventarios.id`).

---

## 6. Plan de ejecución propuesto

1. Restaurar el dump de Anaco en MySQL y hacer una copia intacta (`mysqldump`) antes de tocar nada.
2. Cambios de código mínimos previos:
   - `titanio:importar`: opciones `--store-id=`, `--sucursal=` (o quitar el check), `--desde=` / `--hasta=`
     para recorrer el rango de fechas en una sola ejecución; secreto a `.env`.
   - `cuadre:pedidos-diario`: modo simulación real, filtro `estado=1` y monto > 0, registro de valores originales
     del ítem ajustado para que el reset pueda restaurarlos.
3. Importación: `titanio:importar --fecha=2026-08-30 --dry-run -v` para validar cajas, productos y tipos de pago.
   Corregir faltantes y luego importar 30/08 → 27/09. Verificar por día: cantidad de pedidos y suma en USD/Bs
   contra el cierre de Titanio.
4. Segundo `mysqldump` con los pedidos importados (punto de retorno para el cuadre).
5. Cuadre: `cuadre:pedidos-diario anaco.csv --dry-run` (valida CSV), luego la simulación, luego la corrida real.
6. Medición:
   - Salida del comando: filas procesadas, pedidos marcados, avisos de `ajuste alto`.
   - `/reportes/cuadre-diario/validacion?csv=/ruta/anaco.csv&fecha_desde=...&fecha_hasta=...`:
     compara monto, rango y cantidad por día y máquina, más el chequeo "natural" (ningún pedido supera 50 % del día).
   - `/reportes/cuadre-diario/export` para el resumen en CSV.
   - Consulta directa: `SUM(monto_bs)` de ítems de pedidos `valido=1` por mes contra el objetivo mensual.
