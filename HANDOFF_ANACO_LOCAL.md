# Handoff: continuar el cuadre de Anaco desde una sesión LOCAL de Claude Code

Este archivo resume el estado para que una sesión de Claude Code corriendo en la PC del usuario (con acceso a
sus archivos y a SSH/SFTP) continúe el trabajo sin repetir lo hecho. Contexto completo: `RUNBOOK_CUADRE_ANACO.md`,
`ANALISIS_CUADRE_ANACO.md`, `database/data/FORMATO_CUADRE_DIARIO.md`, `database/data/ALGORITMO_CUADRE_DIARIO.md`.

## Estado al 2026-09-27

- Código listo y en `master`: `cuadre:completo` (orquestador reanudable), `cuadre:pedidos-diario` (auditoría,
  simulación, tolerancia), `titanio:importar` (rango, store-id), scripts en `scripts/cuadre-servidor/`.
- Servidor Cloudways desplegado: repo clonado en `/home/1009655.cloudwaysapps.com/cpqterxwes/private_html/cuadre`,
  `composer install` hecho, `.env` creado (BD `cpqterxwes`, storeId Titanio 21, sucursal `anaco`), ZIP subidos a
  `datos/`. Usuario SSH/SFTP: `jose2712` en `157.230.213.208` (la contraseña la tiene el usuario; NO la escribas en
  archivos ni la pegues en el chat).
- Comandos en el servidor: `cd <ruta anterior> && bash scripts/cuadre-servidor/cloudways.sh estado|log|correr`.
- La corrida FALLÓ en el paso `preparar`: los 22 archivos de objetivos son "LIBRO DE VENTAS MES DE ... .xlsx"
  (libros de ventas mensuales, dic 2024 → ago 2026) y el lector `App\Services\Cuadre\CuadreCsvReader` no reconoce su
  formato (no encontró columnas de fecha ni de venta: la cabecera no está en la fila 1 y los nombres difieren).
  El respaldo `anacobackup.sql` (281 MB, 43 tablas) sí se reconoció. La BD del servidor sigue vacía.

## Qué hacer (en orden)

1. Descomprimir en la PC un ZIP de `C:\Users\alvar\Downloads\ANACO FACTURAS` y abrir 2–3 de los XLSX (p. ej. abril 2026 y
   diciembre 2024) con PhpSpreadsheet/openpyxl para ver su estructura: filas de título, fila de cabecera real, columnas
   (fecha, número de factura o rango, serial/número de máquina fiscal, número Z, monto, notas de crédito, tipo).
2. Adaptar `CuadreCsvReader::leerHojaCalculo` (o añadir un lector "libro de ventas") para que produzca filas normalizadas
   con el mismo contrato: fecha, maquina_fiscal (CONCEPTO), factura_inicio/fin, cantidad, total_venta (Bs), tipo
   (FISCAL RANGO / FISCAL UNITARIA / REDUCE EL TOTAL DE ESE DIA). Si el libro lista factura por factura, agrupar por
   día + máquina (rango = min–max, cantidad = n, venta = suma; notas de crédito → REDUCE). Detectar la cabecera
   buscando la fila que contenga las palabras clave, no la fila 1.
3. Probar con los archivos reales: `php artisan cuadre:pedidos-diario "<xlsx>" --dry-run` y las pruebas
   `vendor/bin/phpunit tests/Unit/CuadreCsvReaderTest.php` (añadir un caso con un XLSX de muestra reducido).
4. Commit + push a `master`.
5. En el servidor: `git pull` y `bash scripts/cuadre-servidor/cloudways.sh correr` (reanuda desde `preparar`).
   Revisar en el log la tabla por mes de `preparar` (días, facturas, venta objetivo) antes de que empiece `restaurar`.
6. Vigilar con `cloudways.sh estado` cada ~10 min: `restaurar` (281 MB → minutos), `migrar`, `titanio` (30/08 → hoy,
   storeId 21; revisar cajas/productos/tipos de pago no reconocidos), `respaldo`, `cuadre`, `medir`.
7. Entregar al usuario la tabla por mes de `medir` y `storage/app/cuadre-completo/anaco/resultado_anaco.csv`.

## Notas

- Nunca correr `cuadre:completo` contra una BD de producción: la BD del `.env` se borra y se restaura.
- Si un paso falla, corregir y repetir `correr`; el estado en `storage/app/cuadre-completo/anaco/estado.json` reanuda.
- Al terminar, recomendar al usuario cambiar la contraseña SFTP (quedó expuesta en una captura).
