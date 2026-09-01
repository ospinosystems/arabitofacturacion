# WMS / TMS

Documentación del módulo de gestión de almacén y transporte construido sobre el
warehouse-inventory existente.

---

## Qué se agregó

| Fase | Qué resuelve | Piezas |
|------|--------------|--------|
| 1 | Datos físicos (peso, volumen, cubicaje) | migraciones + `DatosFisicosEstimadosSeeder` |
| 2 | Picking FEFO | `PickingStrategyService` |
| 3 | Clasificación ABC (Pareto) | `AbcClassificationService` + `wms:abc-recalcular` |
| 4 | Sugerencia de ubicación (slotting) | `SlottingService` + captura de decisiones |
| 5 | Conteo cíclico por ubicación | `ConteoCiclicoService` |
| 6 | TMS: flota, carga, rutas, POD | `PlanificacionCargaService` |

Todo lo ajustable vive en **`config/wms.php`**: umbrales del ABC, pesos del scoring
de slotting, estrategia de picking, frecuencias de conteo y parámetros del TMS.

---

## Instalación

```bash
php artisan migrate
```

Las migraciones nuevas son:

```
2026_08_27_100000_add_datos_fisicos_to_inventarios
2026_08_27_100010_add_slotting_to_warehouses
2026_08_27_100020_create_producto_abc_table
2026_08_27_100030_create_putaway_sugerencias_table
2026_08_27_100040_create_conteos_ciclicos_tables
2026_08_27_100050_create_tms_tables
```

Después, en orden:

```bash
# 1. Clasificar el catálogo por rotación real (usa items_pedidos + pedidos)
php artisan wms:abc-recalcular

# 2. Estimar peso y volumen para poder probar el flujo (marca fuente = 'estimado')
php artisan db:seed --class=DatosFisicosEstimadosSeeder
```

Sólo para entornos de prueba, si no hay almacén cargado:

```bash
php artisan db:seed --class=LayoutAlmacenDemoSeeder   # 580 ubicaciones de ejemplo
php artisan db:seed --class=StockDemoWmsSeeder        # stock con lotes y vencimientos
```

El recálculo ABC queda además programado los domingos a las 03:30 (`app/Console/Kernel.php`).

---

## 1. Datos físicos

`inventarios` no tenía peso ni dimensiones, y sin eso no hay slotting, ni control
real de capacidad, ni planificación de carga.

Campos nuevos: `peso_kg`, `largo_cm`, `ancho_cm`, `alto_cm`, `volumen_m3`,
`unidades_por_bulto`, `peso_bulto_kg`, `volumen_bulto_m3`, `bultos_por_capa`,
`capas_por_paleta`, `apilable`, `max_apilamiento`, `fragil`,
`requiere_refrigeracion`, `peligroso`, `datos_fisicos_fuente`,
`datos_fisicos_medido_en`.

`volumen_m3` se recalcula solo al guardar el producto (hook en el modelo).

### Sobre los datos estimados

El seeder **no genera números aleatorios**. Deriva el tamaño del perfil de la
categoría (una nevera pesa como una nevera, un rollo de alambre es denso) y lo
escala por el precio del producto respecto a la mediana de su categoría. La
aleatoriedad es determinista: la semilla es el id del producto, así que dos
ejecuciones dan el mismo resultado.

Todo queda marcado con `datos_fisicos_fuente = 'estimado'`, y **cualquier
sugerencia que dependa del cubicaje lo advierte en la respuesta**. Para cargar
medidas reales:

```
GET  /wms/medidas/pendientes    → lista ordenada por rotación (medir primero las A rinde más)
POST /wms/medidas               → guarda las medidas y marca fuente = 'medido'
```

> **Limitación conocida.** El estimador es tan bueno como la categoría del
> producto. Durante las pruebas apareció una "CADENA DE MOTOSIERRA" catalogada
> como `id_categoria = 34` (LÍNEA BLANCA), a la que el seeder asignó 45 kg y
> 0,41 m³. El estimador hizo lo correcto; el dato maestro está mal. Conviene
> revisar las categorías antes de confiar en las estimaciones.

---

## 2. Picking FEFO

