# ═══════════════════════════════════════════════════════════════════
#  TRINITY — Imagen del sitio web + API (Apache + PHP 8.3)
#  Contexto de build: la RAÍZ del proyecto (ver docker-compose.yml).
#
#  La imagen es autocontenida: lleva dentro el código (Trinity-page/ y
#  app/). Para ver un cambio hay que reconstruir:
#        docker compose up -d --build web
#  …o, para desarrollar sin reconstruir, usar el override opcional:
#        docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d
#  Las imágenes del sitio (WhatsApp/assets, 90 MB) NO van en la imagen: se
#  montan como volumen de solo lectura (ver compose).
#
#  Qué entra a la imagen lo decide .dockerignore (lista blanca).
#  Configuración de Apache/PHP: docker/ (apache.conf, security.conf, php.ini).
# ═══════════════════════════════════════════════════════════════════
FROM php:8.3-apache

# La app solo necesita pdo_mysql de más: curl, mbstring, json y ctype (usados
# por la app y por phpdotenv) ya vienen compilados en la imagen oficial.
# unzip: composer lo usa para descomprimir paquetes.
RUN apt-get update \
 && apt-get install -y --no-install-recommends unzip \
 && rm -rf /var/lib/apt/lists/* \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql \
 && a2enmod rewrite headers \
 && a2dissite 000-default

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY docker/security.conf /etc/apache2/conf-available/trinity-security.conf
COPY docker/apache.conf   /etc/apache2/sites-available/trinity.conf
COPY docker/php.ini       /usr/local/etc/php/conf.d/zz-trinity.ini
RUN a2enconf trinity-security && a2ensite trinity && apache2ctl configtest

WORKDIR /var/www/trinity

# Dependencias PHP (solo vlucas/phpdotenv), con versiones exactas de
# composer.lock. Va ANTES de copiar el código para que Docker reutilice esta
# capa mientras composer.json/lock no cambien. vendor/ queda en la raíz del
# proyecto, hermano de app/ (app/config.php lo busca en ../vendor).
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader \
 && test -f vendor/autoload.php

# Código. Trinity-page/ (front, DocumentRoot) y app/ (API) son carpetas
# HERMANAS, igual que en un servidor real.
COPY Trinity-page ./Trinity-page
COPY app          ./app

# Todo el código es de solo lectura para el proceso web (www-data): si alguien
# lograra escribir un archivo desde la app, no podría dejar un .php.
RUN chown -R root:root /var/www/trinity && chmod -R go-w /var/www/trinity

EXPOSE 80

# Sin curl en la imagen base: el chequeo de salud usa el propio PHP.
HEALTHCHECK --interval=15s --timeout=5s --start-period=10s --retries=5 \
  CMD php -r 'exit(@file_get_contents("http://127.0.0.1/index.html") === false ? 1 : 0);'
