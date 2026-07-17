<?php

use App\Models\BusinessHour;
use App\Models\Professional;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function whOwner(Tenant $tenant): User
{
    Role::firstOrCreate(['name' => 'salon_owner', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('salon_owner');
    $tenant->users()->attach($user->id, ['role' => 'owner']);

    return $user;
}

function whStaff(Tenant $tenant): User
{
    Role::firstOrCreate(['name' => 'salon_staff', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('salon_staff');
    $tenant->users()->attach($user->id, ['role' => 'staff']);

    return $user;
}

/** Próxima segunda-feira futura (day_of_week = 1). */
function whNextMonday(): Carbon
{
    return Carbon::today()->next(Carbon::MONDAY);
}

// --- Horário de funcionamento do salão (business-hours) ---

it('owner sincroniza o horário de funcionamento do salão', function () {
    $tenant = Tenant::factory()->create();
    $owner = whOwner($tenant);

    $response = $this->actingAs($owner)->putJson("/api/v1/negocio/{$tenant->slug}/business-hours", [
        'days' => [
            ['day_of_week' => 1, 'is_open' => true, 'open_time' => '09:00', 'close_time' => '18:00'],
            ['day_of_week' => 0, 'is_open' => false, 'open_time' => null, 'close_time' => null],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.0.day_of_week', 0)
        ->assertJsonPath('data.1.day_of_week', 1)
        ->assertJsonPath('data.1.open_time', '09:00');

    $this->assertDatabaseHas('business_hours', [
        'tenant_id' => $tenant->id, 'day_of_week' => 1, 'is_open' => true,
    ]);
});

it('endpoint público retorna o horário de funcionamento', function () {
    $tenant = Tenant::factory()->create();
    BusinessHour::create(['tenant_id' => $tenant->id, 'day_of_week' => 1, 'is_open' => true, 'open_time' => '09:00', 'close_time' => '18:00']);

    $this->getJson("/api/v1/negocio/{$tenant->slug}/business-hours")
        ->assertOk()
        ->assertJsonPath('data.0.close_time', '18:00');
});

it('staff (não owner) não pode sincronizar o funcionamento', function () {
    $tenant = Tenant::factory()->create();
    $staff = whStaff($tenant);

    $this->actingAs($staff)->putJson("/api/v1/negocio/{$tenant->slug}/business-hours", [
        'days' => [['day_of_week' => 1, 'is_open' => true, 'open_time' => '09:00', 'close_time' => '18:00']],
    ])->assertStatus(403);
});

it('rejeita fechamento antes da abertura com 422', function () {
    $tenant = Tenant::factory()->create();
    $owner = whOwner($tenant);

    $this->actingAs($owner)->putJson("/api/v1/negocio/{$tenant->slug}/business-hours", [
        'days' => [['day_of_week' => 1, 'is_open' => true, 'open_time' => '18:00', 'close_time' => '09:00']],
    ])->assertStatus(422)->assertJsonValidationErrors(['days.0.close_time']);
});

// --- Horário de trabalho do profissional (sync em lote) ---

it('sincroniza a semana do profissional substituindo os horários antigos', function () {
    $tenant = Tenant::factory()->create();
    $owner = whOwner($tenant);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    Schedule::create(['tenant_id' => $tenant->id, 'professional_id' => $professional->id, 'day_of_week' => 1, 'start_time' => '08:00', 'end_time' => '12:00']);

    $response = $this->actingAs($owner)->putJson("/api/v1/negocio/{$tenant->slug}/professionals/{$professional->id}/schedules", [
        'schedules' => [
            ['day_of_week' => 2, 'start_time' => '10:00', 'end_time' => '19:00'],
            ['day_of_week' => 3, 'start_time' => '10:00', 'end_time' => '19:00'],
        ],
    ]);

    $response->assertOk()->assertJsonCount(2, 'data');

    // A segunda-feira antiga foi removida; só terça e quarta permanecem.
    expect(Schedule::where('professional_id', $professional->id)->pluck('day_of_week')->sort()->values()->all())
        ->toBe([2, 3]);
});

// --- Disponibilidade: interseção com o funcionamento do salão ---

function whSetupAvailability(Tenant $tenant): array
{
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 60, 'price' => 5000]);
    // Profissional trabalha segunda 08:00–18:00.
    Schedule::create(['tenant_id' => $tenant->id, 'professional_id' => $professional->id, 'day_of_week' => 1, 'start_time' => '08:00', 'end_time' => '18:00']);

    return [$professional, $service];
}

it('a disponibilidade respeita o horário do salão (interseção)', function () {
    $tenant = Tenant::factory()->create();
    [$professional, $service] = whSetupAvailability($tenant);
    // Salão abre só das 09:00 às 12:00 na segunda.
    BusinessHour::create(['tenant_id' => $tenant->id, 'day_of_week' => 1, 'is_open' => true, 'open_time' => '09:00', 'close_time' => '12:00']);

    $date = whNextMonday()->format('Y-m-d');
    $response = $this->getJson("/api/v1/negocio/{$tenant->slug}/availability?professional_id={$professional->id}&service_id={$service->id}&date={$date}");

    // 09–10, 10–11, 11–12 → exatamente 3 slots (limitado pelo salão, não pelo profissional).
    $response->assertOk()->assertJsonCount(3, 'data');
    $response->assertJsonFragment(['starts_at' => '09:00', 'ends_at' => '10:00']);
    $response->assertJsonMissing(['starts_at' => '08:00', 'ends_at' => '09:00']);
});

it('salão fechado no dia zera a disponibilidade mesmo com o profissional livre', function () {
    $tenant = Tenant::factory()->create();
    [$professional, $service] = whSetupAvailability($tenant);
    BusinessHour::create(['tenant_id' => $tenant->id, 'day_of_week' => 1, 'is_open' => false]);

    $date = whNextMonday()->format('Y-m-d');
    $this->getJson("/api/v1/negocio/{$tenant->slug}/availability?professional_id={$professional->id}&service_id={$service->id}&date={$date}")
        ->assertOk()->assertJsonCount(0, 'data');
});

it('sem business_hours configurado, a disponibilidade vem só do profissional (legado)', function () {
    $tenant = Tenant::factory()->create();
    [$professional, $service] = whSetupAvailability($tenant);
    // Nenhuma linha de business_hours.

    $date = whNextMonday()->format('Y-m-d');
    // 08:00..17:00, passos de 60min = 10 slots.
    $this->getJson("/api/v1/negocio/{$tenant->slug}/availability?professional_id={$professional->id}&service_id={$service->id}&date={$date}")
        ->assertOk()->assertJsonCount(10, 'data');
});
