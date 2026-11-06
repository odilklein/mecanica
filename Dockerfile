FROM php:8.2-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libonig-dev libzip-dev unzip git \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_mysql mbstring zip

RUN { \
        echo '<Directory /var/www/html>'; \
        echo '    AllowOverride All'; \
        echo '</Directory>'; \
    } > /etc/apache2/conf-available/allowoverride.conf \
    && a2enconf allowoverride \
    && a2enmod rewrite headers \
    && sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf

COPY --chown=www-data:www-data . /var/www/html/

EXPOSE 80
