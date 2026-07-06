# Agendei

[![CI](https://github.com/joaopedroplinta/sistema-agendamentos/actions/workflows/ci.yml/badge.svg)](https://github.com/joaopedroplinta/sistema-agendamentos/actions/workflows/ci.yml)

Plataforma SaaS de agendamentos para salões de beleza. Cada salão opera como um tenant isolado — dados, usuários e configurações completamente separados entre si.

## Stack

| Camada | Tecnologia |
|---|---|
| API | Laravel 13 · PHP 8.5 · PostgreSQL 16 |
| Auth | Laravel Sanctum (Bearer tokens stateless) |
| Permissões | Spatie Laravel Permission |
| Pagamentos | MercadoPago (PIX nativo + Checkout Pro) |
| Frontend | Next.js 15 · TypeScript strict · Tailwind CSS · shadcn/ui |
| Infra | Docker Compose · GitHub Actions CI |

## Funcionalidades

- **Multi-tenant** — isolamento completo de dados por salão com `TenantScope` global
- **Catálogo** — serviços (com imagem), pacotes e profissionais com horários por dia da semana
- **Agendamento online** — fluxo em 5 etapas pelo link do salão; validação de disponibilidade em tempo real
- **Pagamentos** — PIX com QR Code e polling de status; cartão via Checkout Pro
- **Assinatura** — salon owner faz upgrade de plano (Starter/Pro/Enterprise) direto pelo painel
- **Dashboard** — métricas de receita, ocupação e top serviços com gráficos (Recharts)
- **Super Admin** — visão global da plataforma, gestão de tenants e planos

## Roles

| Role | Acesso |
|---|---|
| `super_admin` | Métricas globais, todos os tenants e planos |
| `salon_owner` | Serviços, equipe, agenda, financeiro e assinatura do próprio salão |
| `salon_staff` | Visualiza agenda, confirma e cancela agendamentos |
| `client` | Agenda serviços, paga e consulta histórico |

## Estrutura

```
agendei/
├── api/              # Laravel 13 — REST API (/api/v1/)
├── web/              # Next.js 15 — Frontend
└── docker-compose.yml
```

## Rodando localmente

**Pré-requisitos:** Docker, PHP 8.5+, Composer 2+, Node.js 20+

```bash
# 1. Banco de dados
docker compose up -d

# 2. API
cd api
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve        # http://localhost:8000

# 3. Frontend
cd web
cp .env.local.example .env.local
npm install
npm run dev              # http://localhost:3000
```

## Variáveis de ambiente

**`api/.env`**
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=agendamentos
DB_USERNAME=postgres
DB_PASSWORD=secret

MERCADOPAGO_ACCESS_TOKEN=your_access_token
MERCADOPAGO_WEBHOOK_SECRET=        # opcional — valida HMAC do webhook
```

**`web/.env.local`**
```env
NEXT_PUBLIC_API_URL=http://localhost:8000/api/v1
```

## Dados de demonstração

`php artisan migrate --seed` cria:

| Perfil | URL | Email | Senha |
|---|---|---|---|
| Salon Owner | `localhost:3000/salao-demo/dashboard` | `owner@salao-demo.com.br` | `password` |
| Super Admin | `localhost:3000/super-admin` | `admin@agendei.com` | `password` |

O tenant demo inclui 8 serviços, 3 profissionais com horários de segunda a sábado (09h–18h) e 2 pacotes.

## API

```
POST   /api/v1/auth/register
POST   /api/v1/auth/login
POST   /api/v1/auth/logout
GET    /api/v1/auth/me
POST   /api/v1/auth/forgot-password
POST   /api/v1/auth/reset-password

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

GET    /api/v1/salao/{slug}/subscription
POST   /api/v1/salao/{slug}/subscription

GET    /api/v1/salao/{slug}/dashboard

GET    /api/v1/admin/metrics
GET    /api/v1/admin/tenants
PATCH  /api/v1/admin/tenants/{id}
```

## Testes

```bash
cd api && php artisan test --compact   # 99 testes Pest
cd web && npm run build                # TypeScript check
cd web && npm run lint                 # ESLint
```

## Licença

MIT
