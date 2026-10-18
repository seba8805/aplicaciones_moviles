FROM php:8.3-apache

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev libonig5 ca-certificates \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring \
    && apt-get purge -y --auto-remove libonig-dev \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite \
    && sed -ri "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n' > /etc/apache2/conf-available/public.conf \
    && a2enconf public

WORKDIR /var/www/html

COPY public/ /var/www/html/public/
COPY sql/ /var/www/html/sql/
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
