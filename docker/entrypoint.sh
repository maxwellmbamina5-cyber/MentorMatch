#!/bin/sh
set -eu

mkdir -p /var/www/html/uploads/certificates /var/www/html/uploads/profiles /var/www/html/backend/backups
chown -R www-data:www-data /var/www/html/uploads
chown -R www-data:www-data /var/www/html/backend/backups

exec "$@"
