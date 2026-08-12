# Entorno de desarrollo con Laravel Sail

Entorno Docker para este proyecto (Laravel 8.83 / PHP 8.1). Los puertos están corridos porque
80 y 3306 suelen estar ocupados por otros proyectos locales (`titaniopos`, `arabitocentral`).

## Servicios

| Servicio | Imagen | Puerto host | Notas |
|---|---|---|---|
| `laravel.test` | `sail-8.1/app` | `8000` → 80 | PHP 8.1 (Laravel 8 no es compatible con 8.2+) |
| | | `6001` | `beyondcode/laravel-websockets` |
| `mysql` | `mysql/mysql-server:8.0` | `3307` → 3306 | DB `arabitofacturacion`, usuario `sail` / `password` |
| `redis` | `redis:alpine` | `6379` | |
| `mailpit` | `axllent/mailpit` | `1025` SMTP / `8025` UI | Captura el correo saliente |

Los puertos se controlan desde `.env`: `APP_PORT`, `FORWARD_DB_PORT`, `FORWARD_REDIS_PORT`,
`WEBSOCKETS_PORT`.

## Instalación desde cero

El PHP del host no sirve si es 8.2+, así que las dependencias se instalan dentro de un contenedor:

```bash
cp .env.example .env

docker run --rm -u "$(id -u):$(id -g)" \
    -v "$(pwd)":/var/www/html -w /var/www/html \
    laravelsail/php81-composer:latest \
    composer install --ignore-platform-reqs

./vendor/bin/sail up -d --build
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
```

App en http://localhost:8000 · Mailpit en http://localhost:8025

## Uso diario

```bash
./vendor/bin/sail up -d          # arrancar
./vendor/bin/sail down           # detener
./vendor/bin/sail logs -f laravel.test
./vendor/bin/sail artisan <cmd>
./vendor/bin/sail mysql          # cliente MySQL
```

Alias recomendado: `alias sail='sh $([ -f sail ] && echo sail || echo vendor/bin/sail)'`

## Datos de prueba

`DatabaseSeeder` está vacío; los seeders se invocan uno por uno:

```bash
./vendor/bin/sail artisan db:seed --class=UsuariosSeeder
./vendor/bin/sail artisan db:seed --class=SucursalSeeder
```

## Assets

El repo versiona los assets compilados en `public/js` y `public/css`, así que no hace falta
compilar para levantar el entorno. Para recompilar (webpack mix, no Vite):

```bash
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

## Websockets

```bash
./vendor/bin/sail artisan websockets:serve   # escucha en 6001, expuesto al host
```

## Nota sobre migraciones

Dos migraciones (`2026_06_19_120000` y `2026_06_19_120001`) definían su FK hacia
`transferencias_inventarios.id` como `integer` SIGNED, mientras que en una DB creada desde cero
`increments()` genera `int UNSIGNED` — MySQL 8 rechaza la FK con error 3780. Ahora ambas detectan
el signo real de la columna en runtime, de modo que funcionan tanto contra la DB de producción
(donde la columna es SIGNED) como en un entorno nuevo.
