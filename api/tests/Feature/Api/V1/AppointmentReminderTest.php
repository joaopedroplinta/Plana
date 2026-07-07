<?php

use App\Models\Appointment;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AppointmentReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function reminderAppointment(array $overrides = []): Appointment
{
    $tenant = Tenant::factory()->create();
    $client = User::factory()->create();
    $tenant->users()->attach($client->id, ['role' => 'client']);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id]);

    return Appointment::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'status' => 'confirmed',
        'starts_at' => now()->addHours(23),
        'ends_at' => now()->addHours(24),
    ], $overrides));
}

it('envia lembrete para agendamento ~24h antes e marca reminder_sent_at', function () {
    Notification::fake();

    $appointment = reminderAppointment();

    $this->artisan('appointments:send-reminders')->assertSuccessful();

    Notification::assertSentTo($appointment->client, AppointmentReminder::class);
    expect($appointment->fresh()->reminder_sent_at)->not->toBeNull();
});

it('nao envia duas vezes para o mesmo agendamento', function () {
    Notification::fake();

    reminderAppointment();

    $this->artisan('appointments:send-reminders')->assertSuccessful();
    $this->artisan('appointments:send-reminders')->assertSuccessful();

    Notification::assertCount(1);
});

it('nao envia para agendamento cancelado', function () {
    Notification::fake();

    reminderAppointment(['status' => 'cancelled']);

    $this->artisan('appointments:send-reminders')->assertSuccessful();

    Notification::assertNothingSent();
});

it('nao envia para agendamento fora da janela de 22-24h', function () {
    Notification::fake();

    reminderAppointment([
        'starts_at' => now()->addHours(48),
        'ends_at' => now()->addHours(49),
    ]);
    reminderAppointment([
        'starts_at' => now()->addHours(2),
        'ends_at' => now()->addHours(3),
    ]);

    $this->artisan('appointments:send-reminders')->assertSuccessful();

    Notification::assertNothingSent();
});
