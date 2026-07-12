# ---- Etapa 1: dependências PHP (composer) ----
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --no-progress \
    --optimize-autoloader

COPY . .
RUN composer dump-autoload --optimize --no-dev

# ---- Etapa 2: runtime (PHP-FPM + Nginx + Supervisor) ----
FROM php:8.5-fpm-alpine AS runtime

RUN apk add --no-cache \
        nginx \
        supervisor \
        postgresql-client \
        libpq-dev \
        icu-dev \
        oniguruma-dev \
    && docker-php-ext-configure pgsql \
    && docker-php-ext-install pdo pdo_pgsql pgsql intl opcache

RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.enable_cli=0'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.max_accelerated_files=10000'; \
        echo 'opcache.validate_timestamps=0'; \
    } > /usr/local/etc/php/conf.d/opcache-prod.ini

WORKDIR /var/www/html

COPY --from=vendor /app ./
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh

RUN chmod +x /entrypoint.sh \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R ug+rwX storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
