Use the github-agent to close the current sprint: $ARGUMENTS

Execute the following steps:

## 1. Levantamento do sprint
List all issues with the current sprint label and check their state:
```bash
gh issue list --repo joaopedroplinta/sistema-agendamentos --label "sprint-N" --json number,title,state,labels
```

## 2. Verificar PRs abertos relacionados
```bash
gh pr list --repo joaopedroplinta/sistema-agendamentos --json number,title,state,body | python3 -c "..."
```

## 3. Relatório do sprint
Generate and display a sprint summary:
- ✅ Issues entregues (closed)
- 🔄 Issues não finalizadas (still open)
- 📊 Taxa de conclusão (%)
- 🔗 Links das PRs mergeadas

## 4. Mover issues não finalizadas
For each open issue from this sprint, move it to the next sprint label:
```bash
gh issue edit N --repo joaopedroplinta/sistema-agendamentos --remove-label "sprint-N" --add-label "sprint-N+1"
```
Also update the Sprint field in the GitHub Project to the next sprint iteration.

## 5. Criar release tag
```bash
git tag -a v0.N.0 -m "Sprint N — [Sprint Name]: [summary of features delivered]"
git push origin v0.N.0
```

## 6. Criar GitHub Release
```bash
gh release create v0.N.0 \
  --repo joaopedroplinta/sistema-agendamentos \
  --title "Sprint N — [Sprint Name]" \
  --notes "## Entregues\n- Feature 1\n- Feature 2\n\n## Issues fechadas\n- Closes #N1\n- Closes #N2"
```

## 7. Preparar próximo sprint
- Confirm which issues will be in the next sprint
- Report next sprint backlog to the user

Always ask the user to confirm before creating the release tag and before moving unfinished issues to the next sprint.
