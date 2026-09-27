#!/usr/bin/env bash
# Lanza cuadre:completo en el servidor, en segundo plano y reanudable.
#
#   bash run.sh            # arranca (o reanuda) el proceso completo en segundo plano
#   bash run.sh estado     # muestra estado.json y las últimas líneas del log
#   bash run.sh log        # sigue el log en vivo (Ctrl+C para salir; el proceso sigue)
#   bash run.sh detener    # detiene el contenedor del proceso (se puede reanudar con run.sh)
#   bash run.sh shell      # abre un shell en el contenedor app (artisan, mysql, etc.)
#
# Si el servidor se reinicia, basta con volver a ejecutar `bash run.sh`: cada paso ya
# completado se omite y el cuadre continúa por los grupos día+máquina pendientes.
set -euo pipefail
cd "$(dirname "$0")"

ENV_FILE=.env.servidor
if [[ ! -f "$ENV_FILE" ]]; then
  echo "Falta $ENV_FILE. Copie .env.servidor.example a $ENV_FILE y complete TITANIO_STORE_ID." >&2
  exit 1
fi
# shellcheck disable=SC1090
set -a; source "$ENV_FILE"; set +a

mkdir -p datos trabajo mysql logs
LOG=logs/cuadre_$(date +%Y%m%d).log
CONTENEDOR=cuadre_completo

compose() { docker compose "$@"; }

case "${1:-correr}" in
  estado)
    echo "── estado.json ──"; cat trabajo/estado.json 2>/dev/null || echo "(sin estado aún)"; echo
    echo "── últimas líneas del log ──"; tail -n 40 "$(ls -t logs/*.log 2>/dev/null | head -1)" 2>/dev/null || echo "(sin log)"
    echo; docker ps --filter "name=$CONTENEDOR" --format 'Proceso: {{.Names}} {{.Status}}' || true
    ;;
  log)
    tail -f "$(ls -t logs/*.log | head -1)"
    ;;
  detener)
    docker stop "$CONTENEDOR" 2>/dev/null && echo "Detenido. Reanude con: bash run.sh" || echo "No estaba corriendo."
    ;;
  shell)
    compose run --rm app bash
    ;;
  correr)
    if docker ps --filter "name=^${CONTENEDOR}$" --format '{{.Names}}' | grep -q .; then
      echo "Ya hay un proceso corriendo ($CONTENEDOR). Use 'bash run.sh estado' o 'bash run.sh log'."; exit 0
    fi
    if ! ls datos/*.zip >/dev/null 2>&1; then
      echo "No hay ZIP en ./datos. Copie ahí el respaldo de la BD y los ZIP de montos objetivo." >&2; exit 1
    fi
    if [[ -z "${TITANIO_STORE_ID:-}" && "${CUADRE_OPCIONES:-}" != *"--sin-titanio"* ]]; then
      echo "TITANIO_STORE_ID vacío en $ENV_FILE (o agregue --sin-titanio en CUADRE_OPCIONES)." >&2; exit 1
    fi

    echo "Construyendo imagen y levantando MariaDB…"
    compose build app >>"$LOG" 2>&1
    compose up -d db >>"$LOG" 2>&1

    # Dependencias PHP y APP_KEY (una sola vez; vendor/ queda en el repo montado).
    if [[ ! -d ../../vendor ]]; then
      echo "Instalando dependencias PHP (composer install)…"
      compose run --rm app composer install --no-dev --no-interaction --ignore-platform-req=php >>"$LOG" 2>&1
    fi
    if [[ -z "${APP_KEY:-}" ]]; then
      APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
      sed -i "s|^APP_KEY=.*|APP_KEY=$APP_KEY|" "$ENV_FILE"
    fi

    OPCIONES=(
      /datos
      "--sucursal=${CUADRE_SUCURSAL:-anaco}"
      "--titanio-desde=${TITANIO_DESDE:-2026-08-30}"
      "--max-segundos=${CUADRE_MAX_SEGUNDOS:-15}"
      "--tolerancia-bs=${CUADRE_TOLERANCIA_BS:-1}"
      "--umbral-ajuste=${CUADRE_UMBRAL_AJUSTE:-5}"
      --trabajo=/trabajo
      --permitir-remoto
      --si
      --no-ansi
    )
    [[ -n "${TITANIO_STORE_ID:-}" ]] && OPCIONES+=("--store-id=${TITANIO_STORE_ID}")
    [[ -n "${TITANIO_HASTA:-}" ]] && OPCIONES+=("--titanio-hasta=${TITANIO_HASTA}")
    # shellcheck disable=SC2206
    [[ -n "${CUADRE_OPCIONES:-}" ]] && OPCIONES+=(${CUADRE_OPCIONES})

    echo "Lanzando cuadre:completo en segundo plano (log: $LOG)…"
    # -d: el contenedor sigue vivo aunque se cierre la sesión SSH. --rm lo limpia al terminar.
    compose run -d --rm --name "$CONTENEDOR" app php artisan cuadre:completo "${OPCIONES[@]}" >/dev/null
    # Volcar la salida del contenedor al log del host (en segundo plano, con nohup).
    nohup docker logs -f "$CONTENEDOR" >>"$LOG" 2>&1 &
    sleep 3
    echo "En marcha. Siga el avance con:  bash run.sh log      (estado: bash run.sh estado)"
    ;;
  *)
    echo "Uso: bash run.sh [correr|estado|log|detener|shell]"; exit 1
    ;;
esac
