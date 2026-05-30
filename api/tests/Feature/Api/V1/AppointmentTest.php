<?php

use App\Models\Appointment;
use App\Models\BlockedDate;
use App\Models\Professional;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// --- Helpers ---

function apptOwner(Tenant $tenant): User
{
    Role::firstOrCreate(['name' => 'salon_owner', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('salon_owner');
    $tenant->users()->attach($user->id, ['role' => 'owner']);

    return $user;
}

function apptStaff(Tenant $tenant): User
{
    Role::firstOrCreate(['name' => 'salon_staff', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('salon_staff');
    $tenant->users()->attach($user->id, ['role' => 'staff']);

    return $user;
}

function apptClient(Tenant $tenant): User
{
    Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('client');
    $tenant->users()->attach($user->id, ['role' => 'client']);

    return $user;
}

// --- Criar agendamento ---

it('cria agendamento com slot valido e retorna 201', function () {
    $tenant = Tenant::factory()->create();
    $client = apptClient($tenant);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 60, 'price' => 5000]);

    $startsAt = now()->addDay()->setHour(10)->setMinute(0)->setSecond(0)->toIso8601String();

    $response = $this->actingAs($client)->postJson("/api/v1/salao/{$tenant->slug}/appointments", [
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'starts_at' => $startsAt,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.price', 5000);

    $this->assertDatabaseHas('appointments', [
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'status' => 'pending',
        'price' => 5000,
    ]);
});

it('rejeita agendamento em slot ocupado com 422', function () {
    $tenant = Tenant::factory()->create();
    $client = apptClient($tenant);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 60, 'price' => 5000]);

    $startsAt = now()->addDay()->setHour(10)->setMinute(0)->setSecond(0);

    Appointment::factory()->create([
        'tenant_id' => $tenant->id,
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'starts_at' => $startsAt->copy(),
        'ends_at' => $startsAt->copy()->addMinutes(60),
        'status' => 'confirmed',
        'price' => 5000,
    ]);

    $response = $this->actingAs($client)->postJson("/api/v1/salao/{$tenant->slug}/appointments", [
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'starts_at' => $startsAt->toIso8601String(),
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['starts_at']);
});

it('rejeita agendamento com profissional de outro tenant com 422', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $client = apptClient($tenantA);
    $professionalB = Professional::factory()->create(['tenant_id' => $tenantB->id]);
    $service = Service::factory()->create(['tenant_id' => $tenantA->id, 'duration_minutes' => 60]);

    $response = $this->actingAs($client)->postJson("/api/v1/salao/{$tenantA->slug}/appointments", [
        'professional_id' => $professionalB->id,
        'service_id' => $service->id,
        'starts_at' => now()->addDay()->setHour(10)->toIso8601String(),
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['professional_id']);
});

// --- Listar agendamentos ---

it('salon_owner lista todos os agendamentos do tenant', function () {
    $tenant = Tenant::factory()->create();
    $owner = apptOwner($tenant);
    $clientA = apptClient($tenant);
    $clientB = apptClient($tenant);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 30]);

    Appointment::factory(2)->create([
        'tenant_id' => $tenant->id,
        'client_id' => $clientA->id,
        'professional_id' => $professional->id,
        'service_id' => $service->id,
    ]);
    Appointment::factory(3)->create([
        'tenant_id' => $tenant->id,
        'client_id' => $clientB->id,
        'professional_id' => $professional->id,
        'service_id' => $service->id,
    ]);

    $response = $this->actingAs($owner)->getJson("/api/v1/salao/{$tenant->slug}/appointments");

    $response->assertOk()->assertJsonCount(5, 'data');
});

it('client lista apenas os proprios agendamentos', function () {
    $tenant = Tenant::factory()->create();
    $clientA = apptClient($tenant);
    $clientB = apptClient($tenant);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 30]);

    Appointment::factory(2)->create([
        'tenant_id' => $tenant->id,
        'client_id' => $clientA->id,
        'professional_id' => $professional->id,
        'service_id' => $service->id,
    ]);
    Appointment::factory(3)->create([
        'tenant_id' => $tenant->id,
        'client_id' => $clientB->id,
        'professional_id' => $professional->id,
        'service_id' => $service->id,
    ]);

    $response = $this->actingAs($clientA)->getJson("/api/v1/salao/{$tenant->slug}/appointments");

    $response->assertOk()->assertJsonCount(2, 'data');
});

// --- Confirmar agendamento ---

it('salon_owner confirma agendamento com sucesso', function () {
    $tenant = Tenant::factory()->create();
    $owner = apptOwner($tenant);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 60]);
    $appointment = Appointment::factory()->pending()->create([
        'tenant_id' => $tenant->id,
        'professional_id' => $professional->id,
        'service_id' => $service->id,
    ]);

    $response = $this->actingAs($owner)
        ->patchJson("/api/v1/salao/{$tenant->slug}/appointments/{$appointment->id}/confirm");

    $response->assertOk()
        ->assertJsonPath('data.status', 'confirmed');

    $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'confirmed']);
});

