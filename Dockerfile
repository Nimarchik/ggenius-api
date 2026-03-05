FROM php:8.2-apache

# Устанавливаем нужные пакеты и расширения PHP
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    libpq-dev \
    pkg-config \
    git unzip \
 && docker-php-ext-install curl \
 && docker-php-ext-install pdo_pgsql pgsql \
 && pecl install redis \
 && docker-php-ext-enable redis

# Устанавливаем Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Устанавливаем рабочую директорию
WORKDIR /var/www/html

# Спочатку копіюємо composer.json і composer.lock
COPY composer.json composer.lock ./

# Встановлюємо залежності
RUN composer install --no-dev --optimize-autoloader

# Потім копіюємо весь код з public
COPY ./public /var/www/html

# Apache уже настроен в php:8.2-apache, поэтому просто экспонируем порт
EXPOSE 80
