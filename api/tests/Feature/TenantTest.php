<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('tenant com slug válido é resolvido pelo middleware', function () {
    $tenant = Tenant::factory()->create(['slug' => 'salao-teste', 'active' => true]);

    $response = $this->getJson('/api/v1/salao/salao-teste/ping');

    $response->assertOk()
        ->assertJson(['ok' => true]);
});

test('tenant inexistente retorna 404', function () {
    $response = $this->getJson('/api/v1/salao/nao-existe/ping');

    $response->assertNotFound();
});

test('tenant inativo retorna 404', function () {
    Tenant::factory()->inactive()->create(['slug' => 'salao-inativo']);

    $response = $this->getJson('/api/v1/salao/salao-inativo/ping');

    $response->assertNotFound();
});

test('pivot tenant_user registra corretamente o owner', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();

    $tenant->users()->attach($user->id, ['role' => 'owner']);

    $pivotRow = DB::table('tenant_user')
        ->where('tenant_id', $tenant->id)
        ->where('user_id', $user->id)
        ->first();

    expect($pivotRow)->not->toBeNull()
        ->and($pivotRow->role)->toBe('owner');

    expect($tenant->owner()->count())->toBe(1);
    expect($user->belongsToTenant($tenant))->toBeTrue();
});

test('endpoint publico do tenant expõe current_tenant_role escopado por tenant, nao pela role global', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $user = User::factory()->create();
    $tenantA->users()->attach($user->id, ['role' => 'owner']);
    $tenantB->users()->attach($user->id, ['role' => 'staff']);

    Role::firstOrCreate(['name' => 'salon_owner', 'guard_name' => 'web']);
    $user->assignRole('salon_owner');

    $this->actingAs($user)
        ->getJson("/api/v1/salao/{$tenantA->slug}")
        ->assertOk()
        ->assertJsonPath('data.current_tenant_role', 'owner');

    $this->actingAs($user)
        ->getJson("/api/v1/salao/{$tenantB->slug}")
        ->assertOk()
        ->assertJsonPath('data.current_tenant_role', 'staff');
});

test('current_tenant_role é null para usuario sem vinculo com o tenant', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson("/api/v1/salao/{$tenant->slug}")
        ->assertOk()
        ->assertJsonPath('data.current_tenant_role', null);
});

test('current_tenant_role é null para requisicao nao autenticada', function () {
    $tenant = Tenant::factory()->create();

    $this->getJson("/api/v1/salao/{$tenant->slug}")
        ->assertOk()
        ->assertJsonPath('data.current_tenant_role', null);
});
