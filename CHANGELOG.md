# Changelog

Todas as mudanças relevantes deste projeto são documentadas aqui.

---

## [v1.0.0] — Lançamento (2026-07-12)

Fechamento da auditoria de hardening pré-lançamento (#40–#61): correções de
segurança, produto novo (equipe, pacotes de sessões, reagendamento), testes
E2E e empacotamento Docker para deploy.

### Marca
- Rebrand de "Agendei" para **Plana** — o nome anterior já era usado por
  múltiplos produtos ativos no mesmo mercado (#70)
- Novo ícone/logo (marca de barras teal/lima) aplicado como favicon, app icon
  e componente reutilizável no header, footer e sidebar do super-admin (#70)
- Repositório tornado público
- Primeira etapa de generalização pra vários ramos de negócio (não só salão
  de beleza): rota da API `/api/v1/salao/{slug}/` → `/api/v1/negocio/{slug}/`
  e toda a copy voltada ao usuário (landing page, dashboard, e-mails,
  mensagens de validação) trocada de "salão" para "negócio" (#75).
  Identificadores internos (roles `salon_owner`/`salon_staff`, campo
  `salon_name`) ficam para uma refatoração completa posterior

### Segurança
- Fix de vazamento cross-tenant: `SubscriptionController` e gate do dashboard
  usavam role Spatie global em vez do papel por tenant (`ownsTenant()`) (#47, #50)
- `Professional.user_id` passou a ser validado escopado ao tenant atual (#55)
- Rate limiting (5/min auth, 60/min API), expiração de token Sanctum (7 dias),
  `MERCADOPAGO_WEBHOOK_SECRET` obrigatório em produção, CORS restrito (#41)

### Produto
- Gestão de equipe: convite e remoção de staff, promoção client → staff (#42)
- Perfil público do salão com serviços e contatos (#43)
- Lembrete de agendamento por e-mail ~24h antes, idempotente (#44)
- Reagendamento (mantém status pro staff, volta a `pending` pro cliente) e
  registro de falta (`no_show`, só staff, só após o horário) (#45)
- Traduções pt_BR do framework — e-mails, reset de senha, validação (#46)
- Toasts (sonner) e `AlertDialog` para confirmações destrutivas no frontend (#49)
- Máquina de estados centralizada para status de `Appointment` (enum nativo +
  CHECK constraint) (#48)
- Regras de slot unificadas entre disponibilidade e agendamento — corrige bug
  de reagendamento não ignorar o próprio slot no conflito (#51)
- Limites de plano centralizados em `SubscriptionService` (#52)
- **Compra de pacotes de sessões**: schema novo, consumo de sessão no
  agendamento com lock (sem condição de corrida), devolução no cancelamento,
  pagamento via PIX/cartão reaproveitando o `PaymentService` (#53, #56)
- Dark mode funcional e footer em todos os layouts (#58)
- Fix de horário de agendamento inconsistente entre telas (timezone) (#60)
- Fix de bloqueio de `/login` por cookie órfão + logout no cliente (#59)
- Limite de profissionais do plano conta apenas ativos (#61)
- Migração da integração MercadoPago de `/v1/payments` para a API Orders (#57)

### Testes e infra
- Suite E2E com Playwright (registro/login, booking + cancelamento, staff
  confirma, reagendamento) e job `e2e` no CI — encontrou e corrigiu 2 bugs
  reais de frontend (redirect de login quebrado, gate de `/super-admin`
  travado) (#54)
- Empacotamento Docker (`api/Dockerfile`, `web/Dockerfile`,
  `docker-compose.prod.yml` + Caddy com HTTPS automático) e workflow de
  release publicando imagens no GHCR
- Job de `docker build` (sem push) no CI, rodando em todo PR/push pra main —
  pega quebra no Dockerfile antes do release, não só na hora de publicar
  uma tag (#74)
- Segunda opção de deploy gratuito: `render.yaml` (Blueprint) pra Render +
  Neon, sem exigir VM nem domínio próprio. Endpoint
  `POST /api/v1/system/scheduler` (protegido por `SCHEDULER_TOKEN`) permite
  disparar o scheduler via cron externo em ambientes sem worker/cron
  persistente (#77)
- Suite final: 230 testes Pest + 4 specs E2E, todos passando

---

## [v0.7.0] — Sprint 8: Super Admin (2026-05-30)

### API
- Rotas `/api/v1/admin/*` protegidas por `role:super_admin` (middleware Spatie registrado)
- `GET /admin/metrics` — métricas globais da plataforma (tenants, usuários, agendamentos, receita total)
- `GET /admin/tenants` — listagem paginada de todos os salões com `user_count` e `owner`
- `GET /admin/tenants/{id}` — detalhes do salão com owner (nome + email)
- `PATCH /admin/tenants/{id}` — atualização de plano (starter/pro/enterprise) e status (active)
- `AdminTenantResource` com `whenLoaded` para `user_count` e `owner`
- 9 testes Pest cobrindo acesso super_admin, salon_owner (403) e unauthenticated (401)

### Web
- Layout do Super Admin reescrito com sidebar escura (Agendei ADMIN badge), auth guard e logout
- Guard de role `super_admin` — redireciona para `/` se não autorizado
- `/super-admin` — dashboard com 4 cards de métricas reais e tabela de distribuição por plano
- `/super-admin/tenants` — tabela paginada com dropdown de plano inline e toggle ativo/inativo
- `adminService` em `src/services/admin.ts`
- Tipos `AdminTenant`, `AdminTenantOwner` e `AdminMetrics` em `src/types/index.ts`

---

## [v0.6.0] — Sprint 6: Dashboard (2026-05-30)

### API
- Endpoint `GET /api/v1/salao/{slug}/dashboard?period=30` (invokable `DashboardController`)
- Retorna: 6 métricas resumo, agendamentos por status, receita por dia, top 5 serviços, top 5 profissionais
- Gate `viewDashboard` — acesso restrito a `salon_owner` e `salon_staff`; `client` recebe 403
- Parâmetro `period` com máximo de 90 dias
- 6 testes Pest cobrindo roles, estrutura de resposta e cálculo de receita

### Web
- Dashboard reescrito com **Recharts** (LineChart, PieChart, BarChart horizontal)
- Seletor de período (7/30/90 dias) com re-fetch automático
- 4 cards de resumo com skeleton de loading
- Tabela de top profissionais com receita formatada
- `metricsService` em `src/services/metrics.ts`
- Tipos `DashboardSummary` e `DashboardMetrics` em `src/types/index.ts`

---

## [v0.5.0] — Sprint 5: Pagamentos (2026-05-30)

### API
- Integração **MercadoPago SDK** (PIX nativo + Checkout Pro)
- `POST /salao/{slug}/appointments/{id}/payments` — cria pagamento PIX ou cartão
- `GET /salao/{slug}/payments/{id}` — consulta status com sync automático
- `POST /v1/payments/webhook` — webhook MercadoPago com verificação HMAC condicional
- `PaymentService` com `createPix`, `createCheckoutPro`, `syncStatus`, `handleWebhook`
- Confirmação automática do agendamento ao receber pagamento aprovado

### Web
- Fluxo de agendamento estendido com etapa de pagamento (PIX ou cartão)
- QR Code PIX exibido com polling a cada 5s via `setInterval`
- Redirecionamento para URL do Checkout Pro no fluxo de cartão
- Página `/payment-success` para retorno pós-pagamento
- `paymentsService` em `src/services/payments.ts`

---

## [v0.4.0] — Sprint 4: Agendamentos (2026-05-30)

### API
- CRUD de **agendamentos** (`/salao/{slug}/appointments`)
- `PATCH confirm`, `cancel`, `complete` — transições de status com policy por role
- `GET /salao/{slug}/availability` — slots livres por profissional, serviço e data
- Fix de isolamento cross-tenant no `TenantScope` (fallback via `request()->route('tenant')`)
- Senha recuperação: `POST /auth/forgot-password` e `POST /auth/reset-password`
- Seeder demo: tenant `salao-demo` com 8 serviços, 3 profissionais e pacotes

### Web
- Fluxo de agendamento em 5 etapas: serviço → profissional → data → horário → confirmação
- Página `/forgot-password` funcional e `/reset-password` com token via query string
- Guard de middleware corrigido para Next.js 16 (`proxy.ts` com export `proxy`)
- Fix ESLint `react-hooks/set-state-in-effect` no hook `useTenant`

---

## [v0.3.0] — Sprint 3: Catálogo (2026-05-27)

### API
- CRUD completo de **serviços** com upload de imagem (`/salao/{slug}/services`)
- CRUD de **pacotes de serviços** com vinculação de serviços (`/salao/{slug}/packages`)
- CRUD de **profissionais**, horários por dia da semana e datas bloqueadas
- Policies com dupla verificação de tenant (`$resource->tenant_id === $currentTenant->id`)
- `service_ids` validados contra o tenant atual (`Rule::exists` scoped)
- 47 testes Pest cobrindo CRUD, roles e isolamento cross-tenant

### Web
- Layout do dashboard admin com sidebar (Serviços, Pacotes, Profissionais, Agenda)
- Guard de autenticação com redirect para `/login`
- Páginas de CRUD com tabela, modal de criação/edição e confirmação de exclusão
- Preços em centavos na API; exibição formatada em reais com `formatPrice`

---

## [v0.2.0] — Sprint 2: Auth (2026-05-27)

### API
- `POST /api/v1/auth/register` — cria Tenant + User com role `salon_owner`, retorna token
- `POST /api/v1/auth/login` — aceita `tenant_slug` opcional para resolução determinística
- `POST /api/v1/auth/logout` — revoga token Sanctum
- `GET /api/v1/auth/me` — retorna usuário autenticado com tenant
- Slug do tenant gerado automaticamente com sufixo incremental para unicidade
- 14 testes Pest cobrindo todos os endpoints e edge cases

### Web
- Página `/login` com formulário real e redirect para `/{slug}/dashboard`
- Página `/register` com validação por campo e redirect pós-cadastro
- Página `/forgot-password` (placeholder UI)
- `AuthResponse` type com envelope `{ data: { token, user, tenant } }`

---

## [v0.1.0] — Sprint 1: Foundation (2026-05-26)

### Infra
- Monorepo `api/` (Laravel 13) + `web/` (Next.js 15)
- Docker Compose com PostgreSQL 16 e Redis 7
- GitHub Actions CI com jobs paralelos: PHP 8.5 + Pest e TypeScript + ESLint

### API
- Multi-tenant: model `Tenant`, trait `BelongsToTenant`, `TenantScope`, middleware `ResolveTenant`
- Tabela pivot `tenant_user` com role
- Rotas versionadas `/api/v1/salao/{tenant:slug}/`
- 4 testes Pest de isolamento de tenant

### Web
- App Router com route groups `(public)/`, `(salon)/[slug]/`, `(super-admin)/`
- Hook `useAuth` com lazy init de token
- Hook `useTenant` com resolução por slug
- `src/lib/api.ts` com interceptor Bearer
- Landing page pública
