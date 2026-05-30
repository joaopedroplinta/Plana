<?php

use App\Models\Service;
use App\Models\ServicePackage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function pkgOwner(Tenant $tenant): User
{
    Role::firstOrCreate(['name' => 'salon_owner', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('salon_owner');
    $tenant->users()->attach($user->id, ['role' => 'owner']);

    return $user;
}

function pkgStaff(Tenant $tenant): User
{
    Role::firstOrCreate(['name' => 'salon_staff', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('salon_staff');
    $tenant->users()->attach($user->id, ['role' => 'staff']);

    return $user;
}

it('lista pacotes do tenant publicamente', function () {
    $tenant = Tenant::factory()->create();
    ServicePackage::factory(3)->create(['tenant_id' => $tenant->id]);

    $response = $this->getJson("/api/v1/salao/{$tenant->slug}/packages");

    $response->assertOk()->assertJsonCount(3, 'data');
});

it('nao vaza pacotes entre tenants', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    ServicePackage::factory(2)->create(['tenant_id' => $tenantA->id]);
    ServicePackage::factory(4)->create(['tenant_id' => $tenantB->id]);

    $response = $this->getJson("/api/v1/salao/{$tenantA->slug}/packages");

    $response->assertOk()->assertJsonCount(2, 'data');
});

it('exibe detalhe do pacote com servicos', function () {
    $tenant = Tenant::factory()->create();
    $package = ServicePackage::factory()->create(['tenant_id' => $tenant->id]);
    $services = Service::factory(2)->create(['tenant_id' => $tenant->id]);
    $package->services()->sync($services->pluck('id')->toArray());

    $response = $this->getJson("/api/v1/salao/{$tenant->slug}/packages/{$package->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $package->id)
        ->assertJsonCount(2, 'data.services');
});

it('salon_owner cria pacote com servicos', function () {
    $tenant = Tenant::factory()->create();
    $owner = pkgOwner($tenant);
    $services = Service::factory(2)->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($owner)->postJson("/api/v1/salao/{$tenant->slug}/packages", [
        'name' => 'Pacote Verão',
        'price' => 20000,
        'sessions' => 5,
        'valid_days' => 90,
        'service_ids' => $services->pluck('id')->toArray(),
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Pacote Verão')
        ->assertJsonCount(2, 'data.services');
});

it('salon_owner atualiza pacote e sincroniza servicos', function () {
    $tenant = Tenant::factory()->create();
    $owner = pkgOwner($tenant);
    $package = ServicePackage::factory()->create(['tenant_id' => $tenant->id]);
    $oldServices = Service::factory(2)->create(['tenant_id' => $tenant->id]);
    $newService = Service::factory()->create(['tenant_id' => $tenant->id]);
    $package->services()->sync($oldServices->pluck('id')->toArray());

    $response = $this->actingAs($owner)->putJson("/api/v1/salao/{$tenant->slug}/packages/{$package->id}", [
        'name' => 'Pacote Atualizado',
        'service_ids' => [$newService->id],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Pacote Atualizado')
        ->assertJsonCount(1, 'data.services');
});

it('salon_owner deleta pacote com 204', function () {
    $tenant = Tenant::factory()->create();
    $owner = pkgOwner($tenant);
    $package = ServicePackage::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($owner)->deleteJson("/api/v1/salao/{$tenant->slug}/packages/{$package->id}");

    $response->assertNoContent();
    $this->assertDatabaseMissing('service_packages', ['id' => $package->id]);
});

it('salon_staff nao pode deletar pacote', function () {
    $tenant = Tenant::factory()->create();
    $staff = pkgStaff($tenant);
    $package = ServicePackage::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($staff)->deleteJson("/api/v1/salao/{$tenant->slug}/packages/{$package->id}");

    $response->assertForbidden();
});

it('usuario nao autenticado nao pode criar pacote', function () {
    $tenant = Tenant::factory()->create();

    $response = $this->postJson("/api/v1/salao/{$tenant->slug}/packages", [
        'name' => 'Pacote X',
        'price' => 10000,
        'sessions' => 3,
        'valid_days' => 30,
    ]);

    $response->assertUnauthorized();
});

it('cannot update package from another tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $ownerA = pkgOwner($tenantA);
    $packageB = ServicePackage::factory()->create(['tenant_id' => $tenantB->id]);

    $response = $this->actingAs($ownerA)->putJson("/api/v1/salao/{$tenantA->slug}/packages/{$packageB->id}", [
        'name' => 'Pacote Invadido',
    ]);

    $response->assertNotFound();
});

it('cannot reference services from another tenant in service_ids', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $ownerA = pkgOwner($tenantA);
    $servicesB = Service::factory(2)->create(['tenant_id' => $tenantB->id]);

    $response = $this->actingAs($ownerA)->postJson("/api/v1/salao/{$tenantA->slug}/packages", [
        'name' => 'Pacote Cross-Tenant',
        'price' => 15000,
        'sessions' => 3,
        'valid_days' => 60,
        'service_ids' => $servicesB->pluck('id')->toArray(),
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['service_ids.0']);
});
