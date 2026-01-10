FROM php:8.4-fpm-alpine

# install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    postgresql-dev \
    postgresql-client \
    nodejs \
    npm

# install PHP extensions
RUN docker-php-ext-install pdo pdo_pgsql pgsql bcmath

# install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# set working directory
WORKDIR /var/www/html

# copy existing application directory permissions
RUN chown -R www-data:www-data /var/www/html

# expose port 9000 for PHP-FPM
EXPOSE 9000

CMD ["php-fpm"]

