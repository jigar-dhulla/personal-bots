# syntax=docker/dockerfile:1.7

ARG WACLI_VERSION=0.18.3

FROM --platform=$BUILDPLATFORM debian:bookworm-slim AS wacli-fetch
ARG TARGETOS
ARG TARGETARCH
ARG WACLI_VERSION
RUN apt-get update \
    && apt-get install -y --no-install-recommends curl ca-certificates \
    && curl -fsSL \
        "https://github.com/openclaw/wacli/releases/download/v${WACLI_VERSION}/wacli_${WACLI_VERSION}_${TARGETOS}_${TARGETARCH}.tar.gz" \
        | tar -xz -C /tmp --wildcards '*wacli' \
    && chmod +x /tmp/wacli

FROM --platform=$BUILDPLATFORM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources resources
RUN npm run build

FROM dunglas/frankenphp:1-php8.4-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libsqlite3-dev \
        tini \
    && docker-php-ext-install pdo_sqlite pcntl opcache \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY --from=wacli-fetch /tmp/wacli /usr/local/bin/wacli

ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --prefer-dist

COPY . .
COPY --from=assets /app/public/build public/build
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && composer dump-autoload --optimize --no-dev \
    && php artisan config:clear \
    && php artisan route:clear

EXPOSE 8080
ENTRYPOINT ["/usr/bin/tini", "--"]
CMD ["frankenphp", "php-server", "--listen", ":8080", "--root", "public/"]
