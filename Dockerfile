FROM php:8.5-cli-alpine

# Установка системных зависимостей и библиотеки libpq-dev для PostgreSQL
RUN apk add --no-cache \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    oniguruma-dev \
    libpq-dev \
    && docker-php-ext-install pdo_pgsql pgsql mbstring exif pcntl bcmath gd

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Копируем манифесты зависимостей
COPY composer.json composer.lock* ./

# Устанавливаем зависимости с отключением скриптов на этапе сборки
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# Копируем весь исходный код проекта
COPY . .

# Выставляем права на папки для записи
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]