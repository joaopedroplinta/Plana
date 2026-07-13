---
description: Convenções Laravel/PHP e regras de isolamento multi-tenant para a API
paths:
  - "api/**"
---

## Multi-Tenant — Isolamento Obrigatório

Todo recurso de salão DEVE ter `tenant_id`. Nunca exponha dados de um tenant para outro.

```php
// Todo model de salão DEVE usar esta trait
class Service extends Model
{
    use BelongsToTenant; // adiciona global scope filtrando por tenant_id
}

// Controllers DEVEM resolver o tenant pela rota, nunca do input do usuário
public function index(Request $request): AnonymousResourceCollection
{
    $tenant = $request->route('tenant'); // resolvido pelo middleware ResolveTenant
    return ServiceResource::collection(
        Service::where('tenant_id', $tenant->id)->paginate()
    );
}

// Policies: dupla verificação
public function update(User $user, Service $service): bool
{
    $tenant = app('currentTenant');
    return $user->belongsToTenant($tenant)
        && $service->tenant_id === $tenant->id
        && $user->hasRole(['salon_owner', 'salon_staff']);
}

// FKs em requests: sempre escopadas ao tenant
'professional_id' => [
    'required',
    Rule::exists('professionals', 'id')->where('tenant_id', app('currentTenant')->id),
],
```

## Criação de Arquivos

Sempre usar `php artisan make:` — nunca criar arquivos PHP manualmente:

```bash
cd api

php artisan make:model Service -mfs --no-interaction
php artisan make:controller Api/V1/ServiceController --api --no-interaction
php artisan make:resource ServiceResource --no-interaction
php artisan make:request StoreServiceRequest --no-interaction
php artisan make:policy ServicePolicy --model=Service --no-interaction
php artisan make:test Api/V1/ServiceTest --pest --no-interaction
```

## Rotas

```php
Route::prefix('v1')->group(function () {
    // Rotas autenticadas do salão
    Route::prefix('negocio/{tenant:slug}')
        ->middleware(['auth:sanctum', 'role:salon_owner|salon_staff'])
        ->group(function () {
            Route::apiResource('services', ServiceController::class)->except(['index']);
        });

    // Super admin
    Route::prefix('admin')
        ->middleware(['auth:sanctum', 'role:super_admin'])
        ->group(function () {
            Route::apiResource('tenants', TenantController::class);
        });
});
```

## Resposta e Validação

- Envelope padrão: `{ "data": {...} }` via API Resource — nunca retornar array cru
- `authorize()` nos Form Requests usando `hasRole()`
- Tipos PostgreSQL nativos: `uuid`, `jsonb`, `timestamptz` (nunca `timestamp`)
- Models com UUID: `$incrementing = false`, `$keyType = 'string'`, gerar no `boot()` via `Str::uuid()`
- Plano padrão para novos tenants: `'starter'`

## Após Editar PHP

```bash
cd api && vendor/bin/pint --dirty
```

## Testes

Toda rota nova precisa de teste Pest cobrindo: sucesso, 401 unauthenticated, 403 role errada, e vazamento cross-tenant.

```php
it('does not leak across tenants', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    Service::factory(2)->create(['tenant_id' => $tenantA->id]);

    $response = $this->getJson("/api/v1/negocio/{$tenantB->slug}/services");

    $response->assertOk()->assertJsonCount(0, 'data');
});
```

```bash
cd api && php artisan test --compact
```
