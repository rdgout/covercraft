# syntax=docker/dockerfile:1
ARG FRANKENPHP_VERSION=1.12
ARG PHP_VERSION=8.5
ARG NODE_VERSION=24

FROM node:${NODE_VERSION}-trixie-slim AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN --mount=type=cache,target=/root/.npm npm ci
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

FROM dunglas/frankenphp:${FRANKENPHP_VERSION}-php${PHP_VERSION}-trixie AS base
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    VIEW_COMPILED_PATH=/app/bootstrap/cache/views
RUN apt-get update \
    && apt-get install -y --no-install-recommends curl \
    && rm -rf /var/lib/apt/lists/*
RUN install-php-extensions pdo_pgsql pcntl bcmath opcache zip intl mbstring redis
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY --from=composer/composer:2-bin /composer /usr/bin/composer
WORKDIR /app

FROM base AS vendor
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/composer-cache \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    composer install --no-dev --no-scripts --no-autoloader --prefer-dist

FROM base AS app
COPY docker/php.ini /usr/local/etc/php/conf.d/99-covercraft.ini
COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=frontend /app/public/build ./public/build
RUN chmod +x /usr/local/bin/entrypoint \
    && mkdir -p bootstrap/cache/views storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && APP_ENV=production APP_DEBUG=false CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync php artisan view:cache \
    && APP_ENV=production APP_DEBUG=false CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync php artisan event:cache \
    && ln -s /app/storage/app/public /app/public/storage \
    && chown -R www-data:www-data bootstrap/cache storage /data /config
USER www-data
EXPOSE 8000
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8000/up || exit 1
STOPSIGNAL SIGTERM
ENTRYPOINT ["entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