Antes, la ubicación de recolección se elegía con `orderBy('cantidad','desc')`: se
despachaba del montón más grande. Eso deja envejecer los lotes próximos a vencer
hasta que se pierden.

`PickingStrategyService` ordena por:

1. **fecha de vencimiento** ascendente (los lotes sin fecha van al final: no se
   puede afirmar que sean más viejos que uno con fecha próxima);
2. **fecha de entrada** ascendente (desempate FIFO);
3. **prioridad de picking** de la ubicación (recorrido más corto).

Los lotes **vencidos nunca se ofrecen** y se reportan aparte como alerta.

Puntos ya conectados: los tres sitios de `TCDController` que elegían ubicación.

```php
$svc = new PickingStrategyService();
$svc->codigoUbicacionSugerida($productoId);          // una ubicación
$svc->planPicking($productoId, 25);                  // reparto entre ubicaciones + alertas
$svc->mapaUbicacionesSugeridas([$id1, $id2, ...]);   // por lotes, evita N+1
```

Para cambiar a FIFO: `config/wms.php` → `picking.estrategia = 'fifo'`.

---

## 3. Clasificación ABC

Análisis de Pareto sobre la demanda real (`items_pedidos` + `pedidos`, excluyendo
anulados y devoluciones).

Se calculan **cuatro criterios**, porque la métrica cambia el resultado:

| Criterio | Métrica | Para qué sirve |
|----------|---------|----------------|
| `valor` | unidades × costo | dónde está inmovilizado el dinero → política de stock |
| `unidades` | volumen de salida | qué mueve masa por el almacén |
| `popularidad` | nº de líneas de pedido | **cuántas veces hay que ir a buscarlo** |
| `combinado` | mezcla ponderada | el que usa el slotting |

Para **ubicar** mercancía manda la popularidad, no el valor: un artículo barato
que se pide 40 veces al día cuesta más recorridos que uno caro que se pide una vez
al mes. Por eso `pesos_combinado` da 0,50 a popularidad, 0,30 a valor y 0,20 a
unidades.

Corte por porcentaje acumulado: A hasta 80%, B hasta 95%, C el resto (configurable).

Resultado sobre la copia de producción (365 días, 4.082 productos con demanda de
un catálogo de 31.852 activos):

```
Clase A     542 productos   13,3% del catálogo con demanda   80,0% de la actividad
Clase B     764 productos   18,7%                            15,0%
Clase C   2.776 productos   68,0%                             5,0%
```

Los 27.770 productos activos sin ninguna venta en 12 meses quedan fuera del Pareto:
no son "clase C", son catálogo inmóvil, y mezclarlos distorsionaría los cortes.

`producto_abc_historial` registra sólo los **cambios de clase**. Un producto que
pasa de C a A es una señal accionable: hay que acercarlo al muelle. El panel los
lista como "candidatos a reubicar".

```bash
php artisan wms:abc-recalcular              # periodo por defecto (180 días)
php artisan wms:abc-recalcular --dias=365
```

---

## 4. Motor de slotting (la predicción del TCR)

Responde "¿dónde debe ir este producto?" con un ranking **explicado**.

### Cómo decide

**Paso 1 — filtros duros.** Descartan lo imposible, sin puntaje:

- producto refrigerado ↔ ubicación refrigerada (en ambos sentidos: el frío es caro
  y no se gasta en mercancía seca);
- mercancía peligrosa sólo en zona que la admite, y sólo esa mercancía;
- producto no apilable no va a ubicación de altura;
- ubicación bloqueada para putaway;
- capacidad de unidades, peso y volumen;
- altura del producto contra el hueco.

Cada descarte guarda su motivo.

**Paso 2 — score ponderado** (`config/wms.slotting.pesos`):

| Factor | Peso | Qué premia |
|--------|------|-----------|
| `consolidacion` | 30 | ya hay stock del mismo producto ahí; fragmentar multiplica recorridos |
| `afinidad_abc` | 25 | la clase del producto coincide con la de la ubicación |
| `cercania` | 15 | distancia al muelle, **interpretada según rotación** |
| `ajuste_cubicaje` | 12 | el volumen aprovecha bien el hueco, dejando holgura |
| `ergonomia` | 10 | altura del hueco contra frecuencia de picking y peso |
| `afinidad_familia` | 8 | productos de la misma categoría juntos |

