# Dockerfile Example
FROM php:8.3-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    cron \
    nano \
    supervisor

# Install PHP extensions
RUN docker-php-ext-configure gd --with-jpeg
RUN docker-php-ext-install gd pdo pdo_mysql mbstring exif pcntl bcmath intl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Add cron job
COPY docker/crontab /etc/cron.d/my-cron
RUN chmod 0644 /etc/cron.d/my-cron
RUN crontab /etc/cron.d/my-cron

# PHP custom configuration
COPY docker/php.ini /usr/local/etc/php/conf.d/php-custom.ini

# Add Supervisor configuration
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Set working directory
WORKDIR /var/www/html

# Copy Laravel application
COPY . .

# Permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Run Supervisor
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf"]
