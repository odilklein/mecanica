FROM php:8.2-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libonig-dev libzip-dev unzip git poppler-utils \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_mysql mbstring zip

# Hardening básico do PHP.
RUN { \
        echo 'expose_php=Off'; \
        echo 'display_errors=Off'; \
        echo 'log_errors=On'; \
        echo 'session.use_strict_mode=1'; \
        echo 'session.cookie_httponly=1'; \
        echo 'session.cookie_samesite=Lax'; \
        echo 'upload_max_filesize=25M'; \
        echo 'post_max_size=25M'; \
    } > /usr/local/etc/php/conf.d/security.ini

RUN { \
        echo '<Directory /var/www/html>'; \
        echo '    AllowOverride All'; \
        echo '</Directory>'; \
    } > /etc/apache2/conf-available/allowoverride.conf \
    && { \
        echo 'ServerTokens Prod'; \
        echo 'ServerSignature Off'; \
    } > /etc/apache2/conf-available/server-tokens.conf \
    && a2enconf allowoverride \
    && a2enconf server-tokens \
    && a2enmod rewrite headers \
    && sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf

COPY --chown=www-data:www-data . /var/www/html/

# Diretórios de runtime (cache/logs) não vão no build; cria com dono correto.
RUN mkdir -p /var/www/html/storage/cache/canaldapeca /var/www/html/storage/logs \
    && chown -R www-data:www-data /var/www/html/storage

EXPOSE 80
