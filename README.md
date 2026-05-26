# Sistema de Agendamentos

SaaS multi-tenant de agendamentos para salões de beleza. Cada salão é um tenant isolado com slug único, plano de assinatura e painel administrativo próprio.

## Stack

| Camada | Tecnologia |
|---|---|
| API | Laravel 13 · PHP 8.5 · PostgreSQL 16 |
| Autenticação | Laravel Sanctum (Bearer tokens) |
| Autorização | Spatie Laravel Permission |
| Pagamento | MercadoPago (PIX + cartão) |
| Frontend | Next.js 16 · TypeScript · Tailwind CSS · shadcn/ui |
| IA | Claude API (claude-sonnet-4-6) |
| Infra | Docker Compose (PostgreSQL + Redis) |

## Estrutura do Monorepo

```
sistema-agendamentos/
├── api/              # Laravel 13 — API REST versionada
├── web/              # Next.js 16 — Frontend SaaS
├── docker-compose.yml
└── .claude/
    ├── agents/       # Subagentes especializados (Claude Code)
    └── commands/     # Slash commands para desenvolvimento
```

## Funcionalidades

- **Multi-tenant** — cada salão tem dados completamente isolados por `tenant_id`
- **Agendamento online** — clientes agendam diretamente pelo link do salão
- **Gestão de serviços e pacotes** — admin cria serviços avulsos e pacotes de sessões
- **Profissionais e agenda** — disponibilidade por profissional e por dia da semana
- **Pagamentos** — PIX e cartão via MercadoPago, webhooks com verificação de assinatura
- **Dashboard** — gráficos de faturamento, ocupação e serviços mais agendados
- **IA** — assistente de agendamento e insights de negócio via Claude API
- **Super Admin** — gestão de tenants, planos e métricas da plataforma

## Roles

| Role | Escopo | Permissões |
|---|---|---|
| `super_admin` | Plataforma | Gerencia todos os tenants e planos |
| `salon_owner` | Tenant | Gerencia serviços, equipe, agenda e financeiro |
| `salon_staff` | Tenant | Visualiza agenda, confirma/cancela agendamentos |
| `client` | Tenant | Agenda serviços, histórico, pagamentos |

## Como Rodar

### Pré-requisitos
- PHP 8.5+, Composer 2+
- Node.js 20+, npm 10+
- Docker + Docker Compose

### 1. Infra (PostgreSQL + Redis)
```bash
docker compose up -d
```

### 2. API
```bash
cd api
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

### 3. Frontend
```bash
cd web
cp .env.local.example .env.local
npm install
npm run dev
```

A API ficará em `http://localhost:8000` e o frontend em `http://localhost:3000`.

## Variáveis de Ambiente

### api/.env
```env
DB_CONNECTION=pgsql
DB_DATABASE=agendamentos
DB_USERNAME=postgres
DB_PASSWORD=secret

MERCADOPAGO_ACCESS_TOKEN=
MERCADOPAGO_PUBLIC_KEY=
MERCADOPAGO_WEBHOOK_SECRET=

ANTHROPIC_API_KEY=
```

### web/.env.local
```env
NEXT_PUBLIC_API_URL=http://localhost:8000/api/v1
```

## Desenvolvimento com Agentes

Este projeto usa subagentes do Claude Code para desenvolvimento paralelo:

| Agente | Responsabilidade |
|---|---|
| `feature-orchestrator` | Coordena API + Web em paralelo para features completas |
| `api-agent` | Migrations, models, controllers, resources, testes Laravel |
| `web-agent` | Pages, components, services, hooks Next.js |
| `db-agent` | Schema design, índices, performance PostgreSQL |
| `tenant-guard` | Auditoria de isolamento multi-tenant |
| `payment-agent` | Integração MercadoPago (PIX, cartão, webhooks) |
| `ai-features-agent` | Features com Claude API |

### Slash commands disponíveis

```bash
/build-feature <descrição>   # feature completa (API + Web em paralelo)
/scaffold-api <descrição>    # só o lado Laravel
/scaffold-web <descrição>    # só o lado Next.js
/check-tenant                # auditoria de isolamento multi-tenant
```

## Testes

```bash
# API
cd api && php artisan test --compact

# Lint PHP
cd api && vendor/bin/pint --dirty
```

## Licença

MIT
