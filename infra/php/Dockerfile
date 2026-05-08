FROM php:8.3-fpm-alpine
WORKDIR /var/www/html
# System deps
RUN apk add --no-cache \
    bash \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    postgresql-dev \
    unzip \
    zip \
    git \
    curl \
    $PHPIZE_DEPS
# PHP extensions
RUN docker-php-ext-install \
    intl \
    zip \
    pdo \
    pdo_pgsql \
    opcache
# Redis extension (phpredis) for Laravel cache/session/queue
RUN pecl install redis && docker-php-ext-enable redis
# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
# php.ini
COPY php.ini /usr/local/etc/php/conf.d/99-app.ini
CMD ["php-fpm"]
