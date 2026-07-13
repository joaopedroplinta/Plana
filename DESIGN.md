# Design — spec de refresh visual do Plana

> Documento de planejamento original. Implementado na branch
> `design/visual-refresh-tokens` (2026-07-13) — os 5 passos do "Plano de
> execução" abaixo foram concluídos e verificados visualmente (Playwright,
> light/dark, logado como salon_owner e super_admin) e o contraste
> WCAG AA de todos os pares de token foi validado (todos ≥ 4.5:1). Mantido
> aqui como referência da direção original.

## Por que isso importa agora

O produto já tem uma marca real (o logo teal/lima que desenhamos e o nome
"Plana"), mas a UI inteira ainda não sabe disso. Hoje, quem abre o app vê
um Next.js + shadcn de boilerplate com um logo colado em cima — não uma
marca coerente. Isso é o tipo de coisa que faz um produto parecer um MVP de
fim de semana mesmo depois de meses de trabalho de verdade em segurança,
multi-tenancy, pagamentos e infra.

## Diagnóstico — o que existe hoje

Auditoria do código em `web/src/app/globals.css`, `web/src/components/ui/`,
`web/src/app/(public)/page.tsx` e `web/src/app/(public)/login/page.tsx`:

1. **`globals.css` é o tema padrão do shadcn, sem edição.** Todos os tokens
   de cor (`--primary`, `--accent`, `--muted`, `--border`...) são
   `oklch(... 0 0)` — croma **zero**, ou seja, cinza puro. O botão padrão
   (`<Button>` sem variant) renderiza preto/branco, não na cor da marca.
2. **A cor "de marca" que aparece (indigo-600) não é um token** — é
   `text-indigo-600` / `bg-indigo-600` do Tailwind, hardcoded direto em
   `page.tsx` e `login/page.tsx`, sem nenhuma relação com o teal/lima real
   do logo. Duas identidades visuais coexistindo sem se falar.
3. **Tipografia é Geist Sans + Geist Mono** — literalmente as fontes
   padrão do `create-next-app`. Zero decisão de tipografia foi tomada.
4. **Ícones de feature são emoji** (📅 ✂️ 📊) — funciona como placeholder,
   mas lê como "ainda não terminei o design", não como escolha.
5. **`--radius: 0.625rem` fixo em tudo** — sem escala pensada, herdado do
   shadcn.
6. Resultado: qualquer pessoa que já viu um projeto shadcn+Tailwind
   reconhece a estrutura instantaneamente. Nada aqui é *do* Plana.

## Direção proposta

### Princípio geral

Plana já tem duas cores reais (teal `#006768`, lima `#9ad335`) escolhidas
com intenção (ver a sessão de design do logo). A direção certa não é
inventar uma paleta nova — é **fazer o resto da UI admitir que essa marca
existe**: os tokens do shadcn passam a *ser* o teal/lima, não uma cor cinza
com um indigo solto por cima.

Tema tonal: profissional, calmo, "organizado" — teal remete a
confiabilidade/saúde/bem-estar (funciona pra salão, clínica, estúdio,
personal trainer, sem parecer nicho de beleza), lima dá o contraste de
energia ("horário livre", confirmação, sucesso).

### Cores — tokens nomeados

```css
/* Light */
--teal-900: #003d3d;   /* texto de alto contraste, headers escuros */
--teal-700: #00524f;   /* hover de primary */
--teal-600: #006768;   /* primary — botões, links, foco */
--teal-100: #dcefec;   /* tints — badges, fundos sutis, hover leve */
--lima-500: #9ad335;   /* accent — "disponível", sucesso, destaque raro */
--lima-100: #eef7dc;   /* tint do accent — fundo de badge de sucesso */
--neutral-bg: #faf9f6;   /* fundo base — off-white com leve viés quente, não branco puro */
--neutral-border: #e4e1da; /* bordas — cinza com leve viés quente, não cinza puro */
--neutral-text: #1c1f1e;   /* texto padrão — quase preto, leve viés teal */
--neutral-muted: #6b7370;  /* texto secundário */

/* Dark (já existe dark mode funcional — só precisa herdar os mesmos tokens) */
--teal-900-dark: #eef7f6;  /* inverte pra texto claro */
--teal-600-dark: #3ecfcf;  /* teal mais claro/saturado pra contraste em fundo escuro */
--bg-dark: #0d1210;
--surface-dark: #131916;
--border-dark: #212824;
```

Regra de aplicação: `--primary` do shadcn = `--teal-600` (`--teal-600-dark`
no dark mode), `--accent` reservado só pra estados "positivo/disponível"
(nunca decorativo), `--destructive` continua vermelho padrão (não mexer —
já está correto semanticamente). Isso elimina o indigo-600 solto: todo
`text-indigo-600`/`bg-indigo-600` no código vira `text-primary`/`bg-primary`.

### Tipografia

Troca Geist Sans/Mono (genérico, zero personalidade) por um par com
intenção:

- **Display** (H1, H2 de seção, número de destaque no dashboard):
  **Sora**, peso 700/800. Geométrica, confiante, funciona bem grande —
  ecoa a geometria arredondada do próprio logo (as barras do ícone têm
  esse mesmo peso visual).
