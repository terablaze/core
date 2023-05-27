FROM php:8.2-fpm-alpine

RUN docker-php-ext-install pdo pdo_mysql

# Install composer from the official image
COPY --from=composer /usr/bin/composer /usr/bin/composer

#WORKDIR /var/www