it('client nao pode confirmar agendamento', function () {
    $tenant = Tenant::factory()->create();
    $client = apptClient($tenant);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 60]);
    $appointment = Appointment::factory()->pending()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'professional_id' => $professional->id,
        'service_id' => $service->id,
    ]);

    $response = $this->actingAs($client)
        ->patchJson("/api/v1/salao/{$tenant->slug}/appointments/{$appointment->id}/confirm");

    $response->assertForbidden();
});

// --- Cancelar agendamento ---

it('client cancela o proprio agendamento com sucesso', function () {
    $tenant = Tenant::factory()->create();
    $client = apptClient($tenant);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 60]);
    $appointment = Appointment::factory()->pending()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'professional_id' => $professional->id,
        'service_id' => $service->id,
    ]);

    $response = $this->actingAs($client)
        ->patchJson("/api/v1/salao/{$tenant->slug}/appointments/{$appointment->id}/cancel");

    $response->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
});

it('client nao pode cancelar agendamento de outro cliente', function () {
    $tenant = Tenant::factory()->create();
    $clientA = apptClient($tenant);
    $clientB = apptClient($tenant);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 60]);
    $appointment = Appointment::factory()->pending()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $clientB->id,
        'professional_id' => $professional->id,
        'service_id' => $service->id,
    ]);

    $response = $this->actingAs($clientA)
        ->patchJson("/api/v1/salao/{$tenant->slug}/appointments/{$appointment->id}/cancel");

    $response->assertForbidden();
});

// --- Disponibilidade ---

it('retorna slots corretos para profissional com schedule', function () {
    $tenant = Tenant::factory()->create();
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 60]);

    $date = now()->next('Monday');

    Schedule::factory()->create([
        'tenant_id' => $tenant->id,
        'professional_id' => $professional->id,
        'day_of_week' => $date->dayOfWeek,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
    ]);

    $response = $this->getJson("/api/v1/salao/{$tenant->slug}/availability?professional_id={$professional->id}&service_id={$service->id}&date={$date->format('Y-m-d')}");

    $response->assertOk()
        ->assertJsonCount(3, 'data');

    $response->assertJsonFragment(['starts_at' => '09:00', 'ends_at' => '10:00']);
    $response->assertJsonFragment(['starts_at' => '10:00', 'ends_at' => '11:00']);
    $response->assertJsonFragment(['starts_at' => '11:00', 'ends_at' => '12:00']);
});

it('retorna array vazio para dia sem schedule', function () {
    $tenant = Tenant::factory()->create();
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 60]);

    // Use a date where the professional has no schedule entry
    $date = now()->addDays(10);

    // Explicitly ensure there is no schedule for this day_of_week
    $response = $this->getJson("/api/v1/salao/{$tenant->slug}/availability?professional_id={$professional->id}&service_id={$service->id}&date={$date->format('Y-m-d')}");

    $response->assertOk()
        ->assertJsonCount(0, 'data');
});

it('retorna array vazio para dia bloqueado', function () {
    $tenant = Tenant::factory()->create();
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 60]);

    $date = now()->next('Tuesday');

    Schedule::factory()->create([
        'tenant_id' => $tenant->id,
        'professional_id' => $professional->id,
        'day_of_week' => $date->dayOfWeek,
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
    ]);

    BlockedDate::factory()->create([
        'tenant_id' => $tenant->id,
        'professional_id' => $professional->id,
        'date' => $date->format('Y-m-d'),
    ]);

    $response = $this->getJson("/api/v1/salao/{$tenant->slug}/availability?professional_id={$professional->id}&service_id={$service->id}&date={$date->format('Y-m-d')}");

    $response->assertOk()
        ->assertJsonCount(0, 'data');
});

it('slot ocupado por agendamento existente nao aparece na disponibilidade', function () {
    $tenant = Tenant::factory()->create();
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 60]);

    $date = now()->next('Wednesday');

    Schedule::factory()->create([
        'tenant_id' => $tenant->id,
        'professional_id' => $professional->id,
        'day_of_week' => $date->dayOfWeek,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
    ]);

    // Occupy the 09:00 slot
    Appointment::factory()->create([
        'tenant_id' => $tenant->id,
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'starts_at' => $date->copy()->setHour(9)->setMinute(0)->setSecond(0),
        'ends_at' => $date->copy()->setHour(10)->setMinute(0)->setSecond(0),
        'status' => 'confirmed',
        'price' => 5000,
    ]);

    $response = $this->getJson("/api/v1/salao/{$tenant->slug}/availability?professional_id={$professional->id}&service_id={$service->id}&date={$date->format('Y-m-d')}");

    $response->assertOk()
        ->assertJsonCount(2, 'data');

    $response->assertJsonFragment(['starts_at' => '10:00', 'ends_at' => '11:00']);
    $response->assertJsonFragment(['starts_at' => '11:00', 'ends_at' => '12:00']);
});
