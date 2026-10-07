FROM php:8.3-apache-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libzip-dev libxml2-dev libonig-dev \
    && docker-php-ext-install pdo_mysql zip mbstring dom \
    && php -r 'foreach (["pdo_mysql", "zip", "SimpleXML", "dom"] as $ext) { if (!extension_loaded($ext)) { fwrite(STDERR, "Missing extension: ".$ext); exit(1); } }' \
    && rm -rf /var/lib/apt/lists/*
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
    && printf '<Directory /var/www/html/public>\nOptions -Indexes\nAllowOverride None\nRequire all granted\n</Directory>\n' > /etc/apache2/conf-available/control-vehicular.conf \
    && a2enconf control-vehicular
COPY docker-php.ini /usr/local/etc/php/conf.d/control-vehicular.ini
COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html
EXPOSE 80
CMD ["bash", "/var/www/html/bin/start-app.sh"]