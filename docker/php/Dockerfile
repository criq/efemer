FROM php:8.4-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    $PHPIZE_DEPS \
    curl \
    git \
    icu-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libxml2-dev \
    linux-headers \
    oniguruma-dev \
    unzip \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) pdo_mysql mbstring exif pcntl bcmath gd intl \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del $PHPIZE_DEPS linux-headers

# Create log directory
RUN mkdir -p /var/logs && chown -R www-data:www-data /var/logs

# Copy custom Xdebug configuration
COPY xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

# Copy custom PHP configuration
COPY php.ini /usr/local/etc/php/conf.d/php.ini

# Copy PHP-FPM pool configuration
COPY www.conf /usr/local/etc/php-fpm.d/www.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

# Set working directory
WORKDIR /var/www
