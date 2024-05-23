FROM php:8.3-fpm-alpine

WORKDIR /var/www

RUN docker-php-ext-install mysqli