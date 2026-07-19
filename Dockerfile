# syntax=docker/dockerfile:1.18

ARG COMPOSER_BASE_IMAGE="composer:2@sha256:5946476338742b200bb9ff88f8be56275ddae4b3949c72305cb0dbf10cfcb760"
ARG NODE_BASE_IMAGE="node:24-bookworm-slim@sha256:cb4e8f7c443347358b7875e717c29e27bf9befc8f5a26cf18af3c3dec80e58c5"
ARG PHP_BASE_IMAGE="dunglas/frankenphp:1-php8.5-trixie@sha256:2d970ecf0cc0f69039cb1bb749d5e84a20900fddf6c16823a768f6dc38f4a084"

FROM ${COMPOSER_BASE_IMAGE} AS composer

FROM ${NODE_BASE_IMAGE} AS node

FROM ${PHP_BASE_IMAGE} AS php-base

RUN install-php-extensions \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        zip \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-holizuki.ini"

FROM php-base AS backend

WORKDIR /app

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/composer-cache \
    COMPOSER_CACHE_DIR=/tmp/composer-cache composer install \
        --classmap-authoritative \
        --no-autoloader \
        --no-dev \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --prefer-dist

COPY . .

RUN composer dump-autoload \
        --classmap-authoritative \
        --no-dev \
        --no-interaction \
    && composer check-platform-reqs --no-dev \
    && ln -s /app/storage/app/public /app/public/storage \
    && rm -rf /root/.composer /tmp/composer-cache

FROM php-base AS frontend

COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules

RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

WORKDIR /build

COPY package.json package-lock.json ./
RUN --mount=type=cache,target=/root/.npm \
    npm ci --ignore-scripts

COPY --from=backend /app /build
RUN npm run build:ssr

FROM php-base AS runtime

ARG APP_COMMIT="unknown"

LABEL org.opencontainers.image.source="https://github.com/msaifmfz/holizuki" \
      org.opencontainers.image.revision="${APP_COMMIT}"

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    SERVER_NAME=:8080 \
    TELESCOPE_ENABLED=false \
    XDG_CONFIG_HOME=/tmp/caddy/config \
    XDG_DATA_HOME=/tmp/caddy/data

WORKDIR /app

RUN groupadd --gid 10001 app \
    && useradd --uid 10001 --gid app --no-create-home --shell /usr/sbin/nologin app \
    && install -d -o app -g app \
        /app/bootstrap/cache \
        /app/storage/app/public \
        /app/storage/framework/cache/data \
        /app/storage/framework/sessions \
        /app/storage/framework/views \
        /app/storage/logs \
        /tmp/caddy/config \
        /tmp/caddy/data

COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=backend --chown=app:app /app /app
COPY --from=frontend --chown=app:app /build/public/build /app/public/build
COPY --from=frontend --chown=app:app /build/bootstrap/ssr /app/bootstrap/ssr
COPY --chown=root:root docker/entrypoint.sh /usr/local/bin/holizuki

RUN chmod 0755 /usr/local/bin/holizuki

USER 10001:10001

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/holizuki"]
CMD ["web"]

HEALTHCHECK --interval=10s --timeout=3s --start-period=10s --retries=3 \
    CMD ["php", "-r", "$host = parse_url((string) getenv('APP_URL'), PHP_URL_HOST) ?: 'localhost'; $context = stream_context_create(['http' => ['header' => \"Host: {$host}\\r\\n\"]]); exit(@file_get_contents('http://127.0.0.1:8080/up', false, $context) === false ? 1 : 0);"]