El matiz que hace que esto sea slotting de verdad y no "poner todo cerca": para un
producto **C, estar cerca del muelle es malo**. Esa ubicación privilegiada le hace
falta a un producto A. La curva de cercanía se invierte según la clase.

### Uso

```
POST /wms/slotting/sugerir
  { "codigo": "7591234567890", "cantidad": 20, "contexto": "tcr", "top_n": 3 }
  { ..., "distribuir": true }     ← reparte entre varias ubicaciones si no cabe en una

POST /wms/slotting/decision
  { "sugerencia_id": 42, "codigo_ubicacion": "A1-2-3", "motivo": "..." }

GET  /wms/slotting/metricas       ← tasa de aceptación del motor
GET  /wms/slotting/ocupacion      ← ocupación física del almacén
```

Integrado en la pantalla del **pasillero TCR**: al llegar al paso de ubicación
aparece la sugerencia con sus motivos y un botón "Usar". El pasillero puede
ignorarla y escanear otra.

### Por qué no es machine learning (todavía)

Con cero historial, un modelo aprendería ruido. Lo que sí hace el sistema es
registrar en `putaway_sugerencias` **cada sugerencia y cada corrección**: qué
propuso el motor, qué eligió el operario, en qué puesto del ranking quedó y por
qué. Eso es el dataset.

`GET /wms/slotting/metricas` devuelve la tasa de aceptación y el % de casos donde
la elegida estaba en el top 3, agrupando los motivos de rechazo. Cuando haya unos
cientos de decisiones, esos motivos dicen **qué factor está mal calibrado** y los
pesos se ajustan en `config/wms.php` sin tocar código. Ese es el camino a la
fase 2, y es incremental: nunca hay un momento en que el sistema deje de funcionar
mientras se afina.

---

## 5. Conteo cíclico por ubicación

> **No confundir** con el *inventario cíclico* que ya existía contra
> `arabitocentral`: aquél cuenta **productos** contra el stock general de la
> sucursal. Este cuenta **ubicaciones físicas** contra `warehouse_inventory`.
> Son complementarios.

Dos reglas que deciden si el conteo sirve o es teatro:

- **Ciego**: el contador no ve la cantidad de sistema. Si la ve, la confirma en
  lugar de contar. En modo ciego la cantidad **ni siquiera viaja al navegador**.
- **Recuento antes de ajustar**: toda diferencia se cuenta una segunda vez.
  Ajustar al primer conteo convierte un error de conteo en un error de inventario.

La frecuencia sale del ABC — lo que más se toca es lo que más se descuadra:

```
Clase A → cada 30 días
Clase B → cada 90 días
Clase C → cada 180 días
```

Todo ajuste genera un movimiento en `warehouse_movements` con el código del conteo
como referencia: un cuadre sin rastro es indistinguible de un faltante no reportado.

**KPI: exactitud de inventario** = líneas sin diferencia / líneas contadas.
Meta configurable, por defecto 97%.

```
POST /wms/conteos/generar        { "criterio_abc": "A", "limite": 50, "ciego": true }
GET  /wms/conteos/{id}/tareas
POST /wms/conteos/registrar      { "detalle_id": 1, "cantidad": 47 }
POST /wms/conteos/{id}/ajustar
GET  /wms/conteos/{id}/reporte
```

---

## 6. TMS

El WMS termina en el muelle; el TMS empieza ahí. Usa **los mismos datos físicos**
que el slotting — sólo cambia el contenedor: allí el hueco de un rack, aquí la
caja de un camión.

Tablas: `tms_conductores`, `tms_vehiculos`, `tms_rutas`, `tms_paradas`,
`tms_parada_items`.

### Planificación de carga

Bin packing con **dos restricciones simultáneas**, peso y volumen. El límite real
casi nunca es el mismo: la mercancía densa satura el peso mucho antes que el
volumen, y la voluminosa al revés. El campo `limitante` dice cuál se agotó.

Heurística *First Fit Decreasing* (envíos grandes primero, cada uno al primer
vehículo donde quepa), seguida de un paso de **reducción de vehículo**: cada carga
se pasa al vehículo más pequeño que la admita. Sin ese paso el empaquetado manda
500 kg en un camión de 3.500 y bloquea el camión para nada.

