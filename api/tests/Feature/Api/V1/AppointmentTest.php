<?php

use App\Models\Appointment;
use App\Models\BlockedDate;
use App\Models\Professional;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AppointmentBooked;
use App\Notifications\AppointmentCancelled;
use App\Notifications\AppointmentConfirmed;
use App\Notifications\NewAppointmentReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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

function apptFullWeekSchedule(Tenant $tenant, Professional $professional): void
{
    foreach (range(0, 6) as $day) {
        Schedule::factory()->create([
            'tenant_id' => $tenant->id,
            'professional_id' => $professional->id,
            'day_of_week' => $day,
            'start_time' => '08:00:00',
            'end_time' => '20:00:00',
        ]);
    }
}

// --- Criar agendamento ---

it('cria agendamento com slot valido e retorna 201', function () {
    $tenant = Tenant::factory()->create();
    $client = apptClient($tenant);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 60, 'price' => 5000]);
    apptFullWeekSchedule($tenant, $professional);

    $startsAt = now()->addDay()->setHour(10)->setMinute(0)->setSecond(0)->toIso8601String();

    $response = $this->actingAs($client)->postJson("/api/v1/negocio/{$tenant->slug}/appointments", [
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

it('congela o sinal no agendamento herdando o padrão percentual do salão', function () {
    $tenant = Tenant::factory()->create(['settings' => ['deposit_type' => 'percentage', 'deposit_value' => 20]]);
    $client = apptClient($tenant);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 60, 'price' => 5000]);
    apptFullWeekSchedule($tenant, $professional);

    $startsAt = now()->addDay()->setHour(10)->setMinute(0)->setSecond(0)->toIso8601String();

    $response = $this->actingAs($client)->postJson("/api/v1/negocio/{$tenant->slug}/appointments", [
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'starts_at' => $startsAt,
    ]);

    // 20% de R$50 = R$10 de sinal; total continua R$50; resta R$40.
    $response->assertCreated()
        ->assertJsonPath('data.price', 5000)
        ->assertJsonPath('data.deposit_amount', 1000)
        ->assertJsonPath('data.balance_due', 4000);
});

it('override de sinal do serviço vence o padrão do salão na reserva', function () {
    $tenant = Tenant::factory()->create(['settings' => ['deposit_type' => 'percentage', 'deposit_value' => 20]]);
    $client = apptClient($tenant);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create([
        'tenant_id' => $tenant->id, 'duration_minutes' => 60, 'price' => 5000,
        'deposit_type' => 'fixed', 'deposit_value' => 3000,
    ]);
    apptFullWeekSchedule($tenant, $professional);

    $startsAt = now()->addDay()->setHour(10)->setMinute(0)->setSecond(0)->toIso8601String();

    $response = $this->actingAs($client)->postJson("/api/v1/negocio/{$tenant->slug}/appointments", [
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'starts_at' => $startsAt,
    ]);

    $response->assertCreated()->assertJsonPath('data.deposit_amount', 3000);
});

it('sem sinal configurado, o agendamento cobra o valor cheio (deposit null)', function () {
    $tenant = Tenant::factory()->create();
    $client = apptClient($tenant);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 60, 'price' => 5000]);
    apptFullWeekSchedule($tenant, $professional);

    $startsAt = now()->addDay()->setHour(10)->setMinute(0)->setSecond(0)->toIso8601String();

    $response = $this->actingAs($client)->postJson("/api/v1/negocio/{$tenant->slug}/appointments", [
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'starts_at' => $startsAt,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.deposit_amount', null)
        ->assertJsonPath('data.balance_due', 0);
});

