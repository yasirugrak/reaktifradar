FROM php:8.4-fpm-alpine
RUN apk add --no-cache icu-dev libzip-dev libpq-dev oniguruma-dev libpng-dev libjpeg-turbo-dev freetype-dev tzdata \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_pgsql bcmath intl zip mbstring gd opcache pcntl
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_HOME=/tmp/composer
WORKDIR /var/www/html/reaktifradar
CMD ["php-fpm"]
