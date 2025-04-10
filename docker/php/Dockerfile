FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    zip unzip git curl libpng-dev libonig-dev libxml2-dev libicu-dev libjpeg-dev libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd intl \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug

# Create log directory
RUN mkdir -p /var/logs && chown -R www-data:www-data /var/logs

# Copy custom Xdebug configuration
COPY xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

# Copy custom PHP configuration
COPY php.ini /usr/local/etc/php/conf.d/php.ini

# Copy PHP-FPM pool configuration
COPY www.conf /usr/local/etc/php-fpm.d/www.conf

# Set working directory
WORKDIR /var/www