it('rejeita agendamento em slot ocupado com 422', function () {
    $tenant = Tenant::factory()->create();
    $client = apptClient($tenant);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 60, 'price' => 5000]);
    apptFullWeekSchedule($tenant, $professional);

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

    $response = $this->actingAs($client)->postJson("/api/v1/negocio/{$tenant->slug}/appointments", [
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

    $response = $this->actingAs($client)->postJson("/api/v1/negocio/{$tenantA->slug}/appointments", [
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

    $response = $this->actingAs($owner)->getJson("/api/v1/negocio/{$tenant->slug}/appointments");

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

    $response = $this->actingAs($clientA)->getJson("/api/v1/negocio/{$tenant->slug}/appointments");

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
        ->patchJson("/api/v1/negocio/{$tenant->slug}/appointments/{$appointment->id}/confirm");

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
        ->patchJson("/api/v1/negocio/{$tenant->slug}/appointments/{$appointment->id}/confirm");

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
        ->patchJson("/api/v1/negocio/{$tenant->slug}/appointments/{$appointment->id}/cancel");

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
        ->patchJson("/api/v1/negocio/{$tenant->slug}/appointments/{$appointment->id}/cancel");

    $response->assertForbidden();
});

// --- Concluir agendamento ---

it('salon_owner conclui agendamento confirmado com sucesso', function () {
    $tenant = Tenant::factory()->create();
    $owner = apptOwner($tenant);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 60]);
    $appointment = Appointment::factory()->confirmed()->create([
        'tenant_id' => $tenant->id,
        'professional_id' => $professional->id,
        'service_id' => $service->id,
    ]);

    $response = $this->actingAs($owner)
        ->patchJson("/api/v1/negocio/{$tenant->slug}/appointments/{$appointment->id}/complete");

    $response->assertOk()
        ->assertJsonPath('data.status', 'completed');

    $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'completed']);
});

it('nao conclui agendamento ja concluido', function () {
    $tenant = Tenant::factory()->create();
    $owner = apptOwner($tenant);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 60]);
    $appointment = Appointment::factory()->completed()->create([
        'tenant_id' => $tenant->id,
        'professional_id' => $professional->id,
        'service_id' => $service->id,
    ]);

    $this->actingAs($owner)
        ->patchJson("/api/v1/negocio/{$tenant->slug}/appointments/{$appointment->id}/complete")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
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

    $response = $this->getJson("/api/v1/negocio/{$tenant->slug}/availability?professional_id={$professional->id}&service_id={$service->id}&date={$date->format('Y-m-d')}");

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
    $response = $this->getJson("/api/v1/negocio/{$tenant->slug}/availability?professional_id={$professional->id}&service_id={$service->id}&date={$date->format('Y-m-d')}");

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

    $response = $this->getJson("/api/v1/negocio/{$tenant->slug}/availability?professional_id={$professional->id}&service_id={$service->id}&date={$date->format('Y-m-d')}");

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

    $response = $this->getJson("/api/v1/negocio/{$tenant->slug}/availability?professional_id={$professional->id}&service_id={$service->id}&date={$date->format('Y-m-d')}");

    $response->assertOk()
        ->assertJsonCount(2, 'data');

    $response->assertJsonFragment(['starts_at' => '10:00', 'ends_at' => '11:00']);
    $response->assertJsonFragment(['starts_at' => '11:00', 'ends_at' => '12:00']);
});

it('ignore_appointment_id faz o proprio slot do agendamento aparecer disponivel na disponibilidade', function () {
    $tenant = Tenant::factory()->create();
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 60]);

    $date = now()->next('Thursday');

    Schedule::factory()->create([
        'tenant_id' => $tenant->id,
        'professional_id' => $professional->id,
        'day_of_week' => $date->dayOfWeek,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
    ]);

    $appointment = Appointment::factory()->create([
        'tenant_id' => $tenant->id,
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'starts_at' => $date->copy()->setHour(9)->setMinute(0)->setSecond(0),
        'ends_at' => $date->copy()->setHour(10)->setMinute(0)->setSecond(0),
        'status' => 'confirmed',
        'price' => 5000,
    ]);

    // Sem ignore_appointment_id, o próprio slot aparece ocupado.
    $withoutIgnore = $this->getJson("/api/v1/negocio/{$tenant->slug}/availability?professional_id={$professional->id}&service_id={$service->id}&date={$date->format('Y-m-d')}");
    $withoutIgnore->assertOk()->assertJsonCount(2, 'data');
    $withoutIgnore->assertJsonMissing(['starts_at' => '09:00', 'ends_at' => '10:00']);

    // Com ignore_appointment_id apontando para o próprio agendamento, o slot volta a aparecer livre.
    $withIgnore = $this->getJson("/api/v1/negocio/{$tenant->slug}/availability?professional_id={$professional->id}&service_id={$service->id}&date={$date->format('Y-m-d')}&ignore_appointment_id={$appointment->id}");
    $withIgnore->assertOk()->assertJsonCount(3, 'data');
    $withIgnore->assertJsonFragment(['starts_at' => '09:00', 'ends_at' => '10:00']);
});

