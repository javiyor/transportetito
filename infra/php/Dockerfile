FROM php:8.3-fpm-alpine
WORKDIR /var/www/html
# System deps
RUN apk add --no-cache \
    bash \
    icu-dev \
    libxml2-dev \
    libzip-dev \
    mariadb-dev \
    oniguruma-dev \
    postgresql-dev \
    unzip \
    zip \
    git \
    curl \
    tesseract-ocr \
    tesseract-ocr-data-spa \
    tesseract-ocr-data-eng \
    poppler-utils \
    $PHPIZE_DEPS
# PHP extensions
RUN docker-php-ext-install -j"$(nproc)" \
    intl \
    soap \
    zip \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    opcache
# Redis extension (phpredis) for Laravel cache/session/queue
RUN pecl install redis && docker-php-ext-enable redis
# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
# php.ini
COPY php.ini /usr/local/etc/php/conf.d/99-app.ini
# PHP-FPM pool config (limit workers to avoid OOM)
COPY www.conf /usr/local/etc/php-fpm.d/www.conf
CMD ["php-fpm"]
