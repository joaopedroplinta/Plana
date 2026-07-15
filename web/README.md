# Plana — Web

Frontend do [Plana](../README.md), SaaS multi-tenant de agendamentos. Este
README cobre só o essencial pra rodar esta pasta — visão geral do produto e
regras de negócio estão no [README da raiz](../README.md); convenções de
código em [`CLAUDE.md`](../CLAUDE.md) e
[`.claude/rules/web-conventions.md`](../.claude/rules/web-conventions.md).

## Stack

Next.js 15 (App Router) · TypeScript estrito · Tailwind CSS · shadcn/ui · Axios

## Rodando localmente

```bash
cp .env.local.example .env.local   # aponte NEXT_PUBLIC_API_URL pra API local
npm install
npm run dev   # http://localhost:3000
```

Precisa da API rodando em paralelo — ver [`api/README.md`](../api/README.md).

## Comandos essenciais

```bash
npm run build   # build de produção — também serve de type-check do TypeScript
npm run lint    # ESLint
npm run test:e2e   # Playwright (golden paths E2E), precisa da stack completa de pé
npx shadcn@latest add <component>   # adicionar um novo componente shadcn/ui
```

## Deploy

`NEXT_PUBLIC_*` é inlinada em **build time**, não runtime — ao trocar a URL
da API é preciso rebuildar a imagem, não só reiniciar o container. Ver
[`DEPLOY.md`](../DEPLOY.md) na raiz para as opções de produção.
