# Sistema de Agendamentos — CLAUDE.md

## Visão Geral

SaaS multi-tenant de agendamentos para salões. Cada salão é um **tenant** com slug único, plano de assinatura e isolamento completo de dados.

## Monorepo

```
sistema-agendamentos/
├── api/        # Laravel 13, PHP 8.5, PostgreSQL
├── web/        # Next.js 15, TypeScript, Tailwind CSS
└── .claude/
    ├── agents/ # Subagentes especializados
    └── commands/ # Slash commands
```

## Stack

| Camada | Tecnologia |
|---|---|
| API | Laravel 13 · PHP 8.5 · PostgreSQL |
| Autenticação | Laravel Sanctum (tokens stateless) |
| Autorização | Spatie Laravel Permission |
| Pagamento | MercadoPago SDK (PIX + cartão) |
| Frontend | Next.js 15 · TypeScript · Tailwind CSS · shadcn/ui |
| IA no produto | Claude API (claude-sonnet-4-6) |

## Roles (Spatie Permission)

| Role | Escopo | Acesso |
|---|---|---|
| `super_admin` | Plataforma | Gerencia tenants, planos, métricas globais |
| `salon_owner` | Tenant | Gerencia serviços, agenda, equipe, financeiro do salão |
| `salon_staff` | Tenant | Visualiza agenda, confirma/cancela agendamentos |
| `client` | Tenant | Agenda serviços, visualiza histórico, pagamentos |

## Multi-Tenant — Regra Crítica

**Todo recurso pertencente a um salão DEVE ter `tenant_id`.** Nunca exponha dados de um tenant para outro.

- Models com tenant scope: sempre use o global scope `TenantScope` ou trait `BelongsToTenant`
- Controllers: sempre resolva o tenant via middleware `ResolveTenant` antes de qualquer query
- Policies: verifique `$user->tenant_id === $resource->tenant_id`
- Rotas: prefixo `/api/v1/salao/{tenant:slug}/` para endpoints do salão

## Convenções da API (Laravel)

- Sempre usar `php artisan make:` para criar arquivos
- API versionada: `/api/v1/`
- Usar Eloquent API Resources para respostas
- Form Requests para validação
- Feature tests com Pest 4 para toda rota nova
- Rodar `vendor/bin/pint --dirty` após editar PHP
- PostgreSQL: usar tipos nativos (uuid, jsonb, timestamptz)

## Convenções do Frontend (Next.js)

- App Router (`app/` directory)
- Server Components por padrão; `"use client"` só quando necessário
- TypeScript estrito — proibido `any`
- Estilização apenas com Tailwind CSS + shadcn/ui
- API calls centralizadas em `src/services/` — nunca `fetch` direto nas páginas
- Auth: token Bearer no header via `src/lib/api.ts`
- Rotas protegidas com middleware Next.js

## Orquestração de Agentes

Ao implementar uma feature completa, use o `feature-orchestrator`:
- Ele spawna `api-agent` e `web-agent` em paralelo em worktrees isoladas
- `tenant-guard` revisa o resultado para garantir isolamento correto
- `payment-agent` é chamado apenas para features de pagamento
- `ai-features-agent` implementa as funcionalidades com Claude API

## Como Rodar

```bash
# PostgreSQL via Docker
docker compose up -d postgres

# API
cd api && php artisan serve

# Web
cd web && npm run dev
```

## Ambiente

```bash
# api/.env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=agendamentos
DB_USERNAME=postgres
DB_PASSWORD=secret

# web/.env.local
NEXT_PUBLIC_API_URL=http://127.0.0.1:8000/api/v1
```
