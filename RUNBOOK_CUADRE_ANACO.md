# Runbook: cuadre completo de Anaco (restaurar + Titanio + cuadre + medir)

Comando único: `php artisan cuadre:completo <carpeta>`. Hace todo el proceso y es **reanudable**: si la PC
o el servidor se apaga a mitad, se vuelve a ejecutar el mismo comando y continúa donde quedó.

## 1. Qué necesita

| Insumo | Dónde |
|---|---|
| ZIP con el respaldo de la BD de Anaco (mysqldump `.sql`) | En la carpeta de entrada (ej. `C:\Users\alvar\Downloads\ANACO FACTURAS`) |
| ZIP(s) con los montos objetivo, uno por mes, en CSV o XLSX con columnas `FECHA, CONCEPTO, FACTURA, VENTA, TIPO` (ver `database/data/FORMATO_CUADRE_DIARIO.md`) | Misma carpeta |
| `storeId` de Anaco en Titanio POS | `--store-id=NN` o `TITANIO_STORE_ID` en `.env` |
| Una BD MySQL/MariaDB **dedicada** (el proceso la BORRA y restaura) | `.env` → `DB_DATABASE` |
| `mysql` y `mysqldump` (XAMPP: `C:\xampp\mysql\bin`; se detectan solos) | `--mysql-bin=` si están en otra ruta |

El proceso no toca la BD de producción de ninguna sucursal: trabaja sobre la copia restaurada.

## 2. Pasos que ejecuta

| # | Paso | Qué hace | Reanudación |
|---|---|---|---|
| 1 | `preparar` | Descomprime los ZIP, detecta el `.sql` (el que contiene `CREATE TABLE pedidos`) y los CSV/XLSX; fusiona los objetivos en `objetivo_anaco.csv`; muestra totales por mes y **estimación de tiempo** | Se repite solo si se pide |
| 2 | `restaurar` | **Borra todas las tablas** de la BD del `.env` e importa el respaldo. Verifica que `sucursals.codigo` sea `anaco` | Si murió a medias, al repetir vuelve a borrar e importar (idempotente) |
| 3 | `migrar` | `php artisan migrate --force` y asegura columnas del cuadre (`numero_factura`, `maquina_fiscal`, `valido`, `uuid`, `monto_bs`) y la tabla `cuadre_ajustes` | Idempotente |
| 4 | `titanio` | `titanio:importar --desde=2026-08-30 --hasta=hoy --store-id=NN` (día por día; reintentos; sigue si un día falla y lo reporta) | Idempotente por `uuid` |
| 5 | `respaldo` | `mysqldump` de la BD ya completa → `respaldo_pre_cuadre_anaco_<fecha>.sql` en la carpeta de trabajo | — |
| 6 | `simular` | (opcional, `--simular-antes` o `--solo-simular`) selección real sin escribir; reporte por grupo | — |
| 7 | `cuadre` | `cuadre:pedidos-diario objetivo_anaco.csv --si` con reporte CSV por día+máquina | Omite los grupos ya cuadrados |
| 8 | `medir` | Tabla por mes: objetivo vs logrado, facturas, ajustes, duplicados de numeración → `resultado_anaco.csv` | — |

Carpeta de trabajo (por defecto `storage/app/cuadre-completo/anaco/`): `estado.json`, `cuadre_completo.log`,
`objetivo_anaco.csv`, `respaldo_pre_cuadre_*.sql`, `cuadre_<fecha>.csv`, `resultado_anaco.csv`.

## 3. Correrlo en la PC (Windows / XAMPP)

```bat
cd C:\ruta\arabitofacturacion
php artisan cuadre:completo "C:\Users\alvar\Downloads\ANACO FACTURAS" --sucursal=anaco --store-id=NN --si
```
o el atajo `scripts\cuadre-completo-anaco.bat "C:\Users\alvar\Downloads\ANACO FACTURAS" NN` (deja el log en
`storage\logs\cuadre_completo_anaco.log`).

Opciones útiles:

- `--dry-run` : solo prepara los archivos, muestra la estimación y, si la BD ya tiene pedidos, simula el cuadre.
- `--sin-titanio` : no importar desde Titanio (si ya se importó o no hay red).
- `--simular-antes` / `--solo-simular` : ver los ajustes por día antes de escribir.
- `--desde-cero` : si la BD trae un cuadre previo, deshacerlo (restaura ajustes auditados) y rehacer.
- `--paso=medir` / `--desde-paso=cuadre` / `--reiniciar` : control fino de la reanudación.
- `--max-segundos=15 --tolerancia-bs=1` : tiempo máximo por grupo y tolerancia a partir de la cual se deja de buscar.

