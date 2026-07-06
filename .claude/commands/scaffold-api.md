---
description: Gera scaffold completo de feature API via api-agent (migration, model, controller, policy, testes)
argument-hint: <descrição da feature>
---

Use the api-agent to scaffold the following API feature: $ARGUMENTS

The agent must create:
1. Migration (with tenant_id if salon-scoped)
2. Model (with BelongsToTenant trait and factory)
3. API Resource
4. Form Request(s) for validation
5. Controller (with proper auth middleware in routes)
6. Policy
7. Routes in api/routes/api.php
8. Pest feature tests (including tenant isolation test)

After creation, run: vendor/bin/pint --dirty
Then run: php artisan test --compact
