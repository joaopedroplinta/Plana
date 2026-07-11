# Sistema de Agendamentos — CLAUDE.md

## Visão Geral

SaaS multi-tenant de agendamentos para salões. Cada salão é um **tenant** com slug único, plano de assinatura e isolamento completo de dados.

## Monorepo

```
sistema-agendamentos/
├── api/        # Laravel 13, PHP 8.5, PostgreSQL
├── web/        # Next.js 15, TypeScript, Tailwind CSS
└── .claude/
    ├── agents/   # Subagentes especializados
    ├── commands/ # Slash commands
    └── rules/    # Convenções por escopo de arquivo (api/** e web/**)
```

## Stack

| Camada | Tecnologia |
|---|---|
| API | Laravel 13 · PHP 8.5 · PostgreSQL |
| Autenticação | Laravel Sanctum (tokens stateless) |
| Autorização | Spatie Laravel Permission |
| Pagamento | MercadoPago SDK (PIX + cartão) |
| Frontend | Next.js 15 · TypeScript · Tailwind CSS · shadcn/ui |

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
- Policies: dupla verificação — `$user->belongsToTenant($currentTenant) && $resource->tenant_id === $currentTenant->id`
- Rotas: prefixo `/api/v1/salao/{tenant:slug}/` para endpoints do salão
- FKs de outros tenants em requests: sempre usar `Rule::exists('tabela', 'id')->where('tenant_id', app('currentTenant')->id)`

## Convenções da API (Laravel)

- Sempre usar `php artisan make:` para criar arquivos
- API versionada: `/api/v1/`
- Usar Eloquent API Resources para respostas
- Form Requests para validação
- Feature tests com Pest 4 para toda rota nova
- Rodar `vendor/bin/pint --dirty` após editar PHP
- PostgreSQL: usar tipos nativos (uuid, jsonb, timestamptz)
- Models com UUID: definir `$incrementing = false`, `$keyType = 'string'` e auto-gerar no `boot()` via `Str::uuid()`
- Envelope de resposta padrão: `{ "data": {...} }` via API Resource — nunca retornar array cru
- Plano padrão para novos tenants: `'starter'`

## Convenções do Frontend (Next.js)

- App Router (`app/` directory)
- Server Components por padrão; `"use client"` só quando necessário
- TypeScript estrito — proibido `any`
- Estilização apenas com Tailwind CSS + shadcn/ui
- API calls centralizadas em `src/services/` — nunca `fetch` direto nas páginas
- Auth: token Bearer no header via `src/lib/api.ts`
- Rotas protegidas com middleware Next.js
- `useState` lazy initializer para valores de localStorage: `useState(() => localStorage.getItem('token'))`
- Nunca chamar `setState` sincronamente no corpo do `useEffect` — regra `react-hooks/set-state-in-effect`; usar callbacks async (`.then`, `.finally`) ou lazy initializer
- Preços sempre em centavos na API; usar `formatPrice` de `src/lib/format.ts` para exibição

## Orquestração de Agentes

| Agente | Quando usar |
|---|---|
| `feature-orchestrator` | Feature completa — spawna api-agent + web-agent em paralelo |
| `api-agent` | Qualquer trabalho Laravel: migration, model, controller, teste |
| `web-agent` | Qualquer trabalho Next.js: page, component, service, hook |
| `db-agent` | Design de schema, índices, performance PostgreSQL |
| `tenant-guard` | Auditoria de isolamento multi-tenant |
| `payment-agent` | Integração MercadoPago (PIX, cartão, webhooks) |
| `github-agent` | Branch, PR, issue board — toda a gestão GitHub |

## Slash Commands

| Comando | O que faz |
|---|---|
| `/start-issue <N>` | Cria branch, move issue para In Progress, spawna feature-orchestrator, abre PR |
| `/sync-main` | Faz merge de main na branch atual e resolve conflitos simples |
| `/run-tests` | Roda Pest (API) e TypeScript + ESLint (web) e reporta resultado |
| `/build-feature <desc>` | Implementa feature completa end-to-end via feature-orchestrator |
| `/close-sprint` | Fecha sprint: resume issues, cria tag + release, move pendências |

## Fluxo de trabalho por issue

1. `/start-issue <N>` — inicia tudo automaticamente (branch, In Progress, implementação, PR)
2. Internamente: `github-agent` cria branch + move issue → `feature-orchestrator` spawna api-agent + web-agent em paralelo → `tenant-guard` audita → `github-agent` abre PR com `Closes #N`
3. Sempre atribuir o usuário como Assignee no PR

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