## 4. Correrlo en un servidor aparte (recomendado)

La PC puede apagarse y la corrida es larga. Con un VPS Linux con Docker (2 vCPU, 4 GB RAM, 40 GB de disco;
Hetzner CX22 ≈ 4 €/mes o por horas, DigitalOcean/Vultr ≈ 12–24 US$/mes; se puede borrar al terminar):

```bash
# en el servidor
apt-get update && apt-get install -y docker.io docker-compose-plugin git
git clone -b claude/anaco-order-transformation-command-6v5vg2 https://github.com/ospinosystems/arabitofacturacion.git
cd arabitofacturacion/scripts/cuadre-servidor
cp .env.servidor.example .env.servidor      # completar TITANIO_STORE_ID (y TITANIO_SECRET si aplica)
mkdir -p datos                              # copiar aquí los ZIP (scp / rclone / WinSCP desde la PC)
bash run.sh                                 # arranca en segundo plano; se puede cerrar la sesión SSH
bash run.sh estado                          # ver estado.json + últimas líneas del log
bash run.sh log                             # seguir el log en vivo
```

Si el servidor se reinicia: `bash run.sh` otra vez. Al terminar, copiar `trabajo/resultado_anaco.csv`,
`trabajo/cuadre_*.csv` y hacer `mysqldump` de la BD final (`bash run.sh shell` → `mysqldump -h db -uroot -p$DB_ROOT_PASSWORD arabito > /trabajo/anaco_final.sql`).

Notas: en Docker la BD está en el host `db`, por eso `run.sh` pasa `--permitir-remoto`. El repo queda montado en
`/app` y `composer install` corre la primera vez.

### 4.b Servidor Cloudways (PHP + MySQL ya instalados, sin Docker)

**Camino corto, desde la PC (Windows 10/11, PowerShell):** un solo comando sube los ZIP por SFTP, clona el
repositorio en el servidor, crea el `.env` y lanza el proceso en segundo plano. Pide la IP, el usuario SFTP, la
carpeta local de los ZIP, los datos MySQL de la app (Access Details → MySQL Access) y el storeId; la contraseña
SFTP la pide el propio ssh/scp tres veces.

```powershell
powershell -ExecutionPolicy Bypass -Command "iwr https://raw.githubusercontent.com/ospinosystems/arabitofacturacion/master/scripts/cuadre-servidor/desplegar-desde-pc.ps1 -OutFile $env:TEMP\desplegar.ps1; & $env:TEMP\desplegar.ps1"
```

Después, para ver el avance: `ssh usuario@IP "bash ~/private_html/cuadre/scripts/cuadre-servidor/cloudways.sh estado"`
(o `log`).

**Camino manual**, desde el terminal SSH de la aplicación (Cloudways → Application → Application Credentials →
SSH, o cualquier app SSH con esas credenciales). El repositorio es público, no hace falta token:

```bash
cd ~/public_html                      # carpeta de la aplicación (si ya hay archivos, use otra: mkdir ~/cuadre && cd ~/cuadre)
git clone https://github.com/ospinosystems/arabitofacturacion.git .
bash scripts/cuadre-servidor/cloudways.sh instalar   # composer install + .env (pide DB name/user/pass de "MySQL Access" y el storeId)
# subir por SFTP los ZIP a ~/public_html/datos  (WinSCP / FileZilla con las mismas credenciales)
bash scripts/cuadre-servidor/cloudways.sh correr     # arranca en segundo plano (setsid + nohup)
bash scripts/cuadre-servidor/cloudways.sh estado     # o: log
```

La BD de la aplicación (Access Details → MySQL Access) es la que se borra y restaura. Si el servidor se reinicia
o se corta la sesión: `bash scripts/cuadre-servidor/cloudways.sh correr` de nuevo y continúa. Para actualizar el
código: `git pull` y repetir `correr`. Si el PHP del servidor no tiene `bcmath` o `zip`, activarlos en
Cloudways → Server → Settings & Packages → PHP.

## 5. Tiempo estimado