it('nao lista slots para profissional inativo', function () {
    $tenant = Tenant::factory()->create();
    $professional = Professional::factory()->inactive()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 60]);

    $date = now()->next('Monday');

    Schedule::factory()->create([
        'tenant_id' => $tenant->id,
        'professional_id' => $professional->id,
        'day_of_week' => $date->dayOfWeek,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
    ]);

    $response = $this->getJson("/api/v1/negocio/{$tenant->slug}/availability?professional_id={$professional->id}&service_id={$service->id}&date={$date->format('Y-m-d')}");

    $response->assertOk()->assertJsonCount(0, 'data');
});

it('nao lista slots para servico inativo', function () {
    $tenant = Tenant::factory()->create();
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->inactive()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 60]);

    $date = now()->next('Monday');

    Schedule::factory()->create([
        'tenant_id' => $tenant->id,
        'professional_id' => $professional->id,
        'day_of_week' => $date->dayOfWeek,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
    ]);

    $response = $this->getJson("/api/v1/negocio/{$tenant->slug}/availability?professional_id={$professional->id}&service_id={$service->id}&date={$date->format('Y-m-d')}");

    $response->assertOk()->assertJsonCount(0, 'data');
});

it('nao lista slots que ja passaram no dia de hoje', function () {
    // Fixa o "agora" às 10:00 para evitar flakiness perto da virada do dia.
    $now = now()->startOfDay()->addHours(10);
    $this->travelTo($now);

    $tenant = Tenant::factory()->create();
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 60]);

    Schedule::factory()->create([
        'tenant_id' => $tenant->id,
        'professional_id' => $professional->id,
        'day_of_week' => $now->dayOfWeek,
        'start_time' => '08:00:00',
        'end_time' => '12:00:00',
    ]);

    $response = $this->getJson("/api/v1/negocio/{$tenant->slug}/availability?professional_id={$professional->id}&service_id={$service->id}&date={$now->format('Y-m-d')}");

    $response->assertOk()->assertJsonCount(1, 'data');
    $response->assertJsonFragment(['starts_at' => '11:00', 'ends_at' => '12:00']);

    $this->travelBack();
});

// --- Regras de negócio: horário de atendimento ---

it('rejeita agendamento fora do expediente do profissional com 422', function () {
    $tenant = Tenant::factory()->create();
    $client = apptClient($tenant);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 60, 'price' => 5000]);

    $date = now()->addDay();
    Schedule::factory()->create([
        'tenant_id' => $tenant->id,
        'professional_id' => $professional->id,
        'day_of_week' => $date->dayOfWeek,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
    ]);

    $response = $this->actingAs($client)->postJson("/api/v1/negocio/{$tenant->slug}/appointments", [
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'starts_at' => $date->copy()->setTime(15, 0)->toIso8601String(),
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['starts_at']);
});

it('rejeita agendamento em dia sem schedule com 422', function () {
    $tenant = Tenant::factory()->create();
    $client = apptClient($tenant);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 60, 'price' => 5000]);

    $response = $this->actingAs($client)->postJson("/api/v1/negocio/{$tenant->slug}/appointments", [
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'starts_at' => now()->addDay()->setTime(10, 0)->toIso8601String(),
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['starts_at']);
});

it('rejeita agendamento em data bloqueada com 422', function () {
    $tenant = Tenant::factory()->create();
    $client = apptClient($tenant);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 60, 'price' => 5000]);
    apptFullWeekSchedule($tenant, $professional);

    $date = now()->addDay();
    BlockedDate::factory()->create([
        'tenant_id' => $tenant->id,
        'professional_id' => $professional->id,
        'date' => $date->format('Y-m-d'),
    ]);

    $response = $this->actingAs($client)->postJson("/api/v1/negocio/{$tenant->slug}/appointments", [
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'starts_at' => $date->copy()->setTime(10, 0)->toIso8601String(),
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['starts_at']);
});

// --- Regras de negócio: grade de horários e disponibilidade do profissional/serviço ---

it('rejeita agendamento com profissional inativo com 422', function () {
    $tenant = Tenant::factory()->create();
    $client = apptClient($tenant);
    $professional = Professional::factory()->inactive()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 60, 'price' => 5000]);
    apptFullWeekSchedule($tenant, $professional);

    $response = $this->actingAs($client)->postJson("/api/v1/negocio/{$tenant->slug}/appointments", [
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'starts_at' => now()->addDay()->setTime(10, 0)->toIso8601String(),
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['starts_at']);
});

