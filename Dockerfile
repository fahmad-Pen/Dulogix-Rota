FROM php:8.5-fpm-alpine
RUN apk add --no-cache icu-dev libzip-dev mariadb-connector-c-dev $PHPIZE_DEPS \
 && docker-php-ext-install intl pdo_mysql zip \
 && apk del $PHPIZE_DEPS
WORKDIR /app
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
