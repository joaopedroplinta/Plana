# Plana — API

Backend do [Plana](../README.md), SaaS multi-tenant de agendamentos. Este
README cobre só o essencial pra rodar e testar esta pasta — a visão geral do
produto, regras de negócio e lista completa de rotas estão no
[README da raiz](../README.md); convenções de código em [`CLAUDE.md`](../CLAUDE.md)
e [`.claude/rules/api-conventions.md`](../.claude/rules/api-conventions.md).

## Stack

PHP 8.5 · Laravel 13 · PostgreSQL · Sanctum · Spatie Laravel Permission · MercadoPago SDK · Pest 4

## Rodando localmente

```bash
# Na raiz do monorepo — sobe Postgres (e Redis, se QUEUE_CONNECTION/CACHE_STORE usarem redis)
docker compose up -d

cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed   # --seed cria tenant/usuários de demonstração
php artisan serve            # http://127.0.0.1:8000/api/v1
```

## Comandos essenciais

```bash
php artisan test --compact               # suite Pest completa
php artisan test --compact --filter=X    # filtrar um teste
vendor/bin/pint --dirty                  # formata só o PHP alterado
php artisan route:list --except-vendor   # listar rotas registradas
```

## Deploy

Ver [`DEPLOY.md`](../DEPLOY.md) na raiz — cobre as duas opções em produção
(VM Docker própria ou Render + Neon) e o checklist de variáveis de ambiente.
