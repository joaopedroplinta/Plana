<?php

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\StaffInvited;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function teamOwner(Tenant $tenant): User
{
    Role::firstOrCreate(['name' => 'salon_owner', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('salon_owner');
    $tenant->users()->attach($user->id, ['role' => 'owner']);

    return $user;
}

function teamStaff(Tenant $tenant): User
{
    Role::firstOrCreate(['name' => 'salon_staff', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('salon_staff');
    $tenant->users()->attach($user->id, ['role' => 'staff']);

    return $user;
}

// --- Listar equipe ---

it('owner lista a equipe do salao com papeis', function () {
    $tenant = Tenant::factory()->create();
    $owner = teamOwner($tenant);
    teamStaff($tenant);

    // Cliente não deve aparecer na lista da equipe
    $client = User::factory()->create();
    $tenant->users()->attach($client->id, ['role' => 'client']);

    $response = $this->actingAs($owner)->getJson("/api/v1/salao/{$tenant->slug}/team");

    $response->assertOk()->assertJsonCount(2, 'data');
});

it('cliente nao acessa a lista da equipe', function () {
    $tenant = Tenant::factory()->create();
    teamOwner($tenant);
    $client = User::factory()->create();
    $tenant->users()->attach($client->id, ['role' => 'client']);

    $this->actingAs($client)->getJson("/api/v1/salao/{$tenant->slug}/team")->assertForbidden();
});

it('exige autenticacao para listar equipe', function () {
    $tenant = Tenant::factory()->create();

    $this->getJson("/api/v1/salao/{$tenant->slug}/team")->assertUnauthorized();
});

// --- Convidar staff ---

it('owner convida novo staff que recebe email com link de senha', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $owner = teamOwner($tenant);

    $response = $this->actingAs($owner)->postJson("/api/v1/salao/{$tenant->slug}/team", [
        'name' => 'Nova Funcionária',
        'email' => 'func@salao.com',
    ]);

    $response->assertCreated()->assertJsonPath('data.role', 'staff');

    $user = User::where('email', 'func@salao.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->hasRole('salon_staff'))->toBeTrue();

    $this->assertDatabaseHas('tenant_user', [
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => 'staff',
    ]);

    Notification::assertSentTo($user, StaffInvited::class);
});

it('convidar usuario existente vincula sem criar conta nova', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $owner = teamOwner($tenant);
    $existing = User::factory()->create(['email' => 'ja-existe@test.com']);
    $before = User::count();

    $this->actingAs($owner)->postJson("/api/v1/salao/{$tenant->slug}/team", [
        'name' => 'Já Existe',
        'email' => 'ja-existe@test.com',
    ])->assertCreated();

    expect(User::count())->toBe($before);
    Notification::assertSentTo($existing, StaffInvited::class);
});

it('nao convida quem ja faz parte do salao', function () {
    $tenant = Tenant::factory()->create();
    $owner = teamOwner($tenant);
    $staff = teamStaff($tenant);

    $this->actingAs($owner)->postJson("/api/v1/salao/{$tenant->slug}/team", [
        'name' => $staff->name,
        'email' => $staff->email,
    ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
});

it('staff nao pode convidar membros', function () {
    $tenant = Tenant::factory()->create();
    teamOwner($tenant);
    $staff = teamStaff($tenant);

    $this->actingAs($staff)->postJson("/api/v1/salao/{$tenant->slug}/team", [
        'name' => 'Alguém',
        'email' => 'alguem@test.com',
    ])->assertForbidden();
});

it('owner de outro tenant nao convida staff neste salao', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $ownerB = teamOwner($tenantB);

    $this->actingAs($ownerB)->postJson("/api/v1/salao/{$tenantA->slug}/team", [
        'name' => 'Invasor',
        'email' => 'invasor@test.com',
    ])->assertForbidden();
});

// --- Remover staff ---

it('owner remove staff da equipe', function () {
    $tenant = Tenant::factory()->create();
    $owner = teamOwner($tenant);
    $staff = teamStaff($tenant);

    $this->actingAs($owner)
        ->deleteJson("/api/v1/salao/{$tenant->slug}/team/{$staff->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('tenant_user', [
        'tenant_id' => $tenant->id,
        'user_id' => $staff->id,
    ]);
});

it('nao remove o dono do salao', function () {
    $tenant = Tenant::factory()->create();
    $owner = teamOwner($tenant);

    $this->actingAs($owner)
        ->deleteJson("/api/v1/salao/{$tenant->slug}/team/{$owner->id}")
        ->assertUnprocessable();
});

it('remover usuario de fora do tenant retorna 404', function () {
    $tenant = Tenant::factory()->create();
    $owner = teamOwner($tenant);
    $stranger = User::factory()->create();

    $this->actingAs($owner)
        ->deleteJson("/api/v1/salao/{$tenant->slug}/team/{$stranger->id}")
        ->assertNotFound();
});

it('cliente do salao convidado para a equipe e promovido a staff', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $owner = teamOwner($tenant);
    $client = User::factory()->create();
    $tenant->users()->attach($client->id, ['role' => 'client']);

    $this->actingAs($owner)->postJson("/api/v1/salao/{$tenant->slug}/team", [
        'name' => $client->name,
        'email' => $client->email,
    ])->assertCreated();

    $this->assertDatabaseHas('tenant_user', [
        'tenant_id' => $tenant->id,
        'user_id' => $client->id,
        'role' => 'staff',
    ]);

    Notification::assertSentTo($client, StaffInvited::class);
});
