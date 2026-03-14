# syntax=docker/dockerfile:1.7

FROM node:20-bookworm-slim AS frontend_assets
WORKDIR /app
COPY package.json ./
RUN npm install
COPY resources ./resources
COPY webpack.mix.js ./
RUN npm run production

FROM php:8.4-apache-bookworm AS base

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer \
    PATH="/var/www/html/vendor/bin:${PATH}"

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        git \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libxml2-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pdo_mysql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && a2enmod headers rewrite expires \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint-app

RUN chmod +x /usr/local/bin/docker-entrypoint-app

WORKDIR /var/www/html
ENTRYPOINT ["docker-entrypoint-app"]

FROM base AS composer_deps
COPY . .
RUN composer install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction

FROM base AS development
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
CMD ["apache2-foreground"]

FROM base AS production
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY . .
COPY --from=composer_deps /var/www/html/vendor ./vendor
COPY --from=frontend_assets /app/public/css ./public/css
COPY --from=frontend_assets /app/public/js ./public/js
COPY --from=frontend_assets /app/public/mix-manifest.json ./public/mix-manifest.json

RUN chown -R www-data:www-data /var/www/html/bootstrap/cache /var/www/html/storage

CMD ["apache2-foreground"]
