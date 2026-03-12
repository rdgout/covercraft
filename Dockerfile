# Stage 1: Build frontend assets
FROM node:22-alpine AS node-builder

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js postcss.config.js tailwind.config.js ./
COPY resources/ ./resources/

RUN npm run build

# Stage 2: Production application with FrankenPHP
FROM dunglas/frankenphp:1-php8.4-alpine AS app

# Install system dependencies and PHP extensions
RUN apk add --no-cache \
    curl \
    libpng-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    postgresql-dev \
    && install-php-extensions \
    pdo_pgsql \
    pcntl \
    bcmath \
    opcache \
    zip \
    gd \
    intl \
    mbstring \
    exif \
    sockets \
    redis

# PHP production configuration
RUN echo "opcache.enable=1" > /usr/local/etc/php/conf.d/99-production.ini \
    && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/99-production.ini \
    && echo "opcache.interned_strings_buffer=16" >> /usr/local/etc/php/conf.d/99-production.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/99-production.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/99-production.ini \
    && echo "opcache.save_comments=1" >> /usr/local/etc/php/conf.d/99-production.ini \
    && echo "opcache.fast_shutdown=1" >> /usr/local/etc/php/conf.d/99-production.ini \
    && echo "opcache.jit=1255" >> /usr/local/etc/php/conf.d/99-production.ini \
    && echo "opcache.jit_buffer_size=128M" >> /usr/local/etc/php/conf.d/99-production.ini \
    && echo "expose_php=Off" >> /usr/local/etc/php/conf.d/99-production.ini \
    && echo "memory_limit=256M" >> /usr/local/etc/php/conf.d/99-production.ini \
    && echo "post_max_size=50M" >> /usr/local/etc/php/conf.d/99-production.ini \
    && echo "upload_max_filesize=50M" >> /usr/local/etc/php/conf.d/99-production.ini

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install PHP dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader

# Copy application source
COPY . .

# Copy compiled frontend assets from node-builder
COPY --from=node-builder /app/public/build ./public/build

# Set permissions
RUN chown -R www-data:www-data /app \
    && chmod -R 755 /app/storage \
    && chmod -R 755 /app/bootstrap/cache

# Create Caddyfile
RUN echo ':8000 {' > /etc/caddy/Caddyfile \
    && echo '    root * /app/public' >> /etc/caddy/Caddyfile \
    && echo '    encode zstd gzip' >> /etc/caddy/Caddyfile \
    && echo '' >> /etc/caddy/Caddyfile \
    && echo '    @vite_assets path /build/*' >> /etc/caddy/Caddyfile \
    && echo '    header @vite_assets Cache-Control "public, max-age=31536000, immutable"' >> /etc/caddy/Caddyfile \
    && echo '' >> /etc/caddy/Caddyfile \
    && echo '    php_server' >> /etc/caddy/Caddyfile \
    && echo '}' >> /etc/caddy/Caddyfile

# Copy entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -f http://localhost:8000/up || exit 1

STOPSIGNAL SIGTERM

ENTRYPOINT ["entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