- **Corpo/UI** (parágrafos, labels, tabelas do dashboard, formulários):
  **Manrope**, peso 400–600. Humanista-geométrica, terminações
  arredondadas (mesma família de sensação do logo), excelente legibilidade
  em densidade de dashboard. Evita a "fonte seguridão" (Inter) que todo
  produto SaaS usa.
- Números tabulares (preços, horários, contadores no dashboard) usam
  `font-variant-numeric: tabular-nums` do próprio Manrope — não precisa de
  uma terceira família monoespaçada só pra isso.

### Assinatura visual

A landing page hoje segue a fórmula-padrão de SaaS genérico: H1 + subtítulo
+ 2 botões + 3 cards de feature com emoji. Isso não é *do* Plana — poderia
ser de qualquer produto.

**Proposta de assinatura**: o hero mostra o produto fazendo a coisa mais
característica que ele faz — escolher um horário. Em vez de texto solto
num gradiente, o hero tem ao lado (ou atrás, dependendo do layout) um mockup
real da grade de horários do fluxo de agendamento, com um slot em lima
"disponível" e um clique/hover simulado marcando a confirmação. Não é uma
animação complexa — é *mostrar o produto*, que é exatamente o "the hero is
a thesis" do processo de design: abrir com a coisa mais característica do
mundo do produto, não com decoração.

Reforços dessa ideia pelo resto do site:
- Ícones de feature saem de emoji e viram ícones de linha simples
  (`lucide-react`, que já é dependência do projeto) na cor da marca — sem
  introduzir nova lib.
- Badges de status (confirmado/pendente/cancelado) no dashboard usam a
  paleta semântica de verdade (lima = confirmado, teal claro = pendente,
  neutro = cancelado) em vez de cores genéricas de biblioteca.

## Aplicação por tela

**Landing pública** (`(public)/page.tsx`)
- Hero: H1 em Sora, mockup de agenda substituindo o gradiente vazio,
  botão primário em `--teal-600`, botão secundário outline
- Cards de feature: ícones lucide-react em vez de emoji, borda
  `--neutral-border`, fundo `--neutral-bg` (não mais `bg-muted` genérico)
- CTA final: fundo `--teal-900` (não mais `bg-indigo-600`), texto lima no
  destaque

**Auth** (`login`, `register`, `forgot-password`, `reset-password`)
- Mesma correção mecânica: todo `text-indigo-*`/`bg-indigo-*` vira token
  de marca
- Considerar simplificar o card de login (hoje já é limpo, só precisa da
  cor certa)

**Dashboard** (`(salon)/[slug]/dashboard/**`)
- Sidebar: hoje é `bg-gray-900` fixo (só super-admin) / neutro (dono de
  salão) — unificar num neutro com leve viés teal
- Gráficos do dashboard (Recharts): paleta de cores das séries hoje é
  provavelmente default do Recharts — trocar pra escala derivada de
  teal/lima com 1–2 neutros de apoio
- Tabelas: números tabulares, badges de status com a paleta semântica
  acima

**Página pública do salão / booking** (`(salon)/[slug]/page.tsx`,
`booking/**`)
- Essa é a tela que o *cliente final* vê — mais importante ainda ter
  identidade visual clara, já que hoje é a única superfície realmente
  "branded" pro público de cada negócio
- Grade de horários: slot disponível em lima, selecionado em teal,
  indisponível em neutro — reaproveita exatamente o motivo do hero

## Fora de escopo desta rodada

- Qualquer rebranding de nome/rota (já decidido separadamente: copy/rotas
  generalizadas em #75, identificadores internos tipo `salon_owner`
  ficam pra depois)
- Ilustração customizada / fotografia — fica só com iconografia de linha
  por enquanto
- Editor de tema por tenant (cada salão poder customizar a cor da própria
  página pública) — ideia interessante, mas é feature nova, não polish
  visual

## Plano de execução (quando formos fazer)

1. **Tokens primeiro**: reescrever `globals.css` com a paleta acima (light
   + dark), trocar Geist por Sora/Manrope em `layout.tsx`. Isso sozinho já
   corrige o botão padrão, os componentes shadcn genéricos, etc., sem
   tocar em nenhuma outra página.
2. **Varredura mecânica**: `grep -rn "indigo-" web/src` e trocar cada
   ocorrência pelo token equivalente (`text-primary`, `bg-primary`,
   `hover:bg-primary/90` etc.) — é uma limpeza grande mas mecânica.
3. **Landing page**: implementar o mockup do hero (o item que exige
   decisão de layout/conteúdo, não só troca de token) + trocar emoji por
   ícones lucide.
4. **Dashboard e booking pública**: aplicar paleta semântica nos badges de
   status e na grade de horários; revisar paleta dos gráficos Recharts.
5. Validar contraste (WCAG AA) de cada combinação texto/fundo nova antes
   de finalizar — teal em fundo lima claro e vice-versa merece checagem
   específica.

Cada uma dessas etapas pode virar um PR próprio, seguindo o fluxo normal
do projeto (branch → PR → CI → merge).
