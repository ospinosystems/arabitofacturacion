# Cómo funciona el algoritmo de cuadre diario (explicación)

El objetivo es que **día a día** quede un resultado **lo más real posible**: N pedidos con números de factura consecutivos y un total en Bs muy cercano al objetivo, con un **único ajuste pequeño** cuando haga falta (no un “último pedido” con monto irreal).

---

## 1. Qué entra por día

Para cada **fecha** del CSV:

- Se buscan todos los pedidos **de ese día** usando la fecha/hora de factura (o de creación si no hay fecha factura):  
  `DATE(COALESCE(fecha_factura, created_at)) = fecha`.
- Esos pedidos se ordenan en **orden natural**: primero por `fecha_factura` (o `created_at`), luego por `id`.  
  Es decir, en el orden en que “pasaron” en el tiempo ese día.

Así trabajamos siempre con el **flujo real** del día, en orden cronológico.

---

## 2. Cuánto “pesa” cada pedido (monto Bs)

Para cada pedido del día se calcula su **monto en Bs**: suma de `monto_bs` de todos sus ítems en `items_pedidos`.

Eso da una lista:  
pedido 1 → X₁ Bs, pedido 2 → X₂ Bs, … (en el mismo orden natural de antes).

---

## 3. Elegir los N pedidos que más se acerquen al objetivo

El CSV dice para ese día:

- **N** = cantidad de pedidos que deben contar (ej. 5).
- **Objetivo** = monto total en Bs que debería sumar ese día.

Si ese día hay **menos o igual** que N pedidos:

- Se usan **todos**. No se inventan pedidos.
- La “suma del grupo” es la suma de todos sus montos.

Si ese día hay **más** de N pedidos:

- Se piensa el día como una **tira de pedidos en orden de tiempo**: 1º, 2º, 3º, …
- Se prueban **todas las ventanas de N pedidos seguidos** en esa tira:
  - Ventana 1: pedidos 1 a N → suma S₁  
  - Ventana 2: pedidos 2 a N+1 → suma S₂  
  - Ventana 3: pedidos 3 a N+2 → suma S₃  
  - … hasta la última ventana posible (por ejemplo, si hay 20 pedidos y N=5, las ventanas son 16).
- Para cada ventana se mira: **¿cuánto se desvía su suma del objetivo?**  
  Diferencia = |objetivo − suma de esa ventana|.
- Se elige la ventana cuya suma **más se acerca al objetivo** (diferencia mínima).

Esa ventana son los **N pedidos “ganadores”** del día: los que, siendo **consecutivos en el tiempo**, dan un total **lo más cercano posible** al objetivo. Con eso el resultado día a día es más real: no se fuerza un monto raro en un pedido cualquiera, sino que se elige el bloque de N que ya casi cuadra.

---

## 4. Asignar números de factura y marcar como válidos

A los N pedidos de la ventana elegida (en su orden natural, es decir, en orden de hora):

- Se les asigna **numero_factura** consecutivo empezando por `rango_factura_inicio` (ej. 1001, 1002, …, 1005).
- Se les pone **valido = true**.

No se borra ni se modifica ningún ítem en este paso; solo se actualizan esos dos campos del pedido.

---

## 5. El ajuste (solo la diferencia mínima)

- Se calcula: **Ajuste = objetivo − suma de los N pedidos elegidos**.
- Esa cantidad es la **única** que “falta” o “sobra” para que el total del día coincida exactamente con el objetivo. Como elegimos la ventana que más se acercaba, esta diferencia es **la mínima posible** con N pedidos consecutivos.
- Si **ajuste = 0**: no se hace nada más.
- Si **ajuste ≠ 0**:
  - Se **añade un solo ítem** al **último** pedido del grupo (el que cierra la ventana en el tiempo) con `monto_bs = ajuste`.
  - Se actualiza el pago de ese pedido para que el total del pedido (ítems + ese ítem) coincida con el nuevo total.

Así, el “último” del grupo no se convierte en un pedido con un monto irreal: solo lleva un **ítem de ajuste** por la diferencia pequeña que faltaba (o sobraba) para cuadrar el día.

---

## 6. Resumen en una frase

Por cada día, el algoritmo **recorre todas las ventanas de N pedidos consecutivos en el tiempo**, elige la cuya suma **más se acerca al objetivo**, les asigna números de factura consecutivos y **solo esa diferencia mínima** la aplica como un único ítem de ajuste en el último pedido del grupo; el resto de pedidos y ítems no se tocan, para que el resultado sea lo más real posible día a día.

---

## Consumo de recursos

- Por día se hace: una consulta de pedidos, una suma de ítems por pedido y un bucle de ventanas (si hay más de N pedidos). No se crean pedidos fantasma ni se borran ítems.
- El coste crece con el número de pedidos del día y con el tamaño de N, pero el diseño prioriza **resultado real** día a día sobre ahorro de recursos.