### Rutas y entrega

- Reordenamiento por **vecino más cercano** (`POST /tms/rutas/{id}/optimizar`).
  En la prueba con 4 paradas reales recortó 63,55 km a 44,31 km (−30%).
- Estimación de distancia por haversine × 1,35 (factor de sinuosidad; las calles
  no son líneas rectas). **No sustituye a un motor de ruteo real.**
- Prueba de entrega: recibido por, documento, hora, y entrega completa / parcial /
  fallida con motivo. La ruta se cierra sola cuando no quedan paradas pendientes,
  liberando vehículo y conductor.
- Un conductor con **licencia vencida** no puede pasar la ruta a `en_ruta`.
- Manifiesto imprimible en `/tms/rutas/{id}/manifiesto`, que avisa si alguna línea
  no tiene peso ni volumen (los totales irían cortos).

Peso y volumen de cada línea se **congelan** al planificar: si mañana se corrige la
ficha del producto, un manifiesto ya firmado no debe cambiar retroactivamente.

---

## Dónde vive cada cosa, y por qué

Dos preguntas razonables sobre la arquitectura, con respuestas distintas.

### ¿El ABC debería vivir dentro de Inventariar?

Hay que separar dos cosas.

**El motor y el panel: no.** El ABC es una clasificación transversal que se
recalcula desde el historial de ventas por una tarea programada, y que consumen
tres módulos distintos: el slotting (dónde va cada producto), el conteo cíclico
(cada cuánto se recuenta) y la política de stock. Si viviera dentro de
Inventariar, el conteo cíclico dependería de un módulo con el que no tiene
relación. Es dato maestro, como la categoría de un producto.

**Su aplicación dentro de Inventariar: sí, y faltaba.** Inventariar es un flujo
producto → ubicación → cantidad, estructuralmente idéntico al TCR pasillero.
La sugerencia de ubicación estaba integrada en el TCR y no en Inventariar: una
inconsistencia. Ya está corregido — al escanear el producto aparece su clase ABC
y las ubicaciones sugeridas con botón "Usar", y la decisión real se registra
igual que en el TCR.

### ¿El TMS debería vivir dentro del TCD?

**No, pero tenían que estar conectados — y no lo estaban.**

El TMS no es parte del TCD por tres razones concretas:

1. El TCD es *una* fuente de envíos. El TMS también tiene que mover pedidos a
   clientes, transferencias entre sucursales y recogidas. Una entrega que no nace
   de una orden TCD no tendría dónde vivir.
2. Una ruta lleva paradas de *varias* órdenes TCD. La ruta no es una propiedad de
   la orden; la orden es carga sobre la ruta. Meter el TMS dentro del TCD invierte
   esa relación.
3. La flota (vehículos, conductores, licencias) sobrevive a cualquier orden.

Lo que sí era un hueco real: el TCD terminaba en `despachada` y no había forma de
mandar esa orden a una ruta sin volver a teclear los items. Ya está cerrado:

```
GET  /tms/tcd-pendientes      → órdenes despachadas y aún sin ruta, ya cubicadas
POST /tms/rutas/desde-tcd     → { "orden_ids": [12, 13], "ruta_id": 5 }
```

Toma la cantidad **realmente recolectada** (`cantidad_descontada`), no la pedida,
y bloquea que una orden se monte en dos rutas. Con `ruta_id` se anexa a una ruta
existente verificando que la carga adicional quepa.

---

## Paneles

| Ruta | Contenido |
|------|-----------|
| `/wms/abc/panel` | Distribución ABC, top 50, candidatos a reubicar, salud del dato físico |
| `/wms/conteo` | Exactitud, ubicaciones con recuento vencido, historial de conteos |
| `/tms/panel` | Flota, rutas, efectividad de entrega, utilización de vehículos |

Accesibles desde el menú de warehouse para DICI (tipo 7) y administradores (1, 6).

---

## Pruebas

```bash
php artisan test --filter=Wms
```

