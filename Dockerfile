FROM dunglas/frankenphp:1-php8.3

WORKDIR /app

RUN install-php-extensions pdo_mysql

COPY Caddyfile /etc/frankenphp/Caddyfile
COPY public/ /app/public/
COPY sql/ /app/sql/

EXPOSE 8080
