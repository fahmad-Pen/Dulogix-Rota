FROM php:8.5-fpm-alpine
RUN apk add --no-cache icu-dev libzip-dev mariadb-connector-c-dev poppler-utils $PHPIZE_DEPS \
 && docker-php-ext-install intl pdo_mysql zip \
 && apk del $PHPIZE_DEPS
RUN printf 'upload_max_filesize=50M\npost_max_size=52M\n' > /usr/local/etc/php/conf.d/uploads.ini
WORKDIR /app
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
