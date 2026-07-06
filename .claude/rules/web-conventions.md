---
description: Convenções Next.js/TypeScript para o frontend
paths:
  - "web/**"
---

## TypeScript

- Proibido `any` — usar `unknown` + type guard se necessário
- Tipos globais em `src/types/index.ts`
- Props sempre tipadas com `interface`, nunca inline

## Componentes

Server Components por padrão. `'use client'` apenas quando necessário (interatividade, hooks, browser APIs):

```tsx
// Server Component (padrão — sem "use client")
export default async function ServicesPage({
  params,
}: {
  params: Promise<{ slug: string }>
}) {
  const { slug } = await params
  const { data } = await servicesService.list(slug)
  return <ServiceList services={data.data} />
}
```

## API Calls

Nunca usar `fetch` direto — sempre via `src/services/`:

```typescript
// src/services/services.ts
import { api } from '@/lib/api'

export const servicesService = {
  list: (slug: string) =>
    api.get<PaginatedResponse<Service>>(`/salao/${slug}/services`),
  create: (slug: string, data: CreateServiceData) =>
    api.post<{ data: Service }>(`/salao/${slug}/services`, data),
}
```

## Hooks — Armadilhas Comuns

```typescript
// ✅ Correto: lazy initializer para localStorage
const [token, setToken] = useState(() => localStorage.getItem('token'))

// ❌ Errado: setState síncrono no corpo do useEffect (viola react-hooks/set-state-in-effect)
useEffect(() => {
  setToken(localStorage.getItem('token')) // NUNCA
}, [])

// ✅ Correto: usar callback async dentro do useEffect
useEffect(() => {
  fetchData().then((result) => setData(result))
}, [])
```

## Formatação

Sempre usar `src/lib/format.ts` — nunca formatar inline:

```typescript
formatPrice(1500)       // → "R$ 15,00"
formatDate('2025-01-15') // → "15 jan. 2025"
formatDuration(90)      // → "1h 30min"
```

Preços vêm da API em centavos. Exibir sempre via `formatPrice`.

## Estilização

- Apenas Tailwind CSS + shadcn/ui — zero `style={}` inline
- Adicionar componentes shadcn via `cd web && npx shadcn@latest add <component>`

## Verificação Final

```bash
cd web && npm run build  # sem erros TypeScript
cd web && npm run lint   # sem erros ESLint
```
