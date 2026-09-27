# Imagen mínima para correr los comandos artisan del cuadre en un servidor Linux.
# PHP 8.2 CLI + extensiones que usa la app (pdo_mysql, zip, bcmath, gd, intl, mbstring)
# + cliente mariadb (mysql / mysqldump) para restaurar y respaldar la BD.
FROM php:8.2-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libicu-dev libonig-dev \
        mariadb-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql zip bcmath gd intl mbstring \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# PHP sin límite de memoria/tiempo para procesos largos por CLI.
RUN { echo 'memory_limit=-1'; echo 'max_execution_time=0'; } > /usr/local/etc/php/conf.d/cuadre.ini

WORKDIR /app
