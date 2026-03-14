#!/bin/sh
set -eu

mkdir -p \
    /var/www/html/addons \
    /var/www/html/bootstrap/cache \
    /var/www/html/public/app \
    /var/www/html/storage/app \
    /var/www/html/storage/debugbar \
    /var/www/html/storage/framework/cache \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/httpcache \
    /var/www/html/storage/logs \
    /var/www/html/storage/streams \
    /var/www/html/storage/views/twig

chown -R www-data:www-data /var/www/html/bootstrap/cache /var/www/html/storage

exec "$@"
