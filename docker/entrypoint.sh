#!/bin/sh
set -eu

mkdir -p /var/www/html/uploads/certificates /var/www/html/uploads/profiles
chown -R www-data:www-data /var/www/html/uploads

exec "$@"
