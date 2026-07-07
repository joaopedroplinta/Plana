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

> O papel dentro de um salão vem do vínculo `tenant_user.role` (`owner`/`staff`/`client`) — um usuário pode ser dono do salão A e cliente do salão B ao mesmo tempo.

## Regras de negócio

**Agendamento**
- Só é possível agendar dentro do expediente do profissional (`schedules` por dia da semana), em data não bloqueada e em horário livre — o conflito é verificado com lock em transação (sem double booking)
- Qualquer usuário autenticado pode agendar em qualquer salão; no primeiro agendamento ele é vinculado como `client` daquele tenant
- Ciclo de vida: `pending` → `confirmed` → `completed`; `pending`/`confirmed` podem ser cancelados. Transições inválidas retornam 422
- Cliente cancela apenas os próprios agendamentos; owner/staff gerenciam todos os do salão

**Pagamento (MercadoPago)**
- PIX (QR Code + polling) ou cartão (Checkout Pro); pagamento aprovado confirma o agendamento automaticamente via webhook
- O webhook responde 200 imediatamente e é processado em fila (`ProcessPaymentWebhook`, 3 tentativas com backoff)
- Não é possível pagar agendamento cancelado nem pagar duas vezes
- Também é possível pagar no local — o agendamento fica `pending` até o salão confirmar

**Planos e assinatura**
- Starter (grátis): 1 profissional, 50 agendamentos/mês · Pro (R$ 97/mês): 5 profissionais · Enterprise (R$ 197/mês): ilimitado
- Limites aplicados na API (criação de profissional e de agendamento retornam 422 ao exceder)
- Assinatura paga vale por 1 mês (`expires_at`); a aprovação via webhook atualiza o plano do tenant
- Assinatura expirada: o scheduler diário (`subscriptions:downgrade-expired`, 03:00) rebaixa o tenant para Starter e avisa o owner por e-mail. Planos concedidos manualmente pelo super admin (sem assinatura) não são rebaixados

**Notificações por e-mail** (enfileiradas)
- Cliente: agendamento recebido, confirmado, cancelado, pagamento aprovado e lembrete ~24h antes do horário (scheduler de hora em hora, idempotente via `reminder_sent_at`)
- Owner: novo agendamento, agendamento cancelado, plano ativado e assinatura expirada

**Contas**
- Registro como dono cria o salão (slug único gerado do nome do salão); registro como cliente não cria salão e pode já vincular a um salão existente

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

> **Produção:** rode também um worker de fila (`php artisan queue:work`) para webhooks e e-mails,
> e o scheduler (`php artisan schedule:work` ou cron de `schedule:run`) para o downgrade de assinaturas expiradas.
> Em dev com `QUEUE_CONNECTION=sync` tudo roda inline e os e-mails vão para `storage/logs/laravel.log` (`MAIL_MAILER=log`).

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

GET    /api/v1/salao/{slug}/team
POST   /api/v1/salao/{slug}/team
DELETE /api/v1/salao/{slug}/team/{userId}

GET    /api/v1/admin/metrics
GET    /api/v1/admin/tenants
PATCH  /api/v1/admin/tenants/{id}
```

## Testes

```bash
cd api && php artisan test --compact   # 156 testes Pest
cd web && npm run build                # TypeScript check
cd web && npm run lint                 # ESLint
```

## Licença

MIT