it('rejeita agendamento com servico inativo com 422', function () {
    $tenant = Tenant::factory()->create();
    $client = apptClient($tenant);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->inactive()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 60, 'price' => 5000]);
    apptFullWeekSchedule($tenant, $professional);

    $response = $this->actingAs($client)->postJson("/api/v1/negocio/{$tenant->slug}/appointments", [
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'starts_at' => now()->addDay()->setTime(10, 0)->toIso8601String(),
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['starts_at']);
});

it('rejeita reagendamento quando profissional fica inativo com 422', function () {
    $tenant = Tenant::factory()->create();
    $client = apptClient($tenant);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 60, 'price' => 5000]);
    apptFullWeekSchedule($tenant, $professional);

    $appointment = Appointment::factory()->pending()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'starts_at' => now()->addDay()->setTime(10, 0),
        'ends_at' => now()->addDay()->setTime(11, 0),
    ]);

    $professional->update(['active' => false]);

    $response = $this->actingAs($client)
        ->patchJson("/api/v1/negocio/{$tenant->slug}/appointments/{$appointment->id}/reschedule", [
            'starts_at' => now()->addDays(2)->setTime(10, 0)->toIso8601String(),
        ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['starts_at']);
});

it('rejeita agendamento em horario fora da grade de slots com 422', function () {
    $tenant = Tenant::factory()->create();
    $client = apptClient($tenant);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 60, 'price' => 5000]);
    apptFullWeekSchedule($tenant, $professional);

    // Expediente 08:00-20:00, grade de 60min: 08:00, 09:00, 10:00... 10:07 não é um horário válido.
    $response = $this->actingAs($client)->postJson("/api/v1/negocio/{$tenant->slug}/appointments", [
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'starts_at' => now()->addDay()->setTime(10, 7)->toIso8601String(),
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['starts_at']);
});

it('rejeita reagendamento em horario fora da grade de slots com 422', function () {
    $tenant = Tenant::factory()->create();
    $client = apptClient($tenant);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 60, 'price' => 5000]);
    apptFullWeekSchedule($tenant, $professional);

    $appointment = Appointment::factory()->pending()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'starts_at' => now()->addDay()->setTime(10, 0),
        'ends_at' => now()->addDay()->setTime(11, 0),
    ]);

    $response = $this->actingAs($client)
        ->patchJson("/api/v1/negocio/{$tenant->slug}/appointments/{$appointment->id}/reschedule", [
            'starts_at' => now()->addDays(2)->setTime(14, 15)->toIso8601String(),
        ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['starts_at']);
});

// --- Regras de negócio: vínculo automático de cliente ---

it('usuario de outro tenant pode agendar e vira cliente do salao', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $clientOfA = apptClient($tenantA);
    $professional = Professional::factory()->create(['tenant_id' => $tenantB->id]);
    $service = Service::factory()->create(['tenant_id' => $tenantB->id, 'duration_minutes' => 60, 'price' => 5000]);
    apptFullWeekSchedule($tenantB, $professional);

    $response = $this->actingAs($clientOfA)->postJson("/api/v1/negocio/{$tenantB->slug}/appointments", [
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'starts_at' => now()->addDay()->setTime(10, 0)->toIso8601String(),
    ]);

    $response->assertCreated();

    $this->assertDatabaseHas('tenant_user', [
        'tenant_id' => $tenantB->id,
        'user_id' => $clientOfA->id,
        'role' => 'client',
    ]);
});

it('salon_owner de outro salao que agenda vira client e nao ve agendamentos alheios', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $ownerOfA = apptOwner($tenantA);
    $otherClient = apptClient($tenantB);
    $professional = Professional::factory()->create(['tenant_id' => $tenantB->id]);
    $service = Service::factory()->create(['tenant_id' => $tenantB->id, 'duration_minutes' => 60, 'price' => 5000]);
    apptFullWeekSchedule($tenantB, $professional);

    Appointment::factory(3)->create([
        'tenant_id' => $tenantB->id,
        'client_id' => $otherClient->id,
        'professional_id' => $professional->id,
        'service_id' => $service->id,
    ]);

    $this->actingAs($ownerOfA)->postJson("/api/v1/negocio/{$tenantB->slug}/appointments", [
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'starts_at' => now()->addDay()->setTime(10, 0)->toIso8601String(),
    ])->assertCreated();

    // No salão B ele é apenas client: lista só o próprio agendamento.
    $response = $this->actingAs($ownerOfA)->getJson("/api/v1/negocio/{$tenantB->slug}/appointments");

    $response->assertOk()->assertJsonCount(1, 'data');
});

