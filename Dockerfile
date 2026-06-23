FROM node:22-alpine AS asset-builder

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY public ./public
RUN npm run build:css

FROM composer:2 AS vendor-builder

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader

FROM php:8.3-apache AS app

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev \
    && docker-php-ext-install pdo_mysql \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf

COPY . .
COPY --from=vendor-builder /app/vendor ./vendor
COPY --from=asset-builder /app/public/css/app.css ./public/css/app.css

RUN mkdir -p public/storage/uploads/products/cover public/storage/uploads/products/icon \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80
