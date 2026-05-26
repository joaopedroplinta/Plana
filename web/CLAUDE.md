# Web — CLAUDE.md

Next.js 15 frontend para o SaaS de agendamentos. Leia o CLAUDE.md raiz para contexto completo do projeto.

## Stack

- Next.js 15, App Router, TypeScript strict
- Tailwind CSS + shadcn/ui
- Axios via `src/lib/api.ts`

## Comandos essenciais

```bash
npm run dev          # dev server (porta 3000)
npm run build        # build de produção (verifica TypeScript)
npm run lint         # ESLint
npx shadcn@latest add <component>   # adicionar componente shadcn
```

## Estrutura de pastas

```
src/
├── app/
│   ├── (public)/              # Landing, login, register, pricing
│   ├── (salon)/[slug]/        # Páginas scoped ao salão
│   │   ├── page.tsx           # Home do salão (público)
│   │   ├── booking/           # Fluxo de agendamento
│   │   ├── dashboard/         # Admin do salão (salon_owner/staff)
│   │   └── minha-conta/       # Conta do cliente
│   └── (super-admin)/         # Painel da plataforma
├── components/
│   ├── ui/                    # shadcn/ui (gerado, não editar)
│   └── shared/                # Componentes do projeto
├── services/                  # Chamadas à API
├── hooks/                     # Hooks customizados
├── lib/
│   ├── api.ts                 # Instância Axios configurada
│   └── format.ts              # formatPrice, formatDate, formatDuration
└── types/
    └── index.ts               # Tipos TypeScript globais
```

## Regras absolutas

- Proibido `any` — use `unknown` com type guard se necessário
- Proibido `fetch` direto — sempre usar `src/services/`
- Proibido estilos inline — Tailwind classes apenas
- Server Components por padrão; `'use client'` só para interatividade

## API

Base URL: `process.env.NEXT_PUBLIC_API_URL` (ex: `http://127.0.0.1:8000/api/v1`)
Auth: Bearer token no header `Authorization` via interceptor em `src/lib/api.ts`
