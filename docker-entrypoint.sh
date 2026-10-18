#!/bin/sh
set -eu

PORT="${PORT:-80}"
case "$PORT" in
    ''|*[!0-9]*)
        echo "PORT debe ser un numero entre 1 y 65535" >&2
        exit 1
        ;;
esac
if [ "$PORT" -lt 1 ] || [ "$PORT" -gt 65535 ]; then
    echo "PORT debe ser un numero entre 1 y 65535" >&2
    exit 1
fi

sed -i "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

echo "Iniciando Apache en el puerto ${PORT}"
exec apache2-foreground
