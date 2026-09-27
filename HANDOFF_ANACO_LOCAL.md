# Handoff: cuadre de Anaco (estado al 2026-09-27, 20:45)

Contexto completo: `RUNBOOK_CUADRE_ANACO.md`, `ANALISIS_CUADRE_ANACO.md`, `database/data/FORMATO_CUADRE_DIARIO.md`,
`database/data/ALGORITMO_CUADRE_DIARIO.md`.

## Resultado

El cuadre completo de Anaco (dic 2024 → ago 2026) **terminó** en el servidor Cloudways: 1.175 grupos día+máquina,
201.914 pedidos con número de factura, sin números repetidos por máquina, 18 de 21 meses con diferencia ≤ 0,12 % frente al
libro de ventas. Tabla por mes en `storage/app/cuadre-completo/anaco/resultado_anaco.csv` (copia en
`C:\Users\alvar\Downloads\ANACO FACTURAS\resultado_cuadre\`, junto con el objetivo fusionado, el detalle por día+máquina y el log).

Meses con diferencia > 0,2 % (son limitaciones de los datos, no del proceso):

| Mes | Diferencia | Causa |
|---|---|---|
| 2025-01 | +1,41 % | Días 30/01 y otros: la BD tiene menos pedidos que facturas en el libro y por más monto; el ajuste no alcanza |
| 2025-02 | +1,55 % | Ídem (02/02, 16/02) |
| 2025-05 | -2,50 % | 04/05 factura manual SERIE R de 529 k Bs sin ningún pedido ese día; 12/05 el libro trae 5 facturas por 287 k Bs |

Grupos con ajuste > 5 % (19): en su mayoría facturas manuales "SERIE R" grandes sin un pedido equivalente en la BD.
Lista completa en el log y en `cuadre_detalle_por_dia_maquina.csv` (columna `pct_ajuste`).

## Lo que se corrigió en esta sesión (todo en `master`)

1. **Lector de libros de ventas** (`CuadreCsvReader::leerLibroVentas`): reconoce el XLSX de la contadora (cabecera de dos
   filas, fórmulas, VAN/VIENEN), notas de crédito, facturas manuales SERIE R, secciones de otra sucursal; corrige con aviso
   años mal digitados y rangos mal escritos por continuidad de numeración. Pruebas en `tests/Unit/CuadreCsvReaderTest.php`.
2. **Tasa BCV real por día** (paso `tasas` de `cuadre:completo`): el respaldo traía `tasa = 267,7499` fija en todo
   dic 2024 → dic 2025. Fuente: `database/data/tasas_bcv_diarias.csv` (Wikipedia, 2023 → 22/09/2026, generado con
   `php scripts/extraer_tasas_bcv_wikipedia.php`). Solo se tocan los meses con tasa fija.
3. **Titanio**: la API pasó a `data.orders` y a pagos con `name`; se conserva el pedido aunque falte stock; tolerancia de
   errores (25 %). Importado 2026-08-25 → 2026-09-27 (el respaldo termina el 24/08: usar `--titanio-desde=2026-08-25`).
4. **Cuadre**: los días que el libro salta (el Z se cerró al día siguiente) se absorben en el grupo siguiente de la misma
   máquina (`--max-dias-absorber=3`).
5. **Orquestador**: al fallar o repetir un paso se invalidan sus marcas y las de los pasos posteriores.

## Servidor

- Cloudways `157.230.213.208`, carpeta `/home/1009655.cloudwaysapps.com/cpqterxwes/private_html/cuadre`, BD `cpqterxwes`.
- Desde esta PC: `ssh central` (usuario master, llave ya instalada) puede hacer `git pull` y `bash scripts/cuadre-servidor/cloudways.sh estado|log|correr|detener`
  porque la carpeta tiene permisos de grupo. El usuario `jose2712` solo acepta contraseña; para usarlo con llave hay que
  pegar `~/.ssh/claude_anaco_jose2712.pub` en el panel de Cloudways (Application → Access Details → SSH keys).
- Respaldos en la carpeta de trabajo: `respaldo_pre_cuadre_anaco_20260927_161330.sql` (antes del cuadre final, con tasas
  corregidas). Reverso total: `php artisan cuadre:pedidos-reset --si` (usa `cuadre_ajustes`).
- Pendiente recomendado: cambiar la contraseña SFTP de `jose2712` (quedó expuesta en una captura anterior).

## Si hay que repetir

```bash
ssh central
cd /home/1009655.cloudwaysapps.com/cpqterxwes/private_html/cuadre && git pull
CUADRE_OPCIONES="--desde-paso=cuadre" bash scripts/cuadre-servidor/cloudways.sh correr   # o --reiniciar para todo
bash scripts/cuadre-servidor/cloudways.sh estado
```
