FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader

FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    libpq-dev \
    pkg-config \
    git unzip \
 && docker-php-ext-install curl \
 && docker-php-ext-install pdo_pgsql pgsql \
 && pecl install redis \
 && docker-php-ext-enable redis

WORKDIR /var/www/html

COPY ./public /var/www/html
COPY --from=vendor /app/vendor ./vendor

EXPOSE 80
