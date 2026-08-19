FROM php:8.2-cli

# System deps + PHP extensions needed by Laravel/Filament/PhpSpreadsheet
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libpng-dev libicu-dev libxml2-dev libonig-dev libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite zip gd intl mbstring xml bcmath \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . .

RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs \
    && php artisan config:clear \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 80

CMD php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=80
