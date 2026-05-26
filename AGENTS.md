# Sistema de Agendamentos — AGENTS.md

## Project Overview

Multi-tenant SaaS scheduling platform for salons. Each salon is an isolated **tenant** identified by a unique slug. Built as a monorepo with `api/` (Laravel 13) and `web/` (Next.js 15).

## Architecture

```
api/   → Laravel 13 · PHP 8.5 · PostgreSQL · Sanctum · Spatie Permission · MercadoPago
web/   → Next.js 15 · TypeScript · Tailwind CSS · shadcn/ui · Claude API
```

## Critical Multi-Tenancy Rule

Every salon-scoped resource MUST carry `tenant_id`. Never leak data across tenants.
- Use the `BelongsToTenant` trait on models
- Apply `ResolveTenant` middleware on all tenant routes
- Always filter queries by `tenant_id` — never trust user-supplied tenant context without verification

## Roles

`super_admin` (platform) · `salon_owner` (tenant) · `salon_staff` (tenant) · `client` (tenant)

## API Conventions

- Versioned routes: `/api/v1/`
- Eloquent API Resources for all responses
- Form Requests for validation
- Pest 4 feature tests for every new endpoint
- Run `vendor/bin/pint --dirty` after editing PHP

## Frontend Conventions

- Next.js App Router — Server Components by default
- TypeScript strict mode — no `any`
- All API calls via `src/services/` layer
- Tailwind CSS + shadcn/ui only for styling

## Payment

MercadoPago SDK. PIX and credit card. Webhooks with signature verification. Idempotency keys on all payment requests.

## AI Features

Claude API (claude-sonnet-4-6) for: scheduling assistant, business insights for salon owners, automated client communication suggestions.
