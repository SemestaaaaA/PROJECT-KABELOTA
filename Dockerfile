# Kabelota for Railway (or any Docker host). One container runs the web server,
# the queue worker and the scheduler; uploads + SQLite live on a volume at /data.

# 1) Front-end assets (Tailwind, Alpine, self-hosted fonts)
FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# 2) PHP app on FrankenPHP
FROM dunglas/frankenphp:1-php8.4-bookworm

RUN install-php-extensions pdo_mysql pdo_sqlite gd intl zip bcmath pcntl opcache exif \
    && apt-get update \
    && apt-get install -y --no-install-recommends sqlite3 default-mysql-client unzip \
    && rm -rf /var/lib/apt/lists/* \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf 'upload_max_filesize=8M\npost_max_size=20M\nmemory_limit=256M\nexpose_php=Off\n' > "$PHP_INI_DIR/conf.d/99-kabelota.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --no-progress

COPY . .
COPY --from=assets /app/public/build ./public/build
RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && php artisan filament:assets \
    && chmod +x deploy/railway/start.sh

ENV APP_ENV=production \
    LOG_CHANNEL=stderr \
    DATA_DIR=/data

EXPOSE 8080
CMD ["deploy/railway/start.sh"]
