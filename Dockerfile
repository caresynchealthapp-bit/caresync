FROM php:8.2-apache

# Install required system packages, extensions, and tools
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libgmp-dev \
    git \
    unzip \
    && docker-php-ext-install pdo pdo_pgsql pgsql gmp bcmath

# Install Composer directly into the container
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files to Apache root
WORKDIR /var/www/html
COPY . /var/www/html/

# Install composer packages inside the Linux image
RUN composer update --no-dev --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80