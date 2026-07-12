#!/bin/sh
set -e

php artisan config:cache
php artisan route:cache
php artisan event:cache

# Só o container "api" roda migrations (compose seta RUN_MIGRATIONS=1 nele);
# queue/scheduler usam a mesma imagem mas não devem correr migrate em paralelo.
if [ "$RUN_MIGRATIONS" = "1" ]; then
    php artisan migrate --force
fi

exec "$@"
