Run the test suite locally and report results: $ARGUMENTS

Optional argument: a filter string to run specific tests (ex: `/run-tests AuthTest`).

## API tests (Pest)

```bash
cd /home/pinguasnote/Documentos/codes/sistema-agendamentos/api

# Com filtro (se $ARGUMENTS foi passado)
php artisan test --compact --filter="$ARGUMENTS"

# Sem filtro (todos os testes)
php artisan test --compact
```

## Web (TypeScript check + lint)

If $ARGUMENTS is empty (running all), also check the web package:

```bash
cd /home/pinguasnote/Documentos/codes/sistema-agendamentos/web
npm run build 2>&1 | tail -20
npm run lint 2>&1 | tail -20
```

## Report

After running, produce a summary:

```
API Tests
  ✅ X passed  ❌ Y failed  (Z assertions, Ns)

  Failures (if any):
  - TestName: error message

Web
  ✅ TypeScript build: OK
  ✅ ESLint: OK
  (or show errors)
```

If any test fails, show the full failure output so the user can act on it.
Do NOT auto-fix failures — report only.
