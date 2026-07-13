<img src="web/src/app/icon.svg" width="64" height="64" alt="Plana" />

# Plana

[![CI](https://github.com/joaopedroplinta/sistema-agendamentos/actions/workflows/ci.yml/badge.svg)](https://github.com/joaopedroplinta/sistema-agendamentos/actions/workflows/ci.yml)
[![Release](https://img.shields.io/github/v/release/joaopedroplinta/sistema-agendamentos)](https://github.com/joaopedroplinta/sistema-agendamentos/releases/latest)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](#licença)

Plataforma SaaS de agendamentos para salões de beleza. Cada salão opera como um tenant isolado — dados, usuários e configurações completamente separados entre si.

## Índice

- [Stack](#stack)
- [Funcionalidades](#funcionalidades)
- [Roles](#roles)
- [Regras de negócio](#regras-de-negócio)
- [Estrutura](#estrutura)
- [Rodando localmente](#rodando-localmente)
- [Variáveis de ambiente](#variáveis-de-ambiente)
- [Dados de demonstração](#dados-de-demonstração)
- [API](#api)
- [Testes](#testes)
- [Deploy em produção](#deploy-em-produção)
- [Licença](#licença)

## Stack

| Camada | Tecnologia |
|---|---|
| API | Laravel 13 · PHP 8.5 · PostgreSQL 16 |
| Auth | Laravel Sanctum (Bearer tokens stateless) |
| Permissões | Spatie Laravel Permission |
| Fila e cache | Redis 7 |
| Pagamentos | MercadoPago (PIX nativo + Checkout Pro / API Orders) |
| Frontend | Next.js 15 · TypeScript strict · Tailwind CSS · shadcn/ui |
| Infra | Docker · GitHub Actions CI/CD · Caddy (HTTPS automático) |

## Funcionalidades

- **Multi-tenant** — isolamento completo de dados por salão com `TenantScope` global
- **Catálogo** — serviços (com imagem), pacotes de sessões e profissionais com horários por dia da semana
- **Agendamento online** — fluxo em 5 etapas pelo link do salão; validação de disponibilidade em tempo real, sem double booking
- **Pagamentos** — PIX com QR Code e polling de status, cartão via Checkout Pro, ou pagamento no local
- **Pacotes de sessões** — cliente compra um pacote e consome sessões nos agendamentos, com devolução automática no cancelamento
- **Assinatura** — salon owner faz upgrade de plano (Starter/Pro/Enterprise) direto pelo painel, com downgrade automático se expirar
- **Equipe** — convite e remoção de staff por e-mail, promoção de cliente a staff
- **Dashboard** — métricas de receita, ocupação e top serviços com gráficos (Recharts)
- **Super Admin** — visão global da plataforma, gestão de tenants e planos
- **Notificações por e-mail** — confirmações, lembretes (~24h antes) e avisos ao owner, tudo enfileirado
- **Dark mode**

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
- Ciclo de vida: `pending` → `confirmed` → `completed`; `pending`/`confirmed` podem ser cancelados, remarcados ou marcados como falta (`no_show`, só staff e só após o horário). Transições inválidas retornam 422
- Remarcação valida o novo slot (sem conflitar consigo mesma), zera o lembrete e, feita pelo cliente, volta o status para `pending`
- Cliente cancela apenas os próprios agendamentos; owner/staff gerenciam todos os do salão

**Pagamento (MercadoPago)**
- PIX (QR Code + polling) ou cartão (Checkout Pro / API Orders); pagamento aprovado confirma o agendamento automaticamente via webhook
- O webhook responde 200 imediatamente e é processado em fila (`ProcessPaymentWebhook`, 3 tentativas com backoff)
- Não é possível pagar agendamento cancelado nem pagar duas vezes
- Também é possível pagar no local — o agendamento fica `pending` até o salão confirmar

**Planos e assinatura**
- Starter (grátis): 1 profissional ativo, 50 agendamentos/mês · Pro (R$ 97/mês): 5 profissionais · Enterprise (R$ 197/mês): ilimitado
- Limites aplicados na API (criação de profissional e de agendamento retornam 422 ao exceder); contagem de profissionais considera só os ativos
- Assinatura paga vale por 1 mês (`expires_at`); a aprovação via webhook atualiza o plano do tenant
- Assinatura expirada: o scheduler diário (`subscriptions:downgrade-expired`, 03:00) rebaixa o tenant para Starter e avisa o owner por e-mail. Planos concedidos manualmente pelo super admin (sem assinatura) não são rebaixados

**Pacotes de sessões**
- Cliente compra um `ServicePackage` (PIX ou cartão, reaproveitando a mesma integração MercadoPago/`PaymentService` do agendamento e da assinatura); a compra fica `pending` até o pagamento ser aprovado
- `sessions_total` e `price_paid` são copiados do pacote no momento da compra — editar o pacote depois não afeta compras já feitas
- Pagamento aprovado (webhook ou polling do PIX) ativa a compra: `status = active`, `purchased_at = now()`, `expires_at = purchased_at + valid_days` do pacote
- No agendamento, o cliente pode pagar com um pacote ativo em vez de dinheiro: exige pacote próprio, não expirado, com sessões livres e serviço incluído no pacote — validado com lock em transação (mesma proteção contra concorrência do agendamento) para não deixar duas reservas consumirem a última sessão. Agendamento pago com pacote sai com `price = 0`
- Cancelar um agendamento pago com pacote devolve a sessão (`sessions_used` nunca fica negativo); `no_show` não devolve

**Notificações por e-mail** (enfileiradas)
- Cliente: agendamento recebido, confirmado, cancelado, pagamento aprovado e lembrete ~24h antes do horário (scheduler de hora em hora, idempotente via `reminder_sent_at`)
- Owner: novo agendamento, agendamento cancelado, plano ativado e assinatura expirada

**Contas**
- Registro como dono cria o salão (slug único gerado do nome do salão); registro como cliente não cria salão e pode já vincular a um salão existente

## Estrutura

```
agendei/
├── api/                    # Laravel 13 — REST API (/api/v1/)
├── web/                    # Next.js 15 — Frontend
├── docker-compose.yml      # Postgres + Redis para desenvolvimento local
├── docker-compose.prod.yml # Stack completa de produção (ver Deploy)
└── Caddyfile                # Reverse proxy com HTTPS automático
```

## Rodando localmente

**Pré-requisitos:** Docker, PHP 8.5+, Composer 2+, Node.js 20+

```bash
# 1. Banco de dados e fila
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

> **Filas e agendador:** em dev, `QUEUE_CONNECTION=sync` roda tudo inline e os e-mails vão para
> `storage/logs/laravel.log` (`MAIL_MAILER=log`). Pra testar filas e o lembrete de agendamento de
> verdade, rode também `php artisan queue:work` e `php artisan schedule:work` — ou use
> `composer run dev`, que já sobe API, worker, logs (`pail`) e o frontend juntos.

## Variáveis de ambiente

**`api/.env`** (copie de `api/.env.example`)

| Variável | Obrigatória | Descrição |
|---|---|---|
| `APP_KEY` | sim | Gerada com `php artisan key:generate` |
| `APP_URL` | sim | URL pública da API |
| `FRONTEND_URL` | sim | URL do frontend — usada em CORS, e-mails e `back_urls` do MercadoPago |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | sim | Conexão PostgreSQL |
| `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD` | sim (se `QUEUE_CONNECTION=redis`) | Fila e cache |
| `QUEUE_CONNECTION` | sim | `sync` em dev, `redis` em produção |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` | sim em produção | `log` em dev (grava em arquivo); SMTP real em produção — sem isso nenhum e-mail transacional sai |
| `MERCADOPAGO_ACCESS_TOKEN` | sim | Credencial privada da integração de pagamento |
| `MERCADOPAGO_PUBLIC_KEY` | sim | Credencial pública, espelhada em `NEXT_PUBLIC_MERCADOPAGO_PUBLIC_KEY` no frontend |
| `MERCADOPAGO_WEBHOOK_SECRET` | **sim em produção** | Valida a assinatura HMAC do webhook — a API recusa se `APP_ENV=production` e essa variável estiver vazia |
| `SANCTUM_TOKEN_EXPIRATION` | não | Minutos até o token expirar (padrão 10080 = 7 dias) |
| `CORS_ALLOWED_ORIGINS` | sim | Lista separada por vírgula das origens do frontend permitidas |
| `AUTH_RATE_LIMIT_PER_MINUTE` | não | Limite de tentativas de login/registro por minuto (padrão 5) |

**`web/.env.local`** (copie de `web/.env.local.example`)

| Variável | Obrigatória | Descrição |
|---|---|---|
| `NEXT_PUBLIC_API_URL` | sim | Base da API, incluindo `/api/v1` |
| `NEXT_PUBLIC_MERCADOPAGO_PUBLIC_KEY` | sim | Não é segredo — usada pelo Card Payment Brick pra tokenizar o cartão no navegador |

> `NEXT_PUBLIC_*` é inlinada no bundle em **build time**, não em runtime — ao trocar de domínio é preciso rebuildar o frontend (ver [Deploy em produção](#deploy-em-produção)).

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

GET    /api/v1/negocio/{slug}
GET    /api/v1/negocio/{slug}/availability
GET    /api/v1/negocio/{slug}/services
GET    /api/v1/negocio/{slug}/packages
GET    /api/v1/negocio/{slug}/professionals

GET    /api/v1/negocio/{slug}/appointments
POST   /api/v1/negocio/{slug}/appointments
PATCH  /api/v1/negocio/{slug}/appointments/{id}/confirm
PATCH  /api/v1/negocio/{slug}/appointments/{id}/cancel
PATCH  /api/v1/negocio/{slug}/appointments/{id}/complete
PATCH  /api/v1/negocio/{slug}/appointments/{id}/no-show
PATCH  /api/v1/negocio/{slug}/appointments/{id}/reschedule

POST   /api/v1/negocio/{slug}/appointments/{id}/payments
GET    /api/v1/negocio/{slug}/payments/{id}
POST   /api/v1/payments/webhook

GET    /api/v1/negocio/{slug}/subscription
POST   /api/v1/negocio/{slug}/subscription

GET    /api/v1/negocio/{slug}/packages/{id}/purchases
POST   /api/v1/negocio/{slug}/packages/{id}/purchases

GET    /api/v1/negocio/{slug}/dashboard

GET    /api/v1/negocio/{slug}/team
POST   /api/v1/negocio/{slug}/team
DELETE /api/v1/negocio/{slug}/team/{userId}

GET    /api/v1/admin/metrics
GET    /api/v1/admin/tenants
PATCH  /api/v1/admin/tenants/{id}
```

## Testes

```bash
cd api && php artisan test --compact   # 227 testes Pest
cd web && npm run build                # TypeScript check
cd web && npm run lint                 # ESLint
cd web && npm run test:e2e             # Playwright (golden paths E2E)
```

## Deploy em produção

O projeto é empacotado como imagens Docker (`api/Dockerfile`, `web/Dockerfile`), publicadas
automaticamente no GitHub Container Registry a cada release (`.github/workflows/release.yml`),
e orquestradas em `docker-compose.prod.yml` com Postgres, Redis, worker de fila, agendador e
Caddy fazendo reverse proxy com HTTPS automático (Let's Encrypt).

```bash
cp .env.prod.example .env   # preencha domínios, banco, MercadoPago e SMTP reais
docker compose -f docker-compose.prod.yml up -d --build
```

Guia completo — incluindo como subir uma VM **always-free** na Oracle Cloud e apontar o
DNS — em [DEPLOY.md](DEPLOY.md).

## Licença

MIT
