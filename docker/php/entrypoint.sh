#!/bin/sh
set -e

# Only the process that boots php-fpm installs dependencies and bootstraps
# .env/APP_KEY. queue/scheduler run artisan commands directly and rely on
# depends_on: app: condition: service_healthy to wait until this has finished
# writing into the bind-mounted tree, so exactly one process ever runs
# `composer install` against it.
if [ "$1" = "php-fpm" ]; then
    if [ ! -f vendor/autoload.php ]; then
        composer install --no-interaction --prefer-dist --optimize-autoloader
    fi

    if [ ! -f .env ]; then
        cp .env.example .env
    fi

    if ! grep -q '^APP_KEY=base64' .env 2>/dev/null; then
        php artisan key:generate --force
    fi

    php artisan storage:link --force || true
fi

exec "$@"
