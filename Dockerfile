FROM php:8.2-apache

# Install PostgreSQL libraries, GMP, and PHP extensions
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libgmp-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql gmp bcmath

# Copy application files to Apache root
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80