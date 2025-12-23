FROM php:8.2-apache

# Install dependencies and extensions
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install pdo pdo_mysql zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy composer files first (better Docker caching)
COPY composer.json composer.lock* ./

# Install PHP dependencies (including PHPMailer)
RUN composer install --no-dev --optimize-autoloader

# Copy all files (including mooc_assets)
COPY . .

# Set permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80