// --- Regras de negócio: transições de status ---

it('nao confirma agendamento cancelado', function () {
    $tenant = Tenant::factory()->create();
    $owner = apptOwner($tenant);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id]);
    $appointment = Appointment::factory()->create([
        'tenant_id' => $tenant->id,
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'status' => 'cancelled',
    ]);

    $this->actingAs($owner)
        ->patchJson("/api/v1/negocio/{$tenant->slug}/appointments/{$appointment->id}/confirm")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('nao cancela agendamento concluido', function () {
    $tenant = Tenant::factory()->create();
    $owner = apptOwner($tenant);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id]);
    $appointment = Appointment::factory()->create([
        'tenant_id' => $tenant->id,
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'status' => 'completed',
    ]);

    $this->actingAs($owner)
        ->patchJson("/api/v1/negocio/{$tenant->slug}/appointments/{$appointment->id}/cancel")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

// --- Regras de negócio: limite do plano starter ---

it('bloqueia agendamento acima do limite mensal do plano starter', function () {
    $tenant = Tenant::factory()->create(['plan' => 'starter']);
    $client = apptClient($tenant);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 60, 'price' => 5000]);
    apptFullWeekSchedule($tenant, $professional);

    $startsAt = now()->addMonthNoOverflow()->startOfMonth()->addDays(3)->setTime(10, 0);

    foreach (range(0, 49) as $i) {
        Appointment::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'professional_id' => $professional->id,
            'service_id' => $service->id,
            'starts_at' => $startsAt->copy()->startOfMonth()->addMinutes($i * 30),
            'ends_at' => $startsAt->copy()->startOfMonth()->addMinutes($i * 30 + 30),
            'status' => 'confirmed',
        ]);
    }

    $response = $this->actingAs($client)->postJson("/api/v1/negocio/{$tenant->slug}/appointments", [
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'starts_at' => $startsAt->toIso8601String(),
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['starts_at']);
});

it('plano pro nao tem limite mensal de agendamentos', function () {
    $tenant = Tenant::factory()->create(['plan' => 'pro']);
    $client = apptClient($tenant);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 60, 'price' => 5000]);
    apptFullWeekSchedule($tenant, $professional);

    $startsAt = now()->addMonthNoOverflow()->startOfMonth()->addDays(3)->setTime(10, 0);

    foreach (range(0, 49) as $i) {
        Appointment::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'professional_id' => $professional->id,
            'service_id' => $service->id,
            'starts_at' => $startsAt->copy()->startOfMonth()->addMinutes($i * 30),
            'ends_at' => $startsAt->copy()->startOfMonth()->addMinutes($i * 30 + 30),
            'status' => 'confirmed',
        ]);
    }

    $this->actingAs($client)->postJson("/api/v1/negocio/{$tenant->slug}/appointments", [
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'starts_at' => $startsAt->toIso8601String(),
    ])->assertCreated();
});

// --- Notificações ---

it('envia emails ao criar agendamento (cliente e owner)', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $owner = apptOwner($tenant);
    $client = apptClient($tenant);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 60, 'price' => 5000]);
    apptFullWeekSchedule($tenant, $professional);

    $this->actingAs($client)->postJson("/api/v1/negocio/{$tenant->slug}/appointments", [
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'starts_at' => now()->addDay()->setTime(10, 0)->toIso8601String(),
    ])->assertCreated();

    Notification::assertSentTo($client, AppointmentBooked::class);
    Notification::assertSentTo($owner, NewAppointmentReceived::class);
});

it('envia email ao confirmar e ao cancelar agendamento', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $owner = apptOwner($tenant);
    $client = apptClient($tenant);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id]);
    $appointment = Appointment::factory()->pending()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'professional_id' => $professional->id,
        'service_id' => $service->id,
    ]);

    $this->actingAs($owner)
        ->patchJson("/api/v1/negocio/{$tenant->slug}/appointments/{$appointment->id}/confirm")
        ->assertOk();

    Notification::assertSentTo($client, AppointmentConfirmed::class);

    $this->actingAs($owner)
        ->patchJson("/api/v1/negocio/{$tenant->slug}/appointments/{$appointment->id}/cancel")
        ->assertOk();

    Notification::assertSentTo($client, AppointmentCancelled::class);
});
