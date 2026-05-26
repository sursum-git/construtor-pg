FROM composer:2 AS composer

FROM php:8.4-fpm-bookworm AS app

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        libicu-dev \
        libpq-dev \
        libzip-dev \
        nginx \
        postgresql-client \
        supervisor \
        unzip \
        zip \
    && docker-php-ext-install intl pdo_pgsql pgsql zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . /app

RUN cd /app/backend \
    && composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts \
    && mkdir -p /srv/app-state/share /srv/app-state/install /app/backend/var \
    && chown -R www-data:www-data /app/backend/var /srv/app-state

COPY docker/nginx/default.conf /etc/nginx/sites-available/default
COPY docker/supervisor/construtor-pg.conf /etc/supervisor/conf.d/construtor-pg.conf
COPY docker/entrypoint.sh /usr/local/bin/construtor-pg-entrypoint

RUN chmod +x /usr/local/bin/construtor-pg-entrypoint

EXPOSE 80

ENTRYPOINT ["construtor-pg-entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf"]
