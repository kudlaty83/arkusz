#!/bin/sh
set -eu
php /var/www/initialize.php
exec docker-php-entrypoint "$@"
