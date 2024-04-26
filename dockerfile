# Use official PHP image as base
FROM php:7.4-apache

# Install mysqli extension
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Enable Apache modules
RUN a2enmod rewrite

# Install PHP session extension
RUN docker-php-ext-install session

# Copy PHP configuration file with custom session settings
COPY php.ini /usr/local/etc/php/conf.d/php.ini

# Copy your PHP files into the container
COPY . /var/www/html

# Set permissions for Apache
RUN chown -R www-data:www-data /var/www/html

# Expose port 80
EXPOSE 80
