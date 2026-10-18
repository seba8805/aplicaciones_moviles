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

for module in /etc/apache2/mods-enabled/mpm_*.load; do
    [ -e "$module" ] || continue
    nombre="$(basename "$module" .load)"
    if [ "$nombre" != "mpm_prefork" ]; then
        echo "Deshabilitando MPM inesperado: $nombre"
        rm -f "/etc/apache2/mods-enabled/${nombre}.load" "/etc/apache2/mods-enabled/${nombre}.conf"
    fi
done
a2enmod mpm_prefork

sed -i "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

echo "MPM habilitado:"
for module in /etc/apache2/mods-enabled/mpm_*.load; do
    [ -e "$module" ] || continue
    basename "$module" .load
done
apache2ctl -t

echo "Iniciando Apache en el puerto ${PORT}"
exec apache2-foreground
