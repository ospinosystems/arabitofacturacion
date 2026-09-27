#!/usr/bin/env bash
# Corre el cuadre completo en un servidor Cloudways (PHP + MySQL locales, sin Docker).
#
#   bash cloudways.sh instalar   # composer install, .env (pide credenciales de la BD), carpetas
#   bash cloudways.sh correr     # lanza cuadre:completo en segundo plano (sobrevive al cierre del SSH)
#   bash cloudways.sh estado     # estado.json + últimas líneas del log
#   bash cloudways.sh log        # seguir el log en vivo
#   bash cloudways.sh detener    # detener el proceso (se reanuda con "correr")
#
# Ejecutar desde la raíz del repositorio (donde está `artisan`). Los ZIP (respaldo de BD y montos objetivo)
# van en la carpeta ./datos (subirlos por SFTP). El .env debe apuntar a la BD de la aplicación de Cloudways
# (Access Details → MySQL Access): esa BD se BORRA y se restaura con el respaldo de la sucursal.
set -euo pipefail

if [[ ! -f artisan ]]; then
  echo "Ejecute este script desde la raíz del repositorio (donde está artisan)." >&2
  exit 1
fi
RAIZ=$(pwd)
CONF="$RAIZ/scripts/cuadre-servidor/cloudways.conf"
LOGDIR="$RAIZ/storage/logs"
PIDFILE="$RAIZ/storage/app/cuadre-completo.pid"
mkdir -p "$LOGDIR" "$RAIZ/storage/app" "$RAIZ/datos"

# Variables por defecto (se pueden fijar en cloudways.conf)
CUADRE_SUCURSAL=${CUADRE_SUCURSAL:-anaco}
TITANIO_DESDE=${TITANIO_DESDE:-2026-08-30}
CUADRE_MAX_SEGUNDOS=${CUADRE_MAX_SEGUNDOS:-15}
CUADRE_TOLERANCIA_BS=${CUADRE_TOLERANCIA_BS:-1}
CUADRE_UMBRAL_AJUSTE=${CUADRE_UMBRAL_AJUSTE:-5}
CUADRE_OPCIONES=${CUADRE_OPCIONES:-}
[[ -f "$CONF" ]] && { set -a; # shellcheck disable=SC1090
  source "$CONF"; set +a; }

php_ok() {
  local v
  v=$(php -r 'echo PHP_VERSION;' 2>/dev/null || echo 0)
  echo "PHP $v"
  for ext in pdo_mysql zip bcmath mbstring; do
    php -m | grep -qi "^$ext$" || echo "  AVISO: falta la extensión PHP '$ext' (Cloudways: Server → Settings & Packages → PHP)"
  done
}

instalar() {
  echo "── Verificando herramientas"
  php_ok
  command -v composer >/dev/null || { echo "Falta composer." >&2; exit 1; }
  command -v mysql >/dev/null || echo "  AVISO: no se encontró el cliente mysql (se usará el importador PHP, más lento)."
  command -v mysqldump >/dev/null || echo "  AVISO: no se encontró mysqldump (no habrá respaldo previo al cuadre; el reverso queda cubierto por cuadre_ajustes)."

  echo "── Dependencias PHP"
  composer install --no-dev --no-interaction --prefer-dist --ignore-platform-req=php 2>&1 | tail -3

  if [[ ! -f .env ]]; then
    echo "── Creando .env (credenciales MySQL de la aplicación en Cloudways: Access Details → MySQL Access)"
    # No interactivo si vienen por variables de entorno (CW_DB_NAME, CW_DB_USER, CW_DB_PASS, CW_STORE_ID).
    DBN=${CW_DB_NAME:-}; DBU=${CW_DB_USER:-}; DBP=${CW_DB_PASS:-}; STORE=${CW_STORE_ID:-}
    [[ -n "$DBN" ]] || read -r -p "  DB name: " DBN
    [[ -n "$DBU" ]] || read -r -p "  DB user: " DBU
    [[ -n "$DBP" ]] || { read -r -s -p "  DB password: " DBP; echo; }
    [[ -n "${CW_STORE_ID+x}" ]] || read -r -p "  storeId de la sucursal en Titanio POS (vacío = omitir Titanio): " STORE
    cat > .env <<EOF
APP_NAME="Arabito Cuadre"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://localhost
LOG_CHANNEL=single
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=$DBN
DB_USERNAME=$DBU
DB_PASSWORD=$DBP
BROADCAST_DRIVER=log
CACHE_DRIVER=file
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
TITANIO_STORE_ID=$STORE
TITANIO_SUCURSAL=$CUADRE_SUCURSAL
EOF
    php artisan key:generate --force >/dev/null
    echo "  .env creado."
  else
    echo "── .env ya existe; se conserva."
  fi

  echo "── Probando conexión a la BD"
  php -r '
    require "vendor/autoload.php"; $app = require "bootstrap/app.php";
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $t = count(Illuminate\Support\Facades\DB::select("SHOW TABLES"));
    echo "  OK: BD ".Illuminate\Support\Facades\DB::connection()->getDatabaseName()." (tablas actuales: $t)\n";
  ' 2>/dev/null || {
    echo "No se pudo conectar a la BD con el .env actual. Revise DB_DATABASE/DB_USERNAME/DB_PASSWORD." >&2; exit 1; }

  echo
  echo "Listo. Ahora:"
  echo "  1. Suba por SFTP los ZIP (respaldo de la BD y montos objetivo) a: $RAIZ/datos"
  echo "  2. Ejecute: bash scripts/cuadre-servidor/cloudways.sh correr"
}

