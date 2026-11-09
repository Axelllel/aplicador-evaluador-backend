# Compilacion de Tailwind/Vite para que Render pueda servir las vistas Blade.
FROM node:22-bookworm-slim AS frontend
WORKDIR /app
COPY package.json ./
RUN npm install --no-audit --no-fund
COPY . .
RUN npm run build

FROM php:8.4-apache-bookworm
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
       git unzip libpq-dev libonig-dev libxml2-dev libzip-dev libpng-dev \
    && docker-php-ext-install -j$(nproc) pdo_pgsql mbstring dom zip bcmath opcache gd \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY . .
COPY --from=frontend /app/public/build /var/www/html/public/build
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && mkdir -p storage/framework/cache/data storage/framework/sessions \
       storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
RUN sed -ri 's#DocumentRoot /var/www/html#DocumentRoot /var/www/html/public#' \
      /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n' \
      > /etc/apache2/conf-available/bigbitbyte.conf \
    && a2enconf bigbitbyte \
    && sed -ri 's/^Listen 80$/Listen 10000/' /etc/apache2/ports.conf \
    && sed -ri 's/<VirtualHost \*:80>/<VirtualHost *:10000>/' \
      /etc/apache2/sites-available/000-default.conf
ENV SESSION_DRIVER=cookie
EXPOSE 10000
CMD ["apache2-foreground"]