40 pruebas en total, 34 de este módulo. Cubren: filtros duros del slotting, dirección del score por clase ABC,
consolidación, reparto de cantidades, corte del Pareto, ponderación del criterio
combinado, orden FEFO, exclusión de vencidos, stock bloqueado, recuento
obligatorio, rastro en kardex, rechazo de conteos negativos, cubicaje y reparto de
carga, y la costura TCD → TMS (cantidad recolectada, estado exigido, doble montaje,
bandeja de planificación).

---

## Estado y límites conocidos

**Listo y verificado con datos reales o de prueba:** ABC sobre 10 meses de ventas
reales, FEFO, slotting con 580 ubicaciones y 398 productos colocados, conteo
cíclico de punta a punta, TMS de punta a punta.

### Validado contra una copia real de producción

El módulo se probó sobre una copia de producción: 31.852 productos activos,
**73.073 ubicaciones**, 14.488 líneas de stock y 87.845 líneas de venta en 2 años.

Resultados y hallazgos:

- **ABC:** 1,7 s sobre 2 años de ventas. Pareto aún más marcado que en desarrollo:
  **13,3% de los productos concentran el 80% de la actividad**.
- **Rendimiento del slotting:** la primera versión **agotaba los 512 MB** de PHP
  porque cargaba las 73.073 ubicaciones como modelos para puntuarlas. Corregido con
  preselección en SQL (`limite_candidatas`, 400 por defecto) más inclusión garantizada
  de las ubicaciones que ya contienen el producto: **300 ms y 42 MB**.
- **El almacén real no tiene layout cargado:** 0 ubicaciones con capacidad de peso,
  volumen o unidades; 1 con zona; ninguna con distancia al muelle ni clase ABC. Hoy
  el motor decide sobre todo por consolidación y familia. Cargar esos datos es lo que
  más va a mejorar la calidad de las sugerencias.
- **27.770 productos activos (87% del catálogo) no tuvieron ninguna venta en 12
  meses**, y 1.809 de ellos ocupan ubicación física. Es espacio de almacén inmovilizado
  y vale la pena revisarlo aparte del WMS.

**Pendiente antes de producción:**

1. **Medir los productos de verdad.** Todo el catálogo está en `estimado`. Empezar
   por las clases A (`GET /wms/medidas/pendientes` ya los ordena por rotación).
2. **Cargar el layout real** — coordenadas, distancia al muelle, capacidades y
   clase ABC de cada ubicación. El `LayoutAlmacenDemoSeeder` es sólo un ejemplo.
3. **Revisar las categorías del catálogo**: hay productos mal categorizados que
   envenenan las estimaciones físicas.
4. **Poner el botón de "enviar a ruta" en la pantalla del TCD.** El endpoint y la
   bandeja de planificación ya existen (`/tms/tcd-pendientes`,
   `/tms/rutas/desde-tcd`) y están probados; falta el disparador en la interfaz del
   chequeador.

**Limitaciones de diseño, asumidas a propósito:**

- El ruteo es una heurística de vecino más cercano con distancia en línea recta
  corregida. Para ventanas horarias duras o tráfico real hace falta un motor de
  ruteo.
- El slotting es un motor de reglas, no un modelo aprendido. Es deliberado: ver
  la sección 4.
- No hay olas de picking (agrupar varios pedidos en un recorrido) ni reposición
  automática de ubicaciones de picking desde las de almacenamiento. Ambas se
  apoyarían sobre lo que ya está construido.

---

## Notas del entorno local

Al montar esto se encontraron dos cosas ajenas al módulo, que siguen presentes:

1. **`routes/web.php` referencia `App\Http\Controllers\CierreV2Controller`, que no
   existe.** Rompe `php artisan route:list` por completo (no sólo esa ruta). Viene
   de antes de este trabajo.
2. **La BD local `sinapsis` está desalineada**: tenía 25 de 91 migraciones
   registradas y `inventarios.id` es `bigint signed` mientras las migraciones del
   módulo declaran `unsignedInteger` para las claves foráneas. Por eso las tablas
   `warehouse_inventory` y `warehouse_movements` existen **sin sus FK** hacia
   `inventarios` y `usuarios`. Eloquent funciona igual, pero conviene alinearlo
   antes de confiar en la integridad referencial.
