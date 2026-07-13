<?php

use App\Models\Appointment;
use App\Models\Professional;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AppointmentRescheduled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function lcOwner(Tenant $tenant): User
{
    Role::firstOrCreate(['name' => 'salon_owner', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('salon_owner');
    $tenant->users()->attach($user->id, ['role' => 'owner']);

    return $user;
}

function lcClient(Tenant $tenant): User
{
    Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('client');
    $tenant->users()->attach($user->id, ['role' => 'client']);

    return $user;
}

/** @return array{Tenant, User, User, Appointment} */
function lcSetup(array $apptOverrides = []): array
{
    $tenant = Tenant::factory()->create(['plan' => 'pro']);
    $owner = lcOwner($tenant);
    $client = lcClient($tenant);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 60]);

    foreach (range(0, 6) as $day) {
        Schedule::factory()->create([
            'tenant_id' => $tenant->id,
            'professional_id' => $professional->id,
            'day_of_week' => $day,
            'start_time' => '08:00:00',
            'end_time' => '20:00:00',
        ]);
    }

    $appointment = Appointment::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'status' => 'confirmed',
        'starts_at' => now()->addDay()->setTime(10, 0),
        'ends_at' => now()->addDay()->setTime(11, 0),
    ], $apptOverrides));

    return [$tenant, $owner, $client, $appointment];
}

// --- No-show ---

it('owner marca falta em agendamento passado', function () {
    [$tenant, $owner, , $appointment] = lcSetup([
        'starts_at' => now()->subHours(2),
        'ends_at' => now()->subHour(),
    ]);

    $this->actingAs($owner)
        ->patchJson("/api/v1/negocio/{$tenant->slug}/appointments/{$appointment->id}/no-show")
        ->assertOk()
        ->assertJsonPath('data.status', 'no_show');
});

it('nao marca falta em agendamento futuro', function () {
    [$tenant, $owner, , $appointment] = lcSetup();

    $this->actingAs($owner)
        ->patchJson("/api/v1/negocio/{$tenant->slug}/appointments/{$appointment->id}/no-show")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('cliente nao pode marcar falta', function () {
    [$tenant, , $client, $appointment] = lcSetup([
        'starts_at' => now()->subHours(2),
        'ends_at' => now()->subHour(),
    ]);

    $this->actingAs($client)
        ->patchJson("/api/v1/negocio/{$tenant->slug}/appointments/{$appointment->id}/no-show")
        ->assertForbidden();
});

it('nao marca falta em agendamento cancelado', function () {
    [$tenant, $owner, , $appointment] = lcSetup([
        'status' => 'cancelled',
        'starts_at' => now()->subHours(2),
        'ends_at' => now()->subHour(),
    ]);

    $this->actingAs($owner)
        ->patchJson("/api/v1/negocio/{$tenant->slug}/appointments/{$appointment->id}/no-show")
        ->assertUnprocessable();
});

// --- Reagendamento ---

it('cliente remarca o proprio agendamento e status volta para pending', function () {
    Notification::fake();

    [$tenant, , $client, $appointment] = lcSetup();
    $newStart = now()->addDays(2)->setTime(14, 0);

    $response = $this->actingAs($client)
        ->patchJson("/api/v1/negocio/{$tenant->slug}/appointments/{$appointment->id}/reschedule", [
            'starts_at' => $newStart->toIso8601String(),
        ]);

    $response->assertOk()->assertJsonPath('data.status', 'pending');

    $fresh = $appointment->fresh();
    expect($fresh->starts_at->format('Y-m-d H:i'))->toBe($newStart->format('Y-m-d H:i'))
        ->and($fresh->ends_at->diffInMinutes($fresh->starts_at))->toBe(-60.0);

    Notification::assertSentTo($client, AppointmentRescheduled::class);
});

it('owner remarca mantendo o status confirmado', function () {
    Notification::fake();

    [$tenant, $owner, , $appointment] = lcSetup();

    $this->actingAs($owner)
        ->patchJson("/api/v1/negocio/{$tenant->slug}/appointments/{$appointment->id}/reschedule", [
            'starts_at' => now()->addDays(2)->setTime(9, 0)->toIso8601String(),
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'confirmed');
});

it('remarcar para o proprio horario atual nao conflita consigo mesmo', function () {
    [$tenant, , $client, $appointment] = lcSetup();

    // Mantém o mesmo horário (10:00) — sem ignoreAppointmentId isso
    // conflitaria com o próprio registro no banco.
    $this->actingAs($client)
        ->patchJson("/api/v1/negocio/{$tenant->slug}/appointments/{$appointment->id}/reschedule", [
            'starts_at' => $appointment->starts_at->copy()->toIso8601String(),
        ])
        ->assertOk();
});

it('remarcar para slot ocupado por outro agendamento retorna 422', function () {
    [$tenant, , $client, $appointment] = lcSetup();

    Appointment::factory()->create([
        'tenant_id' => $tenant->id,
        'professional_id' => $appointment->professional_id,
        'service_id' => $appointment->service_id,
        'status' => 'confirmed',
        'starts_at' => now()->addDays(2)->setTime(15, 0),
        'ends_at' => now()->addDays(2)->setTime(16, 0),
    ]);

    $this->actingAs($client)
        ->patchJson("/api/v1/negocio/{$tenant->slug}/appointments/{$appointment->id}/reschedule", [
            'starts_at' => now()->addDays(2)->setTime(15, 0)->toIso8601String(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['starts_at']);
});

it('cliente nao remarca agendamento de outro cliente', function () {
    [$tenant, , , $appointment] = lcSetup();
    $otherClient = lcClient($tenant);

    $this->actingAs($otherClient)
        ->patchJson("/api/v1/negocio/{$tenant->slug}/appointments/{$appointment->id}/reschedule", [
            'starts_at' => now()->addDays(2)->setTime(9, 0)->toIso8601String(),
        ])
        ->assertForbidden();
});

it('nao remarca agendamento cancelado', function () {
    [$tenant, , $client, $appointment] = lcSetup(['status' => 'cancelled']);

    $this->actingAs($client)
        ->patchJson("/api/v1/negocio/{$tenant->slug}/appointments/{$appointment->id}/reschedule", [
            'starts_at' => now()->addDays(2)->setTime(9, 0)->toIso8601String(),
        ])
        ->assertUnprocessable();
});

it('remarcar zera o reminder_sent_at para novo lembrete', function () {
    [$tenant, , $client, $appointment] = lcSetup();
    $appointment->update(['reminder_sent_at' => now()]);

    $this->actingAs($client)
        ->patchJson("/api/v1/negocio/{$tenant->slug}/appointments/{$appointment->id}/reschedule", [
            'starts_at' => now()->addDays(2)->setTime(9, 0)->toIso8601String(),
        ])
        ->assertOk();

    expect($appointment->fresh()->reminder_sent_at)->toBeNull();
});
