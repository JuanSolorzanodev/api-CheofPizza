FROM dunglas/frankenphp:1-php8.3-bookworm

ARG DEBIAN_FRONTEND=noninteractive

ENV APP_ENV=production \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        git \
        unzip \
        default-mysql-client \
    && install-php-extensions \
        bcmath \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        sockets \
        zip \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

COPY . .

COPY Caddyfile /etc/frankenphp/Caddyfile

RUN composer dump-autoload \
        --no-dev \
        --classmap-authoritative \
        --no-interaction \
    && php artisan package:discover --ansi \
    && mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && chmod +x \
        docker/prepare-runtime.sh \
        docker/start-api.sh \
        docker/start-worker.sh \
        docker/start-reverb.sh \
        docker/start-scheduler.sh

EXPOSE 8080

CMD ["sh", "docker/start-api.sh"]
