FROM php:8.5-apache

RUN docker-php-ext-install mysqli \
    && a2enmod headers rewrite

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html
COPY . .

RUN mkdir -p \
    storage/private_uploads \
    storage/logs \
    storage/tmp \
    public/assets/uploads \
    && chown -R www-data:www-data storage public/assets/uploads

