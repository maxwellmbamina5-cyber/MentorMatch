FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev \
        libonig-dev \
        libpq-dev \
        postgresql-client \
    && docker-php-ext-install -j"$(nproc)" curl mbstring pdo_pgsql \
    && a2enmod access_compat headers rewrite \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY . .

RUN mkdir -p /var/www/html/uploads/certificates /var/www/html/uploads/profiles \
    && chown -R www-data:www-data /var/www/html/uploads

COPY docker/ports.conf /etc/apache2/ports.conf
COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/mentormatch-entrypoint
RUN chmod +x /usr/local/bin/mentormatch-entrypoint

ENV PORT=10000
EXPOSE 10000

ENTRYPOINT ["/usr/local/bin/mentormatch-entrypoint"]
CMD ["apache2-foreground"]
