<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// --- Helpers ---

function superAdmin(): User
{
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    return $user;
}

function adminSalonOwner(Tenant $tenant): User
{
    Role::firstOrCreate(['name' => 'salon_owner', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('salon_owner');
    $tenant->users()->attach($user->id, ['role' => 'owner']);

    return $user;
}

// --- Métricas ---

it('super_admin acessa metricas da plataforma', function () {
    $admin = superAdmin();

    $response = $this->actingAs($admin)->getJson('/api/v1/admin/metrics');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'total_tenants',
                'active_tenants',
                'tenants_by_plan',
                'total_users',
                'total_appointments',
                'total_revenue',
            ],
        ]);
});

it('salon_owner nao acessa metricas admin', function () {
    $tenant = Tenant::factory()->create();
    $owner = adminSalonOwner($tenant);

    $this->actingAs($owner)->getJson('/api/v1/admin/metrics')->assertForbidden();
});

it('unauthenticated nao acessa metricas admin', function () {
    $this->getJson('/api/v1/admin/metrics')->assertUnauthorized();
});

// --- Tenants ---

it('super_admin lista todos os tenants com user_count', function () {
    $admin = superAdmin();
    Tenant::factory(3)->create();

    $response = $this->actingAs($admin)->getJson('/api/v1/admin/tenants');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'slug', 'plan', 'active', 'user_count'],
            ],
            'meta',
        ]);

    expect($response->json('meta.total'))->toBeGreaterThanOrEqual(3);
});

it('super_admin ve detalhes de um tenant com owner', function () {
    $admin = superAdmin();
    $tenant = Tenant::factory()->create();
    $owner = adminSalonOwner($tenant);

    $response = $this->actingAs($admin)->getJson("/api/v1/admin/tenants/{$tenant->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $tenant->id)
        ->assertJsonPath('data.owner.email', $owner->email)
        ->assertJsonStructure(['data' => ['id', 'name', 'slug', 'plan', 'active', 'user_count', 'owner']]);
});

it('super_admin atualiza plano do tenant', function () {
    $admin = superAdmin();
    $tenant = Tenant::factory()->create(['plan' => 'starter']);

    $response = $this->actingAs($admin)
        ->patchJson("/api/v1/admin/tenants/{$tenant->id}", ['plan' => 'pro']);

    $response->assertOk()
        ->assertJsonPath('data.plan', 'pro');

    $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'plan' => 'pro']);
});

it('super_admin desativa um tenant', function () {
    $admin = superAdmin();
    $tenant = Tenant::factory()->create(['active' => true]);

    $response = $this->actingAs($admin)
        ->patchJson("/api/v1/admin/tenants/{$tenant->id}", ['active' => false]);

    $response->assertOk()
        ->assertJsonPath('data.active', false);
});

it('salon_owner nao acessa rotas de tenants admin', function () {
    $tenant = Tenant::factory()->create();
    $owner = adminSalonOwner($tenant);

    $this->actingAs($owner)->getJson('/api/v1/admin/tenants')->assertForbidden();
});

it('unauthenticated nao acessa rotas de tenants admin', function () {
    $this->getJson('/api/v1/admin/tenants')->assertUnauthorized();
});
