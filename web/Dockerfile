FROM php:8.2-apache

# Install PDO MySQL extension for database access
RUN docker-php-ext-install pdo_mysql

# Enable Apache rewrite module for clean URLs (optional but good practice)
RUN a2enmod rewrite

# The web root is /var/www/html, which is the default
