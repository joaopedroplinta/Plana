<?php

use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeOwner(Tenant $tenant): User
{
    Role::firstOrCreate(['name' => 'salon_owner', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('salon_owner');
    $tenant->users()->attach($user->id, ['role' => 'owner']);

    return $user;
}

function makeStaff(Tenant $tenant): User
{
    Role::firstOrCreate(['name' => 'salon_staff', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('salon_staff');
    $tenant->users()->attach($user->id, ['role' => 'staff']);

    return $user;
}

function makeClient(Tenant $tenant): User
{
    Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('client');
    $tenant->users()->attach($user->id, ['role' => 'client']);

    return $user;
}

it('lista apenas servicos ativos do tenant', function () {
    $tenant = Tenant::factory()->create();
    Service::factory(3)->create(['tenant_id' => $tenant->id, 'active' => true]);
    Service::factory(2)->create(['tenant_id' => $tenant->id, 'active' => false]);

    $response = $this->getJson("/api/v1/negocio/{$tenant->slug}/services");

    $response->assertOk()->assertJsonCount(3, 'data');
});

it('nao vaza servicos entre tenants', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    Service::factory(2)->create(['tenant_id' => $tenantA->id, 'active' => true]);
    Service::factory(3)->create(['tenant_id' => $tenantB->id, 'active' => true]);

    $response = $this->getJson("/api/v1/negocio/{$tenantA->slug}/services");

    $response->assertOk()->assertJsonCount(2, 'data');
});

it('retorna detalhe de servico publicamente', function () {
    $tenant = Tenant::factory()->create();
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'active' => true]);

    $response = $this->getJson("/api/v1/negocio/{$tenant->slug}/services/{$service->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $service->id)
        ->assertJsonPath('data.name', $service->name);
});

it('salon_owner cria servico com 201', function () {
    $tenant = Tenant::factory()->create();
    $owner = makeOwner($tenant);

    $response = $this->actingAs($owner)->postJson("/api/v1/negocio/{$tenant->slug}/services", [
        'name' => 'Corte Feminino',
        'price' => 8000,
        'duration_minutes' => 60,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Corte Feminino');

    $this->assertDatabaseHas('services', [
        'tenant_id' => $tenant->id,
        'name' => 'Corte Feminino',
        'price' => 8000,
    ]);
});

it('cria servico com sinal percentual e persiste a config', function () {
    $tenant = Tenant::factory()->create();
    $owner = makeOwner($tenant);

    $response = $this->actingAs($owner)->postJson("/api/v1/negocio/{$tenant->slug}/services", [
        'name' => 'Coloração',
        'price' => 20000,
        'duration_minutes' => 120,
        'deposit_type' => 'percentage',
        'deposit_value' => 30,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.deposit_type', 'percentage')
        ->assertJsonPath('data.deposit_value', 30);

    $this->assertDatabaseHas('services', [
        'tenant_id' => $tenant->id,
        'name' => 'Coloração',
        'deposit_type' => 'percentage',
        'deposit_value' => 30,
    ]);
});

it('rejeita sinal percentual acima de 100 com 422', function () {
    $tenant = Tenant::factory()->create();
    $owner = makeOwner($tenant);

    $response = $this->actingAs($owner)->postJson("/api/v1/negocio/{$tenant->slug}/services", [
        'name' => 'X', 'price' => 1000, 'duration_minutes' => 30,
        'deposit_type' => 'percentage', 'deposit_value' => 150,
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['deposit_value']);
});

it('rejeita sinal fixo/percentual sem valor com 422', function () {
    $tenant = Tenant::factory()->create();
    $owner = makeOwner($tenant);

    $response = $this->actingAs($owner)->postJson("/api/v1/negocio/{$tenant->slug}/services", [
        'name' => 'X', 'price' => 1000, 'duration_minutes' => 30,
        'deposit_type' => 'fixed',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['deposit_value']);
});

it('salon_staff cria servico com 201', function () {
    $tenant = Tenant::factory()->create();
    $staff = makeStaff($tenant);

    $response = $this->actingAs($staff)->postJson("/api/v1/negocio/{$tenant->slug}/services", [
        'name' => 'Hidratação',
        'price' => 5000,
        'duration_minutes' => 45,
    ]);

    $response->assertCreated();
});

it('client nao pode criar servico', function () {
    $tenant = Tenant::factory()->create();
    $client = makeClient($tenant);

    $response = $this->actingAs($client)->postJson("/api/v1/negocio/{$tenant->slug}/services", [
        'name' => 'Corte',
        'price' => 3000,
        'duration_minutes' => 30,
    ]);

    $response->assertForbidden();
});

it('salon_owner edita servico com 200', function () {
    $tenant = Tenant::factory()->create();
    $owner = makeOwner($tenant);
    $service = Service::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($owner)->putJson("/api/v1/negocio/{$tenant->slug}/services/{$service->id}", [
        'name' => 'Corte Atualizado',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Corte Atualizado');
});

it('salon_owner deleta servico com 204', function () {
    $tenant = Tenant::factory()->create();
    $owner = makeOwner($tenant);
    $service = Service::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($owner)->deleteJson("/api/v1/negocio/{$tenant->slug}/services/{$service->id}");

    $response->assertNoContent();
    $this->assertDatabaseMissing('services', ['id' => $service->id]);
});

it('salon_staff nao pode deletar servico', function () {
    $tenant = Tenant::factory()->create();
    $staff = makeStaff($tenant);
    $service = Service::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($staff)->deleteJson("/api/v1/negocio/{$tenant->slug}/services/{$service->id}");

    $response->assertForbidden();
});

it('validacao falha se name esta ausente ao criar servico', function () {
    $tenant = Tenant::factory()->create();
    $owner = makeOwner($tenant);

    $response = $this->actingAs($owner)->postJson("/api/v1/negocio/{$tenant->slug}/services", [
        'price' => 3000,
        'duration_minutes' => 30,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('cannot update service from another tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $ownerA = makeOwner($tenantA);
    $serviceB = Service::factory()->create(['tenant_id' => $tenantB->id]);

    $response = $this->actingAs($ownerA)->putJson("/api/v1/negocio/{$tenantA->slug}/services/{$serviceB->id}", [
        'name' => 'Serviço Invadido',
    ]);

    $response->assertNotFound();
});

it('cannot delete service from another tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $ownerA = makeOwner($tenantA);
    $serviceB = Service::factory()->create(['tenant_id' => $tenantB->id]);

    $response = $this->actingAs($ownerA)->deleteJson("/api/v1/negocio/{$tenantA->slug}/services/{$serviceB->id}");

    $response->assertNotFound();
});

it('cannot create service in another tenant via route', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $ownerA = makeOwner($tenantA);

    $response = $this->actingAs($ownerA)->postJson("/api/v1/negocio/{$tenantA->slug}/services", [
        'name' => 'Serviço Novo',
        'price' => 5000,
        'duration_minutes' => 60,
    ]);

    $response->assertCreated();

    $this->assertDatabaseHas('services', [
        'name' => 'Serviço Novo',
        'tenant_id' => $tenantA->id,
    ]);

    $this->assertDatabaseMissing('services', [
        'name' => 'Serviço Novo',
        'tenant_id' => $tenantB->id,
    ]);
});

// --- Upload de imagem do serviço ---

it('owner faz upload da imagem do serviço', function () {
    Storage::fake('public');
    $tenant = Tenant::factory()->create();
    $owner = makeOwner($tenant);
    $service = Service::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($owner)->postJson("/api/v1/negocio/{$tenant->slug}/services/{$service->id}/image", [
        'image' => UploadedFile::fake()->create('foto.jpg', 100, 'image/jpeg'),
    ]);

    $response->assertOk();
    $imageUrl = $response->json('data.image_url');
    expect($imageUrl)->toStartWith('/storage/services/');
    Storage::disk('public')->assertExists(str_replace('/storage/', '', $imageUrl));
});

it('staff também pode enviar imagem do serviço', function () {
    Storage::fake('public');
    $tenant = Tenant::factory()->create();
    $staff = makeStaff($tenant);
    $service = Service::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($staff)->postJson("/api/v1/negocio/{$tenant->slug}/services/{$service->id}/image", [
        'image' => UploadedFile::fake()->create('foto.jpg', 100, 'image/jpeg'),
    ])->assertOk();
});

it('client não pode enviar imagem do serviço', function () {
    Storage::fake('public');
    $tenant = Tenant::factory()->create();
    $client = makeClient($tenant);
    $service = Service::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($client)->postJson("/api/v1/negocio/{$tenant->slug}/services/{$service->id}/image", [
        'image' => UploadedFile::fake()->create('foto.jpg', 100, 'image/jpeg'),
    ])->assertStatus(403);
});

it('rejeita arquivo que não é imagem', function () {
    Storage::fake('public');
    $tenant = Tenant::factory()->create();
    $owner = makeOwner($tenant);
    $service = Service::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($owner)->postJson("/api/v1/negocio/{$tenant->slug}/services/{$service->id}/image", [
        'image' => UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf'),
    ])->assertStatus(422)->assertJsonValidationErrors(['image']);
});

it('owner não envia imagem para serviço de outro tenant', function () {
    Storage::fake('public');
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $ownerA = makeOwner($tenantA);
    $serviceB = Service::factory()->create(['tenant_id' => $tenantB->id]);

    // O serviço de B não existe no escopo de A → 404.
    $this->actingAs($ownerA)->postJson("/api/v1/negocio/{$tenantA->slug}/services/{$serviceB->id}/image", [
        'image' => UploadedFile::fake()->create('foto.jpg', 100, 'image/jpeg'),
    ])->assertStatus(404);
});
