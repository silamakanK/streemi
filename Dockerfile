# Image de production de Streemi : PHP 8.3 + FrankenPHP (serveur web Caddy intégré, HTTPS automatique)
FROM dunglas/frankenphp:1-php8.3-bookworm

WORKDIR /app

RUN install-php-extensions pdo_pgsql intl opcache zip apcu

COPY docker/php/prod.ini $PHP_INI_DIR/conf.d/zz-streemi.ini
COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    COMPOSER_ALLOW_SUPERUSER=1

# Dépendances d'abord : cette couche reste en cache tant que composer.lock ne change pas
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress --no-interaction

COPY . .
RUN composer dump-autoload --classmap-authoritative --no-dev \
    && mkdir -p var public/uploads/covers \
    && APP_SECRET=build php bin/console cache:warmup \
    && rm /usr/bin/composer

COPY docker/entrypoint.sh /usr/local/bin/streemi-entrypoint
RUN chmod +x /usr/local/bin/streemi-entrypoint

ENTRYPOINT ["streemi-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
