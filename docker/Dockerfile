FROM php:8.2-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev \
    && docker-php-ext-install pdo pdo_mysql zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/edgeplay.ini

WORKDIR /var/www/html

ENTRYPOINT ["php", "/var/www/html/docker/entrypoint.php"]
CMD ["apache2-foreground"]