Medido en la prueba sintética (3 meses, 2 máquinas, 182 grupos, 3.600 pedidos): **0,35 s por grupo** con
`--tolerancia-bs=1` (cuadre completo en 1 min). El comando imprime su propia estimación en el paso `preparar` y
un ETA en cada grupo.

| Componente | Cómo se estima | Ejemplo Anaco (3 años × 2 máquinas ≈ 2.200 grupos, respaldo 2 GB) |
|---|---|---|
| Restaurar BD | ~25 MB/s con `mysql` CLI en disco local (XAMPP en HDD puede ser 3–5× más lento) | 2–10 min |
| Titanio | ~20 s por día (API + inserción con inventario) | 29 días ≈ 10 min |
| Cuadre | grupos × 0,3–3 s típico; **máximo** grupos × `--max-segundos` | típico 15 min – 2 h; máximo 9 h |
| Medir | segundos | — |

Días con muchos pedidos y N alto pueden agotar los 15 s del grupo sin llegar a ±1 Bs: aun así el ajuste cubre la
diferencia, solo tarda más. Si el tiempo apremia, `--max-segundos=5` recorta el peor caso a la tercera parte.

## 6. Cómo medir el resultado

1. Tabla del paso `medir` y `resultado_anaco.csv`: por mes, `objetivo_bs` vs `logrado_bs` (diferencia esperada:
   decenas de Bs por mes, por el redondeo del precio a 1 decimal en USD), facturas objetivo vs logradas, días,
   cantidad de ajustes y ajuste máximo, venta total del mes antes del cuadre.
2. `cuadre_<fecha>.csv`: detalle por día y máquina (candidatos, seleccionados, suma, ajuste, %). Filtrar
   `estado = sin_pedidos` (días del CSV sin pedidos en la BD) y `pct_ajuste > 5`.
3. Web: `/reportes/cuadre-diario` y `/reportes/cuadre-diario/validacion?csv=<ruta objetivo_anaco.csv>&fecha_desde=…&fecha_hasta=…`.
4. SQL de control: números repetidos por máquina (`medir` ya lo revisa) y
   `SELECT DATE_FORMAT(fecha_factura,'%Y-%m'), COUNT(*), SUM(ip.monto_bs) FROM pedidos p JOIN items_pedidos ip ON ip.id_pedido=p.id WHERE p.valido=1 GROUP BY 1`.

## 7. Cómo deshacer

- Todo el cuadre o un rango: `php artisan cuadre:pedidos-reset --fecha_desde=… --fecha_hasta=… --si`. Restaura
  precio, monto y monto_bs de cada ítem ajustado y el pago desde `cuadre_ajustes`, y limpia `numero_factura`,
  `maquina_fiscal` y `valido`. Verificado: tras el reset los ítems y pagos quedan idénticos al respaldo previo.
- Alternativa total: importar `respaldo_pre_cuadre_anaco_<fecha>.sql` (o repetir `--desde-paso=restaurar`).
- Titanio: `php artisan titanio:importar --reversar --desde=2026-08-30 --hasta=… --store-id=NN --sucursal=anaco`.

## 8. Problemas frecuentes

| Síntoma | Causa / solución |
|---|---|
| `Usuario 'cajaN' no existe en BD local` | Crear el usuario `cajaN` en `usuarios` (tipo_usuario 4) y repetir el paso `titanio` |
| `inventarios.id=X no existe` | El `source_id` de Titanio no coincide con el inventario de Anaco; revisar si el inventario se migró con `inventario:migrar` |
| `tipo de pago desconocido: …` | Titanio devolvió un método no mapeado; agregarlo en `TitanioImportarPedidos` (switch de pagos) |
| `Got a packet bigger than max_allowed_packet` | Subir `max_allowed_packet=256M` en `my.ini` (XAMPP) o usar el compose del servidor, que ya lo trae |
| `Unknown collation utf8mb4_0900_ai_ci` | Respaldo hecho con MySQL 8; importar en MySQL 8 o reemplazar la collation en el `.sql` |
| `La BD restaurada es de la sucursal 'X'` | El respaldo no es de Anaco o el código es otro (`anaco2`): repetir con `--sucursal=X` |
| Grupos `sin_pedidos` en el reporte | El CSV tiene días sin ventas en la BD (feriado, fecha mal escrita, o falta importar Titanio) |
