---
description: Inicia trabalho em uma issue do GitHub — cria branch, move para In Progress, implementa e abre PR
argument-hint: <número da issue>
---

Start work on a GitHub issue end-to-end: $ARGUMENTS

The argument is the issue number (ex: `/start-issue 6`).

Execute the following steps:

## 1. Buscar detalhes da issue

```bash
gh issue view <N> --repo joaopedroplinta/sistema-agendamentos --json number,title,body,labels
```

Read the issue title, body and labels to understand what needs to be implemented.

## 2. Criar branch

Branch naming convention:
- API issue: `feat/<N>-api-<slug>`
- Web issue: `feat/<N>-web-<slug>`
- Both (full feature): use `feat/<N>-<slug>` for each side

```bash
git checkout main && git pull
git checkout -b feat/<N>-<slug>
git push -u origin feat/<N>-<slug>
```

## 3. Mover issue para In Progress

```bash
gh issue edit <N> --repo joaopedroplinta/sistema-agendamentos --add-assignee joaopedroplinta
```

Try to move the issue to "In Progress" on the GitHub Project board if the token allows it.

## 4. Identificar o tipo de issue e implementar

- Se a label for apenas `api`: use o **api-agent** para implementar
- Se a label for apenas `web`: use o **web-agent** para implementar
- Se houver issues relacionadas de api + web: use o **feature-orchestrator** para implementar ambas em paralelo
- Se a label for `infra`: implemente diretamente

Em todos os casos:
- Seguir as convenções do CLAUDE.md raiz e do CLAUDE.md específico de cada pacote
- Rodar testes após implementar
- Rodar pint após editar PHP

## 5. Rodar tenant-guard

Após implementar, sempre rodar o tenant-guard para verificar isolamento multi-tenant:

```
Use the tenant-guard agent to audit the new code.
```

## 6. Abrir PR

```bash
gh pr create \
  --repo joaopedroplinta/sistema-agendamentos \
  --title "<título da issue>" \
  --assignee joaopedroplinta \
  --body "..." \
  --base main \
  --head <branch>
```

O body da PR deve conter:
- Summary (bullet points do que foi implementado)
- Test plan (checklist)
- `Closes #<N>`
