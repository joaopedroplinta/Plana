#!/bin/sh
set -e

php artisan config:cache
php artisan route:cache
php artisan event:cache

# public/ não fica em volume persistido — o link some toda vez que o
# container é recriado (novo build, restart), então recria aqui sempre
# que faltar em vez de depender de rodar storage:link manualmente.
if [ ! -e public/storage ]; then
    php artisan storage:link
fi

# Só o container "api" roda migrations (compose seta RUN_MIGRATIONS=1 nele);
# queue/scheduler usam a mesma imagem mas não devem correr migrate em paralelo.
if [ "$RUN_MIGRATIONS" = "1" ]; then
    php artisan migrate --force
fi

exec "$@"
