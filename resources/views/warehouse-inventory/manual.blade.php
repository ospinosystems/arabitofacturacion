<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 22mm 16mm 20mm 16mm; }
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10.5px; color: #1f2937; line-height: 1.5; }
    h1 { font-size: 20px; color: #1e3a8a; margin: 0 0 4px; }
    h2 { font-size: 15px; color: #1e40af; border-bottom: 2px solid #bfdbfe; padding-bottom: 3px; margin: 18px 0 8px; page-break-after: avoid; }
    h3 { font-size: 12.5px; color: #1d4ed8; margin: 12px 0 4px; page-break-after: avoid; }
    p { margin: 0 0 6px; }
    ul, ol { margin: 0 0 8px 0; padding-left: 18px; }
    li { margin: 0 0 3px; }
    .muted { color: #6b7280; }
    .tag { display: inline-block; background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; border-radius: 4px; padding: 1px 6px; font-size: 9px; font-weight: bold; }
    .role { display: inline-block; background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; border-radius: 4px; padding: 1px 6px; font-size: 9px; font-weight: bold; }
    table { width: 100%; border-collapse: collapse; margin: 6px 0 10px; }
    th, td { border: 1px solid #d1d5db; padding: 4px 7px; text-align: left; vertical-align: top; }
    th { background: #f3f4f6; font-size: 9.5px; text-transform: uppercase; color: #374151; }
    .note { background: #fffbeb; border-left: 3px solid #f59e0b; padding: 6px 10px; margin: 8px 0; }
    .tip { background: #eff6ff; border-left: 3px solid #3b82f6; padding: 6px 10px; margin: 8px 0; }
    .step { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 5px; padding: 7px 10px; margin: 6px 0; }
    .step b { color: #1e40af; }
    .cover { text-align: center; padding-top: 120px; }
    .cover h1 { font-size: 30px; }
    .cover .sub { font-size: 15px; color: #374151; margin-top: 8px; }
    .cover .meta { margin-top: 40px; color: #6b7280; font-size: 11px; }
    .pagebreak { page-break-before: always; }
    .toc td { border: none; padding: 2px 0; }
    .toc .n { color: #1e40af; font-weight: bold; width: 22px; }
    code { background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 3px; padding: 0 3px; font-family: DejaVu Sans Mono, monospace; font-size: 9.5px; }
</style>
</head>
<body>

{{-- ══════════════ PORTADA ══════════════ --}}
<div class="cover">
    <div style="font-size:44px;">🏬</div>
    <h1>Manual de Usuario</h1>
    <div class="sub"><b>Módulo Warehouse Inventory (WMS)</b><br>Gestión de almacén, despacho (TCD) y recepción (TCR)</div>
    <div class="meta">
        Sistema Arábito · Sucursal / Galpón<br>
        Versión del manual: {{ date('d/m/Y') }}
    </div>
</div>

{{-- ══════════════ ÍNDICE ══════════════ --}}
<div class="pagebreak"></div>
<h2>Índice</h2>
<table class="toc">
    <tr><td class="n">1.</td><td>Introducción y conceptos clave</td></tr>
    <tr><td class="n">2.</td><td>Acceso y roles de usuario</td></tr>
    <tr><td class="n">3.</td><td>Consulta de inventario</td></tr>
    <tr><td class="n">4.</td><td>Ubicaciones de almacén (slotting)</td></tr>
    <tr><td class="n">5.</td><td>TCD — Despacho de mercancía</td></tr>
    <tr><td class="n">6.</td><td>TCR — Recepción de mercancía</td></tr>
    <tr><td class="n">7.</td><td>Impresión (etiquetas, tickets, guías, bultos)</td></tr>
    <tr><td class="n">8.</td><td>Buenas prácticas y preguntas frecuentes</td></tr>
</table>

{{-- ══════════════ 1. INTRODUCCIÓN ══════════════ --}}
<h2>1. Introducción y conceptos clave</h2>
<p>El módulo <b>Warehouse Inventory</b> es el sistema de gestión de almacén (WMS) del galpón. Permite <b>consultar el inventario</b>, saber <b>en qué ubicación física</b> está cada producto, y gestionar los dos flujos de movimiento de mercancía entre sucursales:</p>
<ul>
    <li><span class="tag">TCD</span> <b>Transferencia / Despacho</b>: preparar y <b>sacar</b> mercancía del galpón hacia otra sucursal.</li>
    <li><span class="tag">TCR</span> <b>Transferencia / Recepción</b>: <b>recibir</b> mercancía que llega desde central u otra sucursal.</li>
</ul>

<h3>Glosario</h3>
<table>
    <tr><th>Término</th><th>Qué significa</th></tr>
    <tr><td><b>Ubicación</b></td><td>Posición física en el almacén (ej. <code>Q1-05-14</code>: pasillo–estante–nivel). Un producto puede estar repartido en varias.</td></tr>
    <tr><td><b>Redistribución (OD)</b></td><td>Orden de distribución creada por los analistas de compras en central, que indica qué productos enviar a qué sucursal.</td></tr>
    <tr><td><b>Premonta</b></td><td>Una redistribución "aprobada" que aparece en el galpón lista para despachar.</td></tr>
    <tr><td><b>Espejo / Pedido central</b></td><td>Registro que se crea en central cuando se despacha una transferencia; es el que le da el N.º de Guía.</td></tr>
    <tr><td><b>Bulto</b></td><td>Caja/paquete físico en el que se agrupa la mercancía despachada, con su etiqueta.</td></tr>
    <tr><td><b>Pistolear / Escanear</b></td><td>Leer el código de barras con la pistola lectora para confirmar el producto.</td></tr>
</table>

{{-- ══════════════ 2. ACCESO Y ROLES ══════════════ --}}
<h2>2. Acceso y roles de usuario</h2>
<p>Cada usuario ve solo lo que su rol le permite. Los roles que intervienen en el almacén son:</p>
<table>
    <tr><th>Rol</th><th>Qué puede hacer</th></tr>
    <tr><td><span class="role">Chequeador (DICI)</span></td><td>Es el responsable del almacén. Crea y da salida a los despachos, recibe la mercancía, asigna trabajo a los pasilleros, aprueba productos escaneándolos, imprime guías y etiquetas.</td></tr>
    <tr><td><span class="role">Pasillero</span></td><td>Recolecta físicamente los productos: recorre el almacén escaneando ubicación y producto, e informa las cantidades que consigue.</td></tr>
    <tr><td><span class="role">Gerente</span></td><td>Acceso de <b>solo lectura</b>: puede ver el inventario, los reportes, el historial de movimientos de un producto e imprimir precios, pero <b>no</b> edita ni despacha.</td></tr>
</table>
<div class="tip"><b>Cómo entrar:</b> desde el navegador de opciones (menú lateral) elegí <b>Torre de transferencias (TCD, TCR)</b> para el flujo de despacho/recepción, o <b>Inventario</b> para consultar. El pasillero entra por <b>TCD Pasillero</b> / <b>TCR Pasillero</b>.</div>

{{-- ══════════════ 3. CONSULTA DE INVENTARIO ══════════════ --}}
<h2>3. Consulta de inventario</h2>
<p>El módulo ofrece varias formas de consultar qué hay y dónde está:</p>
<table>
    <tr><th>Pantalla</th><th>Para qué sirve</th></tr>
    <tr><td><b>Inventario</b></td><td>Listado general de productos con su stock y ubicaciones asignadas.</td></tr>
    <tr><td><b>Buscar por código</b></td><td>Escaneá o escribí un código de barras / código de proveedor para encontrar un producto y ver dónde está ubicado.</td></tr>
    <tr><td><b>Por ubicación</b></td><td>Elegí una ubicación física y ve qué productos y cantidades hay en ella.</td></tr>
    <tr><td><b>Historial</b></td><td>Movimientos de entrada/salida del almacén (auditoría de qué se movió, cuándo y por quién).</td></tr>
    <tr><td><b>Próximos a vencer</b></td><td>Productos cuyo lote está cerca de la fecha de vencimiento, para priorizar su salida.</td></tr>
</table>
<div class="tip"><b>El "ojito" de movimientos:</b> en el listado de inventario, el ícono de historial junto a un producto abre su <b>historial de movimientos unitario</b> (cada entrada y salida del producto). Útil para auditar diferencias.</div>

{{-- ══════════════ 4. UBICACIONES ══════════════ --}}
<h2>4. Ubicaciones de almacén (slotting)</h2>
<p>Para que el sistema sepa dónde buscar cada producto al despachar, los productos deben tener una o varias <b>ubicaciones</b> asignadas.</p>
<ul>
    <li><b>Asignar ubicación:</b> se escanea el producto y luego la ubicación destino; el sistema guarda que ese producto vive ahí.</li>
    <li><b>Productos sin ubicación:</b> pantalla que lista lo que aún no tiene lugar asignado, para completarlo.</li>
    <li><b>Un producto en varias ubicaciones:</b> es normal. Al despachar, el sistema muestra todas las ubicaciones y sus cantidades para que el pasillero sepa de dónde tomar.</li>
</ul>
<div class="note"><b>Importante:</b> mantener las ubicaciones al día acelera muchísimo el despacho, porque la lista de picking indica exactamente dónde ir a buscar cada producto.</div>

{{-- ══════════════ 5. TCD DESPACHO ══════════════ --}}
<div class="pagebreak"></div>
<h2>5. TCD — Despacho de mercancía</h2>
<p>El despacho vive en <b>Torre de transferencias → pestaña Redistribuciones / En preparación / Despachadas</b>. Hay dos modos:</p>
<ul>
    <li><b>Simple:</b> preparás la orden y le das salida de una vez. Es el flujo más usado.</li>
    <li><b>Avanzado (pasilleros + bultos):</b> repartís el trabajo entre pasilleros y armás bultos. Para despachos grandes.</li>
</ul>

<h3>5.1 Recibir una redistribución y crear la orden</h3>
<div class="step"><b>Paso 1 — Redistribuciones.</b> En la pestaña <b>Redistribuciones</b> aparecen las órdenes que central aprobó para despachar. Podés filtrarlas por destino y fecha.</div>
<div class="step"><b>Paso 2 — Fusionar (opcional).</b> Si varios analistas enviaron varias redistribuciones al <b>mismo destino</b>, marcá sus casillas y tocá <b>"Fusionar seleccionadas"</b>: se combinan en una sola sumando las cantidades de productos repetidos. Queda registro en central.</div>
<div class="step"><b>Paso 3 — Revisar y crear.</b> Tocá <b>"Revisar y crear"</b>. El sistema coteja cada producto contra tu inventario local: muestra el <b>stock disponible</b>, lo <b>solicitado</b> y permite <b>ajustar la cantidad</b> o excluir lo que no exista. Confirmá para crear la <b>orden en preparación</b> (todavía <b>no</b> descuenta inventario).</div>

<h3>5.2 Preparar la orden (picking)</h3>
<div class="step"><b>Editar el borrador.</b> En <b>En preparación</b>, tocá <b>Editar</b>. Ahí podés:
    <ul>
        <li>Ver por producto el <b>stock disponible</b> (ya descontando lo comprometido en otras órdenes en preparación) y la cantidad original.</li>
        <li>Ajustar cantidades a lo que realmente conseguís. <b>No</b> se puede pedir más de lo disponible.</li>
        <li>Marcar cada producto como <b>Revisado</b> — se marca solo al editar su cantidad, o con el botón de la fila.</li>
        <li>Usar el <b>buscador</b> por código o descripción, navegar entre cantidades con <b>Enter / flechas ↑↓</b>, e imprimir el <b>ticket con código de barras</b> de un producto.</li>
    </ul>
</div>
<div class="note"><b>No se puede dar salida hasta que todos los productos estén revisados.</b> Esto obliga a chequear físicamente cada ítem antes de sacar la mercancía.</div>

<h3>5.3 Dar salida</h3>
<div class="step"><b>Dar salida.</b> Con todo revisado, tocá <b>Dar salida</b>. Recién ahí el sistema <b>descuenta el inventario</b> y crea el <b>pedido espejo en central</b> (que otorga el N.º de Guía). La orden pasa a <b>Despachadas</b>.</div>
<div class="tip">Si por un corte de red el envío a central no se completa, la orden queda en Despachadas con el botón <b>"Enviar a central"</b> para reintentar sin volver a descontar.</div>

<h3>5.4 Órdenes despachadas</h3>
<p>En la pestaña <b>Despachadas</b>, por cada orden podés:</p>
<ul>
    <li><b>Ítems:</b> ver todos los productos de la orden en un listado.</li>
    <li><b>Guía de Despacho:</b> imprimir la guía con los datos del cliente (razón social, RIF, dirección), origen y productos.</li>
    <li><b>Bultos:</b> imprimir las etiquetas de los bultos (indicás cuántos bultos son).</li>
    <li><b>Reversar:</b> si la sucursal destino <b>todavía no la recibió</b>, deshace el despacho: reintegra el inventario, quita el espejo en central y vuelve la orden a "en preparación" para corregir.</li>
</ul>
<div class="note"><b>Filtros y paginación:</b> la lista de despachadas se filtra por sucursal, rango de fecha y texto, y se navega por páginas (elegí cuántos resultados por página).</div>

<h3>5.5 Flujo avanzado (pasilleros + bultos)</h3>
<div class="step"><b>Chequeador:</b> crea la orden y <b>asigna líneas a los pasilleros</b>.</div>
<div class="step"><b>Pasillero (TCD Pasillero):</b> recorre el almacén; por cada producto escanea la <b>ubicación</b> y el <b>producto</b>, e informa la <b>cantidad recolectada</b>.</div>
<div class="step"><b>Chequeador:</b> reconcuenta lo recolectado, arma los <b>bultos</b> (escaneando la mercancía de cada uno) y despacha bulto por bulto. Lo que se mandó a recolectar pero no se empacó queda <b>excluido</b> (no se descuenta).</div>

{{-- ══════════════ 6. TCR RECEPCIÓN ══════════════ --}}
<div class="pagebreak"></div>
<h2>6. TCR — Recepción de mercancía</h2>
<p>La recepción vive en <b>Torre de transferencias → TCR</b>. Sirve para recibir los pedidos que llegan desde central.</p>

<h3>6.1 Recepción por el chequeador</h3>
<div class="step"><b>Paso 1 — Seleccionar el pedido.</b> Elegí de la lista el pedido de central que llegó. Sus productos aparecen en estado <b>PENDIENTE</b>.</div>
<div class="step"><b>Paso 2 — Escanear para aprobar.</b> En el buscador, <b>escaneá el código de barras</b> de cada producto. La lista se <b>filtra y muestra solo el producto que coincide</b> — no hace falta buscarlo a mano. Al dar <b>Enter</b> (o el botón <b>Chequear</b>), el producto queda <b>APROBADO</b>.</div>
<div class="note"><b>La aprobación es solo por escaneo, producto por producto.</b> No existe una casilla para marcar a mano ni un "aprobar todos": esto obliga a verificar físicamente cada producto que llegó.</div>
<div class="step"><b>Paso 3 — Ubicar (opcional).</b> Si está activada la <b>asignación de ubicaciones</b>, después de escanear el producto se escanea la <b>ubicación</b> donde se guarda. Si no, se recibe directo sin pistolear ubicación.</div>
<div class="step"><b>Paso 4 — Guardar.</b> Con todos los productos aprobados, tocá <b>Guardar Pedido Revisado</b> para cerrar la recepción.</div>

<h3>6.2 Novedades</h3>
<p>Si llega algo distinto a lo esperado (cantidad diferente, producto no listado), se registra como <b>novedad</b>: se escanea el producto, se informa la cantidad real que llegó y queda documentado para su revisión.</p>

<h3>6.3 Recepción con pasilleros (TCR Pasillero)</h3>
<p>Igual que en el despacho, el chequeador puede repartir la recepción entre pasilleros, que van ubicando físicamente la mercancía escaneando producto y ubicación.</p>

{{-- ══════════════ 7. IMPRESIÓN ══════════════ --}}
<h2>7. Impresión</h2>
<table>
    <tr><th>Documento</th><th>Cuándo / para qué</th></tr>
    <tr><td><b>Etiqueta de precio (57×44 mm)</b></td><td>Etiqueta con código de barras, descripción y precio del producto, para el estante.</td></tr>
    <tr><td><b>Ticket de producto</b></td><td>Etiqueta con el código de barras del producto (desde la fila del producto).</td></tr>
    <tr><td><b>Ticket de ubicación</b></td><td>Etiqueta para identificar una posición física del almacén.</td></tr>
    <tr><td><b>Lista de picking (hoja carta)</b></td><td>Listado con producto, código, ubicación y cantidad para recorrer el almacén. Se puede ordenar y dividir en varias hojas.</td></tr>
    <tr><td><b>Guía de Despacho</b></td><td>Documento fiscal/legal de la transferencia despachada, con cliente, origen y productos.</td></tr>
    <tr><td><b>Etiquetas de bultos</b></td><td>Una etiqueta por bulto (sucursal, número de bulto, origen y fecha).</td></tr>
    <tr><td><b>Nota de entrega</b></td><td>Comprobante de la entrega de una orden TCD.</td></tr>
</table>
<div class="tip">Las etiquetas de 57×44 mm ya traen los <b>márgenes fijos</b> en la hoja: no hace falta configurar la impresora, salen a la medida.</div>

{{-- ══════════════ 8. BUENAS PRÁCTICAS ══════════════ --}}
<h2>8. Buenas prácticas y preguntas frecuentes</h2>
<h3>Buenas prácticas</h3>
<ul>
    <li>Escaneá siempre; no confíes en marcar a mano. El sistema está diseñado para obligar el pistoleo.</li>
    <li>Mantené las ubicaciones actualizadas: es lo que hace rápido el picking.</li>
    <li>Revisá el <b>stock disponible</b> antes de comprometer cantidades: no se puede sacar más de lo que hay, ni entre varias órdenes en preparación.</li>
    <li>Fusioná las redistribuciones repetidas del mismo destino antes de despachar, para no hacer doble trabajo.</li>
</ul>
<h3>Preguntas frecuentes</h3>
<table>
    <tr><th>Situación</th><th>Qué hacer</th></tr>
    <tr><td>"Dar salida" está deshabilitado.</td><td>Faltan productos por revisar. Marcá todos como Revisado (se marcan al editar la cantidad).</td></tr>
    <tr><td>La orden aparece "sin N.º de guía".</td><td>El envío a central no se completó. Tocá <b>Enviar a central</b> para reintentar.</td></tr>
    <tr><td>Necesito corregir una orden ya despachada.</td><td>Usá <b>Reversar</b> (solo si la sucursal destino aún no la recibió). Reintegra el inventario y la vuelve a preparación.</td></tr>
    <tr><td>Al recibir, no encuentro el producto en la lista.</td><td>Escaneá su código: la lista se filtra sola. Si no coincide con ninguno del pedido, avisa "sin coincidencia" — registralo como novedad.</td></tr>
    <tr><td>Un producto está en varias ubicaciones.</td><td>Es normal. La lista de picking muestra todas; el pasillero elige de dónde tomar.</td></tr>
    <tr><td>Soy gerente y no veo botones de editar.</td><td>Correcto: el gerente tiene acceso de solo lectura (ver, reportes, historial, imprimir precio).</td></tr>
</table>

<p class="muted" style="margin-top:24px; text-align:center; border-top:1px solid #e5e7eb; padding-top:8px;">
    Manual de usuario — Módulo Warehouse Inventory · Sistema Arábito · Generado el {{ date('d/m/Y') }}
</p>

</body>
</html>
