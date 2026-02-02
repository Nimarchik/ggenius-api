FROM php:8.2-apache

# Встановлюємо libcurl перед розширенням PHP
RUN apt-get update && apt-get install -y libcurl4-openssl-dev pkg-config

# Встановлюємо curl як розширення
RUN docker-php-ext-install curl

# Копіюємо файли в папку веб-сервера
COPY ./public /var/www/html/

RUN apt-get update && apt-get install -y libpq-dev \
  && docker-php-ext-install pdo_pgsql

# Устанавливаем PostgreSQL и необходимые расширения
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo_pgsql pgsql