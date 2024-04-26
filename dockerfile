# Use the official PHP 7.4 Apache image
FROM php:7.4-apache

# Set the working directory
WORKDIR /var/www/html

# Copy the current directory contents into the container
COPY . /var/www/html/

# Install additional extensions and enable Apache modules
RUN apt-get update && \
    apt-get install -y \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libzip-dev \
        zip \
        curl \
        && docker-php-ext-configure gd --with-freetype --with-jpeg \
        && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql mysqli zip \
        && a2enmod rewrite

# Set recommended PHP.ini settings
RUN { \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.interned_strings_buffer=8'; \
        echo 'opcache.max_accelerated_files=4000'; \
        echo 'opcache.revalidate_freq=60'; \
        echo 'opcache.fast_shutdown=1'; \
        echo 'session.save_handler=files'; \
        echo 'session.save_path="C:\xampp\tmp"'; \
        echo 'session.use_strict_mode=0'; \
        echo 'session.use_cookies=1'; \
        echo 'session.use_only_cookies=1'; \
        echo 'session.cookie_lifetime=0'; \
        echo 'session.cookie_path=/'; \
        echo 'session.cookie_domain= '; \
        echo 'session.cookie_httponly= '; \
        echo 'session.cookie_samesite="None" '; \
        



    } > /usr/local/etc/php/conf.d/opcache-recommended.ini

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
