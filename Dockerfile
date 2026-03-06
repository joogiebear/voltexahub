# Stage 1: Build frontend
FROM node:20-alpine AS frontend

WORKDIR /frontend
COPY voltexaforum/package*.json ./
RUN npm ci
COPY voltexaforum/ .
RUN npm run build

# Stage 2: Backend
FROM php:8.4-fpm-alpine

# System dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    git \
    unzip \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    libexif-dev \
    exiftool

# PHP extensions (tokenizer, ctype, fileinfo, opcache, posix are bundled in PHP 8.4-FPM)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        bcmath \
        mbstring \
        xml \
        gd \
        zip \
        pcntl \
        intl \
        exif

# OPcache tuning
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=10000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install PHP deps (layer cache)
COPY voltexahub/composer.json voltexahub/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# Copy backend app
COPY voltexahub/ .

# Copy built frontend into public/spa
COPY --from=frontend /frontend/dist /app/public/spa

# Optimise autoloader
RUN composer dump-autoload --optimize --no-dev

# Storage directories
RUN mkdir -p \
        storage/logs \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Docker config files
COPY voltexahub/docker/nginx.conf /etc/nginx/http.d/default.conf
COPY voltexahub/docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY voltexahub/docker/start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80

ENTRYPOINT ["/start.sh"]
