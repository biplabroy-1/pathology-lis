# syntax=docker/dockerfile:1

# ---- Frontend assets (Vite + Tailwind v4) ----
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci
COPY . .
RUN npm run build

# ---- PHP 8.3 + Apache runtime ----
FROM php:8.4-apache

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    COMPOSER_ALLOW_SUPERUSER=1 \
    APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
        libzip-dev libcurl4-openssl-dev libicu-dev libonig-dev libxml2-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath curl gd intl mbstring opcache pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

# Apache: serve Laravel's public/ dir, allow .htaccess overrides, enable rewrite
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/sites-available/*.conf \
        /etc/apache2/apache2.conf \
        /etc/apache2/conf-available/*.conf \
    && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf \
    && a2enmod rewrite

WORKDIR /var/www/html
COPY . .
COPY --from=assets /app/public/build ./public/build
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress \
    && mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache \
        storage/app/public bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

EXPOSE 80

# Runs at container start: env vars (APP_KEY, DB_*) come from Coolify at runtime.
ENTRYPOINT ["sh", "-c", "set -e; \
    if [ -z \"$APP_KEY\" ]; then echo 'ERROR: APP_KEY is not set. Generate with: php artisan key:generate --show'; exit 1; fi; \
    php artisan migrate --force; \
    php artisan config:cache; \
    php artisan view:cache; \
    php artisan storage:link || true; \
    exec apache2-foreground"]
