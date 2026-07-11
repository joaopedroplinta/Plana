# API — CLAUDE.md

Laravel 13 API para o SaaS de agendamentos. Leia o CLAUDE.md raiz para contexto completo do projeto.

## Stack

- PHP 8.5, Laravel 13, PostgreSQL
- Autenticação: Laravel Sanctum (Bearer tokens stateless)
- Autorização: Spatie Laravel Permission
- Testes: Pest 4
- Linter: Laravel Pint

## Comandos essenciais

```bash
php artisan serve                        # dev server (porta 8000)
php artisan test --compact               # rodar todos os testes
php artisan test --compact --filter=X    # filtrar testes
php artisan migrate --no-interaction     # rodar migrations
php artisan route:list --except-vendor   # listar rotas
vendor/bin/pint --dirty                  # formatar PHP modificado
```

## Estrutura de pastas

```
app/
├── Http/
│   ├── Controllers/Api/V1/  # Controllers versionados
│   ├── Middleware/           # ResolveTenant, etc.
│   └── Resources/            # API Resources
├── Models/                   # Eloquent Models
├── Policies/                 # Autorização por recurso
├── Services/                 # Lógica de negócio
│   ├── PaymentService.php
│   └── SchedulingService.php
└── Jobs/                     # Jobs assíncronos (webhooks, etc.)
database/
├── migrations/
├── factories/
└── seeders/
routes/
└── api.php                   # Todas as rotas /api/v1/
```

## Regra mais importante: multi-tenant

Todo resource de salão tem `tenant_id`. Toda query deve filtrar por `tenant_id`. Use o `feature-orchestrator` ou o `api-agent` para garantir isso.

## Formato de resposta padrão

```json
// Sucesso (lista)
{ "data": [...], "meta": { "current_page": 1, "last_page": 5, "total": 48 } }

// Sucesso (item)
{ "data": { "id": "...", ... } }

// Erro de validação (422)
{ "message": "The given data was invalid.", "errors": { "campo": ["msg"] } }

// Erro não autorizado (403)
{ "message": "This action is unauthorized." }
```

## Após editar PHP

Sempre executar:
```bash
vendor/bin/pint --dirty
```
