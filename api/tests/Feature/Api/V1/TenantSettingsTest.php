<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function settingsOwner(Tenant $tenant): User
{
    Role::firstOrCreate(['name' => 'salon_owner', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('salon_owner');
    $tenant->users()->attach($user->id, ['role' => 'owner']);

    return $user;
}

it('owner atualiza perfil do salao e os campos aparecem no endpoint publico', function () {
    $tenant = Tenant::factory()->create();
    $owner = settingsOwner($tenant);

    $response = $this->actingAs($owner)->patchJson("/api/v1/negocio/{$tenant->slug}/settings", [
        'name' => 'Salão Renovado',
        'description' => 'O melhor corte da cidade.',
        'phone' => '(42) 99999-0000',
        'whatsapp' => '5542999990000',
        'address' => 'Rua das Flores, 123 — Centro',
        'instagram' => 'salao.renovado',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Salão Renovado')
        ->assertJsonPath('data.description', 'O melhor corte da cidade.')
        ->assertJsonPath('data.whatsapp', '5542999990000');

    // Endpoint público do tenant reflete o perfil
    $this->getJson("/api/v1/negocio/{$tenant->slug}")
        ->assertOk()
        ->assertJsonPath('data.instagram', 'salao.renovado')
        ->assertJsonPath('data.address', 'Rua das Flores, 123 — Centro');
});

it('owner define o sinal padrao do salao e ele aparece no endpoint do tenant', function () {
    $tenant = Tenant::factory()->create();
    $owner = settingsOwner($tenant);

    $this->actingAs($owner)->patchJson("/api/v1/negocio/{$tenant->slug}/settings", [
        'deposit_type' => 'percentage',
        'deposit_value' => 25,
    ])->assertOk()
        ->assertJsonPath('data.deposit_type', 'percentage')
        ->assertJsonPath('data.deposit_value', 25);

    expect($tenant->fresh()->settings)->toMatchArray(['deposit_type' => 'percentage', 'deposit_value' => 25]);
});

it("sinal 'none' zera o valor guardado no settings", function () {
    $tenant = Tenant::factory()->create(['settings' => ['deposit_type' => 'fixed', 'deposit_value' => 3000]]);
    $owner = settingsOwner($tenant);

    $this->actingAs($owner)->patchJson("/api/v1/negocio/{$tenant->slug}/settings", [
        'deposit_type' => 'none',
    ])->assertOk()->assertJsonPath('data.deposit_value', null);

    expect($tenant->fresh()->settings['deposit_value'])->toBeNull();
});

it('atualizacao parcial nao apaga os demais campos do settings', function () {
    $tenant = Tenant::factory()->create(['settings' => ['phone' => '(42) 1111-1111', 'description' => 'Original']]);
    $owner = settingsOwner($tenant);

    $this->actingAs($owner)->patchJson("/api/v1/negocio/{$tenant->slug}/settings", [
        'description' => 'Atualizada',
    ])->assertOk();

    $fresh = $tenant->fresh();
    expect($fresh->settings['description'])->toBe('Atualizada')
        ->and($fresh->settings['phone'])->toBe('(42) 1111-1111');
});

it('staff nao pode atualizar o perfil do salao', function () {
    $tenant = Tenant::factory()->create();
    Role::firstOrCreate(['name' => 'salon_staff', 'guard_name' => 'web']);
    $staff = User::factory()->create();
    $staff->assignRole('salon_staff');
    $tenant->users()->attach($staff->id, ['role' => 'staff']);

    $this->actingAs($staff)->patchJson("/api/v1/negocio/{$tenant->slug}/settings", [
        'name' => 'Hack',
    ])->assertForbidden();
});

it('owner de outro tenant nao atualiza este salao', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $ownerB = settingsOwner($tenantB);

    $this->actingAs($ownerB)->patchJson("/api/v1/negocio/{$tenantA->slug}/settings", [
        'name' => 'Invasão',
    ])->assertForbidden();
});

it('exige autenticacao', function () {
    $tenant = Tenant::factory()->create();

    $this->patchJson("/api/v1/negocio/{$tenant->slug}/settings", ['name' => 'X'])
        ->assertUnauthorized();
});
