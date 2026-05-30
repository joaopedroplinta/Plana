# Sistema de Agendamentos

[![CI](https://github.com/joaopedroplinta/sistema-agendamentos/actions/workflows/ci.yml/badge.svg)](https://github.com/joaopedroplinta/sistema-agendamentos/actions/workflows/ci.yml)

SaaS multi-tenant de agendamentos para salões de beleza. Cada salão é um tenant isolado com slug único, plano de assinatura e painel administrativo próprio.

## Stack

| Camada | Tecnologia |
|---|---|
| API | Laravel 13 · PHP 8.5 · PostgreSQL 16 |
| Autenticação | Laravel Sanctum (Bearer tokens stateless) |
| Autorização | Spatie Laravel Permission |
| Pagamento | MercadoPago SDK (PIX nativo + Checkout Pro) |
| Frontend | Next.js 16 · TypeScript strict · Tailwind CSS · shadcn/ui |
| Gráficos | Recharts |
| Infra | Docker Compose (PostgreSQL + Redis) |
| CI | GitHub Actions (PHP lint + Pest · TypeScript + ESLint) |

## Funcionalidades

- **Multi-tenant** — dados completamente isolados por `tenant_id` com `TenantScope` global
- **Catálogo** — CRUD de serviços (com upload de imagem), pacotes e profissionais
- **Agenda** — disponibilidade por profissional, dia da semana e datas bloqueadas
- **Agendamento online** — clientes agendam pelo link do salão em 5 etapas
- **Pagamentos** — PIX com QR Code e polling de status; cartão via Checkout Pro (redirect)
- **Dashboard** — gráficos de receita diária, ocupação por status e top serviços (Recharts)
- **Super Admin** — métricas globais da plataforma, gestão de tenants e planos

## Roles

| Role | Escopo | Permissões |
|---|---|---|
| `super_admin` | Plataforma | Métricas globais, gestão de todos os tenants e planos |
| `salon_owner` | Tenant | Gerencia serviços, equipe, agenda e financeiro |
| `salon_staff` | Tenant | Visualiza agenda, confirma/cancela agendamentos |
| `client` | Tenant | Agenda serviços, paga, visualiza histórico |

## Estrutura

```
sistema-agendamentos/
├── api/              # Laravel 13 — API REST /api/v1/
├── web/              # Next.js 16 — Frontend
└── docker-compose.yml
```

## Como Rodar

### Pré-requisitos

- Docker + Docker Compose
- PHP 8.5+ e Composer 2+
- Node.js 20+ e npm 10+

### 1. Banco de dados

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

O seed cria o tenant demo e um super admin (ver credenciais abaixo).

### 3. Frontend

```bash
cd web
cp .env.local.example .env.local   # ou crie manualmente
npm install
npm run dev
```

API em `http://localhost:8000` · Frontend em `http://localhost:3000`

## Variáveis de Ambiente

### `api/.env` (campos relevantes)

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=agendamentos
DB_USERNAME=postgres
DB_PASSWORD=secret

MERCADOPAGO_ACCESS_TOKEN=your_access_token
MERCADOPAGO_PUBLIC_KEY=your_public_key
MERCADOPAGO_WEBHOOK_SECRET=     # opcional — só valida HMAC se preenchido
```

### `web/.env.local`

```env
NEXT_PUBLIC_API_URL=http://localhost:8000/api/v1
```

## Dados de Demonstração

O seeder (`php artisan migrate --seed`) cria:

**Tenant demo**
- URL: `http://localhost:3000/salao-demo`
- Dashboard: `http://localhost:3000/salao-demo/dashboard`

**Usuário salon_owner**
- Email: `owner@salao-demo.com.br`
- Senha: `password`

**Super Admin**
- URL: `http://localhost:3000/super-admin`
- Email: `admin@agendei.com`
- Senha: `password`

O tenant demo vem com 8 serviços, 3 profissionais com horários de segunda a sábado (09h–18h) e 2 pacotes de serviços.

## Rotas da API

```
POST   /api/v1/auth/register
POST   /api/v1/auth/login
POST   /api/v1/auth/logout
GET    /api/v1/auth/me

GET    /api/v1/salao/{slug}
GET    /api/v1/salao/{slug}/availability
GET    /api/v1/salao/{slug}/services
GET    /api/v1/salao/{slug}/packages
GET    /api/v1/salao/{slug}/professionals

GET    /api/v1/salao/{slug}/appointments
POST   /api/v1/salao/{slug}/appointments
PATCH  /api/v1/salao/{slug}/appointments/{id}/confirm
PATCH  /api/v1/salao/{slug}/appointments/{id}/cancel
PATCH  /api/v1/salao/{slug}/appointments/{id}/complete

POST   /api/v1/salao/{slug}/appointments/{id}/payments
GET    /api/v1/salao/{slug}/payments/{id}
POST   /api/v1/payments/webhook

GET    /api/v1/salao/{slug}/dashboard

GET    /api/v1/admin/metrics
GET    /api/v1/admin/tenants
PATCH  /api/v1/admin/tenants/{id}
```

Documentação manual em `api/docs/api.http` (VS Code REST Client).

## Testes

```bash
# API — 90 testes Pest
cd api && php artisan test --compact

# Lint PHP
cd api && vendor/bin/pint --dirty

# Frontend — TypeScript + ESLint
cd web && npm run build
cd web && npm run lint
```

## Licença

MIT
