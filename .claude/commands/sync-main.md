---
description: Faz rebase da branch atual em cima de main e push com force-with-lease
---

Rebase the current branch on top of main and push: $ARGUMENTS

Execute the following steps:

## 1. Verificar branch atual

```bash
git branch --show-current
git status
```

If there are uncommitted changes, warn the user and stop. Do not proceed with dirty working tree.

## 2. Atualizar main local

```bash
git fetch origin
git checkout main
git pull
```

## 3. Voltar para a branch e fazer rebase

```bash
git checkout <branch-anterior>
git rebase origin/main
```

If rebase conflicts occur:
- Show the conflicting files
- Ask the user how to resolve before continuing
- Never auto-resolve conflicts

## 4. Push com force-with-lease

```bash
git push --force-with-lease
```

Use `--force-with-lease` (never `--force`) to avoid overwriting remote changes you haven't seen.

## 5. Confirmar

Report the final state: branch name, how many commits ahead of main, and the last commit message.
