# Changelog

Todas as mudanças relevantes deste projeto são documentadas aqui.

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