correr() {
  if [[ -f "$PIDFILE" ]] && kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
    echo "Ya hay un proceso corriendo (PID $(cat "$PIDFILE")). Use 'estado' o 'log'."; exit 0
  fi
  ls "$RAIZ"/datos/*.zip >/dev/null 2>&1 || { echo "No hay ZIP en $RAIZ/datos. Súbalos por SFTP primero." >&2; exit 1; }
  [[ -f .env ]] || { echo "Falta .env: ejecute 'bash scripts/cuadre-servidor/cloudways.sh instalar'." >&2; exit 1; }

  STORE=$(grep -E '^TITANIO_STORE_ID=' .env | cut -d= -f2- | tr -d '"' || true)
  OPC=(
    "$RAIZ/datos"
    "--sucursal=$CUADRE_SUCURSAL"
    "--titanio-desde=$TITANIO_DESDE"
    "--max-segundos=$CUADRE_MAX_SEGUNDOS"
    "--tolerancia-bs=$CUADRE_TOLERANCIA_BS"
    "--umbral-ajuste=$CUADRE_UMBRAL_AJUSTE"
    "--trabajo=$RAIZ/storage/app/cuadre-completo/$CUADRE_SUCURSAL"
    --si --no-ansi
  )
  if [[ -z "$STORE" && "$CUADRE_OPCIONES" != *"--sin-titanio"* ]]; then
    echo "TITANIO_STORE_ID vacío en .env: se omite Titanio (agregue el storeId al .env y repita con --desde-paso=titanio)."
    OPC+=(--sin-titanio)
  fi
  # shellcheck disable=SC2206
  [[ -n "$CUADRE_OPCIONES" ]] && OPC+=($CUADRE_OPCIONES)

  LOG="$LOGDIR/cuadre_completo_$(date +%Y%m%d_%H%M%S).log"
  echo "Lanzando en segundo plano. Log: $LOG"
  # setsid + nohup: el proceso sigue aunque se cierre la sesión SSH o el terminal del navegador.
  setsid nohup php -d memory_limit=-1 -d max_execution_time=0 artisan cuadre:completo "${OPC[@]}" >"$LOG" 2>&1 < /dev/null &
  echo $! > "$PIDFILE"
  sleep 2
  echo "PID $(cat "$PIDFILE"). Siga el avance con: bash scripts/cuadre-servidor/cloudways.sh log"
}

estado() {
  local trabajo="$RAIZ/storage/app/cuadre-completo/$CUADRE_SUCURSAL"
  echo "── estado.json"; cat "$trabajo/estado.json" 2>/dev/null || echo "(sin estado aún)"; echo
  echo "── últimas líneas del log"; tail -n 40 "$(ls -t "$LOGDIR"/cuadre_completo_*.log 2>/dev/null | head -1)" 2>/dev/null || echo "(sin log)"
  echo
  if [[ -f "$PIDFILE" ]] && kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then echo "Proceso: corriendo (PID $(cat "$PIDFILE"))"; else echo "Proceso: detenido"; fi
}

case "${1:-}" in
  instalar) instalar ;;
  correr) correr ;;
  estado) estado ;;
  log) tail -f "$(ls -t "$LOGDIR"/cuadre_completo_*.log | head -1)" ;;
  detener) [[ -f "$PIDFILE" ]] && kill "$(cat "$PIDFILE")" 2>/dev/null && echo "Detenido; reanude con 'correr'." || echo "No estaba corriendo." ;;
  *) echo "Uso: bash scripts/cuadre-servidor/cloudways.sh [instalar|correr|estado|log|detener]"; exit 1 ;;
esac
