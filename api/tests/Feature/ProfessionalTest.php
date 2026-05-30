<?php

use App\Models\BlockedDate;
use App\Models\Professional;
use App\Models\Schedule;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function profOwner(Tenant $tenant): User
{
    Role::firstOrCreate(['name' => 'salon_owner', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('salon_owner');
    $tenant->users()->attach($user->id, ['role' => 'owner']);

    return $user;
}

function profStaff(Tenant $tenant): User
{
    Role::firstOrCreate(['name' => 'salon_staff', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('salon_staff');
    $tenant->users()->attach($user->id, ['role' => 'staff']);

    return $user;
}

// --- Profissionais ---

it('lista apenas profissionais ativos do tenant', function () {
    $tenant = Tenant::factory()->create();
    Professional::factory(3)->create(['tenant_id' => $tenant->id, 'active' => true]);
    Professional::factory(1)->create(['tenant_id' => $tenant->id, 'active' => false]);

    $response = $this->getJson("/api/v1/salao/{$tenant->slug}/professionals");

    $response->assertOk()->assertJsonCount(3, 'data');
});

it('nao vaza profissionais entre tenants', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    Professional::factory(2)->create(['tenant_id' => $tenantA->id, 'active' => true]);
    Professional::factory(4)->create(['tenant_id' => $tenantB->id, 'active' => true]);

    $response = $this->getJson("/api/v1/salao/{$tenantA->slug}/professionals");

    $response->assertOk()->assertJsonCount(2, 'data');
});

it('exibe detalhe do profissional com horarios', function () {
    $tenant = Tenant::factory()->create();
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    Schedule::factory()->create([
        'tenant_id' => $tenant->id,
        'professional_id' => $professional->id,
        'day_of_week' => 1,
    ]);

    $response = $this->getJson("/api/v1/salao/{$tenant->slug}/professionals/{$professional->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $professional->id)
        ->assertJsonCount(1, 'data.schedules');
});

it('salon_owner cria profissional com 201', function () {
    $tenant = Tenant::factory()->create();
    $owner = profOwner($tenant);

    $response = $this->actingAs($owner)->postJson("/api/v1/salao/{$tenant->slug}/professionals", [
        'name' => 'Ana Silva',
        'bio' => 'Especialista em coloração',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Ana Silva');

    $this->assertDatabaseHas('professionals', [
        'tenant_id' => $tenant->id,
        'name' => 'Ana Silva',
    ]);
});

it('salon_owner edita profissional', function () {
    $tenant = Tenant::factory()->create();
    $owner = profOwner($tenant);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($owner)->putJson("/api/v1/salao/{$tenant->slug}/professionals/{$professional->id}", [
        'name' => 'Ana Souza',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Ana Souza');
});

it('salon_owner deleta profissional com 204', function () {
    $tenant = Tenant::factory()->create();
    $owner = profOwner($tenant);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($owner)->deleteJson("/api/v1/salao/{$tenant->slug}/professionals/{$professional->id}");

    $response->assertNoContent();
    $this->assertDatabaseMissing('professionals', ['id' => $professional->id]);
});

it('salon_staff nao pode deletar profissional', function () {
    $tenant = Tenant::factory()->create();
    $staff = profStaff($tenant);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($staff)->deleteJson("/api/v1/salao/{$tenant->slug}/professionals/{$professional->id}");

    $response->assertForbidden();
});

// --- Horarios ---

it('salon_owner cria horario para profissional', function () {
    $tenant = Tenant::factory()->create();
    $owner = profOwner($tenant);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($owner)->postJson(
        "/api/v1/salao/{$tenant->slug}/professionals/{$professional->id}/schedules",
        [
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
        ]
    );

    $response->assertCreated()
        ->assertJsonPath('data.day_of_week', 1);

    $this->assertDatabaseHas('schedules', [
        'professional_id' => $professional->id,
        'day_of_week' => 1,
    ]);
});

it('lista horarios do profissional', function () {
    $tenant = Tenant::factory()->create();
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);

    foreach ([1, 2, 3] as $day) {
        Schedule::factory()->create([
            'tenant_id' => $tenant->id,
            'professional_id' => $professional->id,
            'day_of_week' => $day,
        ]);
    }

    $response = $this->getJson("/api/v1/salao/{$tenant->slug}/professionals/{$professional->id}/schedules");

    $response->assertOk()->assertJsonCount(3, 'data');
});

it('salon_owner atualiza horario', function () {
    $tenant = Tenant::factory()->create();
    $owner = profOwner($tenant);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $schedule = Schedule::factory()->create([
        'tenant_id' => $tenant->id,
        'professional_id' => $professional->id,
        'day_of_week' => 1,
        'start_time' => '08:00',
        'end_time' => '17:00',
    ]);

    $response = $this->actingAs($owner)->putJson(
        "/api/v1/salao/{$tenant->slug}/professionals/{$professional->id}/schedules/{$schedule->id}",
        ['start_time' => '09:00', 'end_time' => '18:00']
    );

    $response->assertOk();
    expect($response->json('data.start_time'))->toStartWith('09:00');
});

it('salon_owner deleta horario', function () {
    $tenant = Tenant::factory()->create();
    $owner = profOwner($tenant);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $schedule = Schedule::factory()->create([
        'tenant_id' => $tenant->id,
        'professional_id' => $professional->id,
        'day_of_week' => 2,
    ]);

    $response = $this->actingAs($owner)->deleteJson(
        "/api/v1/salao/{$tenant->slug}/professionals/{$professional->id}/schedules/{$schedule->id}"
    );

    $response->assertNoContent();
    $this->assertDatabaseMissing('schedules', ['id' => $schedule->id]);
});

// --- Datas bloqueadas ---

it('salon_owner cria data bloqueada para profissional', function () {
    $tenant = Tenant::factory()->create();
    $owner = profOwner($tenant);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($owner)->postJson(
        "/api/v1/salao/{$tenant->slug}/professionals/{$professional->id}/blocked-dates",
        [
            'date' => '2026-07-15',
            'reason' => 'Férias',
        ]
    );

    $response->assertCreated()
        ->assertJsonPath('data.date', '2026-07-15')
        ->assertJsonPath('data.reason', 'Férias');
});

it('lista datas bloqueadas do profissional', function () {
    $tenant = Tenant::factory()->create();
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    BlockedDate::factory(2)->create([
        'tenant_id' => $tenant->id,
        'professional_id' => $professional->id,
    ]);

    $response = $this->getJson("/api/v1/salao/{$tenant->slug}/professionals/{$professional->id}/blocked-dates");

    $response->assertOk()->assertJsonCount(2, 'data');
});

it('salon_owner deleta data bloqueada', function () {
    $tenant = Tenant::factory()->create();
    $owner = profOwner($tenant);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $blocked = BlockedDate::factory()->create([
        'tenant_id' => $tenant->id,
        'professional_id' => $professional->id,
    ]);

    $response = $this->actingAs($owner)->deleteJson(
        "/api/v1/salao/{$tenant->slug}/professionals/{$professional->id}/blocked-dates/{$blocked->id}"
    );

    $response->assertNoContent();
    $this->assertDatabaseMissing('blocked_dates', ['id' => $blocked->id]);
});

it('cannot update professional from another tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $ownerA = profOwner($tenantA);
    $professionalB = Professional::factory()->create(['tenant_id' => $tenantB->id]);

    $response = $this->actingAs($ownerA)->putJson("/api/v1/salao/{$tenantA->slug}/professionals/{$professionalB->id}", [
        'name' => 'Profissional Invadido',
    ]);

    $response->assertNotFound();
});

it('cannot delete professional from another tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $ownerA = profOwner($tenantA);
    $professionalB = Professional::factory()->create(['tenant_id' => $tenantB->id]);

    $response = $this->actingAs($ownerA)->deleteJson("/api/v1/salao/{$tenantA->slug}/professionals/{$professionalB->id}");

    $response->assertNotFound();
});

it('nao vaza horarios entre tenants', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $profA = Professional::factory()->create(['tenant_id' => $tenantA->id]);
    $profB = Professional::factory()->create(['tenant_id' => $tenantB->id]);

    foreach ([1, 2] as $day) {
        Schedule::factory()->create(['tenant_id' => $tenantA->id, 'professional_id' => $profA->id, 'day_of_week' => $day]);
    }
    foreach ([1, 2, 3] as $day) {
        Schedule::factory()->create(['tenant_id' => $tenantB->id, 'professional_id' => $profB->id, 'day_of_week' => $day]);
    }

    $response = $this->getJson("/api/v1/salao/{$tenantA->slug}/professionals/{$profA->id}/schedules");

    $response->assertOk()->assertJsonCount(2, 'data');
});
