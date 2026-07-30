# syntax=docker/dockerfile:1.7

ARG PHP_VERSION=8.4

FROM node:22-bookworm-slim AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN --mount=type=cache,target=/root/.npm npm ci

COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
RUN npm run build

FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/cache \
    composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --optimize-autoloader \
        --prefer-dist

FROM php:${PHP_VERSION}-fpm-bookworm AS app

ARG APP_VERSION=
ARG VCS_REF=

LABEL org.opencontainers.image.title="EcclesiaOS" \
      org.opencontainers.image.description="Church management platform application runtime" \
      org.opencontainers.image.source="https://github.com/visezion/EcclesiaOS" \
      org.opencontainers.image.revision="${VCS_REF}" \
      org.opencontainers.image.version="${APP_VERSION}"

ENV APP_CONTAINERIZED=true \
    COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update \
    && apt-get upgrade -y \
    && apt-get install -y --no-install-recommends \
        $PHPIZE_DEPS \
        curl \
        default-mysql-client \
        gosu \
        libcurl4-openssl-dev \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libpq-dev \
        libsqlite3-dev \
        libxml2-dev \
        libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        curl \
        dom \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
        pdo_sqlite \
        xml \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get install -y --no-install-recommends \
        libcurl4 \
        libfreetype6 \
        libicu72 \
        libjpeg62-turbo \
        libonig5 \
        libpng16-16 \
        libpq5 \
        libsqlite3-0 \
        libxml2 \
        libzip4 \
    && apt-get purge -y --auto-remove \
        $PHPIZE_DEPS \
        libcurl4-openssl-dev \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libpq-dev \
        libsqlite3-dev \
        libxml2-dev \
        libzip-dev \
        linux-libc-dev \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=frontend --chown=www-data:www-data /app/public/build ./public/build
COPY docker/php/production.ini "$PHP_INI_DIR/conf.d/99-ecclesiaos.ini"
COPY docker/php/zz-app.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/ecclesiaos-entrypoint

RUN mkdir -p \
        bootstrap/cache \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
    && rm -rf public/storage \
    && ln -s ../storage/app/public public/storage \
    && if [ -n "$APP_VERSION" ]; then printf '%s\n' "$APP_VERSION" > VERSION; fi \
    && rm -f bootstrap/cache/*.php \
    && chown -R www-data:www-data bootstrap/cache storage \
    && php artisan package:discover --ansi

EXPOSE 9000

ENTRYPOINT ["ecclesiaos-entrypoint"]
CMD ["php-fpm", "-F"]

FROM nginx:1.28-alpine AS web

LABEL org.opencontainers.image.title="EcclesiaOS Web" \
      org.opencontainers.image.description="Nginx edge for the EcclesiaOS application" \
      org.opencontainers.image.source="https://github.com/visezion/EcclesiaOS"

RUN apk upgrade --no-cache

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public

RUN mkdir -p /var/www/html/storage/app/public \
    && rm -rf /var/www/html/public/storage \
    && ln -s ../storage/app/public /var/www/html/public/storage

EXPOSE 80

HEALTHCHECK --interval=15s --timeout=5s --start-period=30s --retries=5 \
    CMD wget -q -O /dev/null http://127.0.0.1/up || exit 1
