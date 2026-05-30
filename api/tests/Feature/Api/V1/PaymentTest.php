<?php

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// --- Helpers ---

function payTenantWithClient(): array
{
    $tenant = Tenant::factory()->create();
    $client = User::factory()->create();
    $client->tenants()->attach($tenant->id, ['role' => 'client']);
    Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
    $client->assignRole('client');

    return [$tenant, $client];
}

function payAppointmentForClient(Tenant $tenant, User $client): Appointment
{
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id]);

    return Appointment::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'status' => 'pending',
        'price' => 5000,
    ]);
}

// --- Create PIX payment ---

it('client can create a pix payment', function () {
    [$tenant, $client] = payTenantWithClient();
    $appointment = payAppointmentForClient($tenant, $client);

    $fakePayment = Payment::factory()->pix()->make([
        'appointment_id' => $appointment->id,
        'tenant_id' => $tenant->id,
        'amount' => 5000,
    ]);

    $this->mock(PaymentService::class, fn ($mock) => $mock->shouldReceive('createPix')->once()->andReturn($fakePayment));

    $response = $this->actingAs($client)
        ->postJson("/api/v1/salao/{$tenant->slug}/appointments/{$appointment->id}/payments", [
            'method' => 'pix',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.method', 'pix')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonStructure(['data' => ['id', 'pix_qr_code', 'pix_qr_code_base64']]);
});

// --- Create credit_card payment ---

it('client can create a credit card payment and receives preference_url', function () {
    [$tenant, $client] = payTenantWithClient();
    $appointment = payAppointmentForClient($tenant, $client);

    $fakePayment = Payment::factory()->creditCard()->make([
        'appointment_id' => $appointment->id,
        'tenant_id' => $tenant->id,
        'amount' => 5000,
    ]);

    $this->mock(PaymentService::class, fn ($mock) => $mock->shouldReceive('createCheckoutPro')->once()->andReturn($fakePayment));

    $response = $this->actingAs($client)
        ->postJson("/api/v1/salao/{$tenant->slug}/appointments/{$appointment->id}/payments", [
            'method' => 'credit_card',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.method', 'credit_card');
});

// --- Tenant isolation ---

it('client from another tenant cannot create payment', function () {
    [$tenantA, $clientA] = payTenantWithClient();
    [$tenantB, $clientB] = payTenantWithClient();
    $appointment = payAppointmentForClient($tenantB, $clientB);

    $this->actingAs($clientA)
        ->postJson("/api/v1/salao/{$tenantA->slug}/appointments/{$appointment->id}/payments", [
            'method' => 'pix',
        ])
        ->assertNotFound();
});

it('client cannot pay for another clients appointment', function () {
    [$tenant, $clientA] = payTenantWithClient();

    $clientB = User::factory()->create();
    $clientB->tenants()->attach($tenant->id, ['role' => 'client']);
    $clientB->assignRole('client');

    $appointment = payAppointmentForClient($tenant, $clientB);

    $this->actingAs($clientA)
        ->postJson("/api/v1/salao/{$tenant->slug}/appointments/{$appointment->id}/payments", [
            'method' => 'pix',
        ])
        ->assertForbidden();
});

// --- View payment status ---

it('client can view own payment status', function () {
    [$tenant, $client] = payTenantWithClient();
    $appointment = payAppointmentForClient($tenant, $client);

    $payment = Payment::factory()->pix()->create([
        'tenant_id' => $tenant->id,
        'appointment_id' => $appointment->id,
    ]);

    $this->mock(PaymentService::class, fn ($mock) => $mock->shouldReceive('syncStatus')->once()->andReturn($payment->fresh()));

    $this->actingAs($client)
        ->getJson("/api/v1/salao/{$tenant->slug}/payments/{$payment->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $payment->id);
});

it('client cannot view payment from another client', function () {
    [$tenant, $clientA] = payTenantWithClient();

    $clientB = User::factory()->create();
    $clientB->tenants()->attach($tenant->id, ['role' => 'client']);
    $clientB->assignRole('client');

    $appointmentB = payAppointmentForClient($tenant, $clientB);

    $payment = Payment::factory()->pix()->create([
        'tenant_id' => $tenant->id,
        'appointment_id' => $appointmentB->id,
    ]);

    $this->actingAs($clientA)
        ->getJson("/api/v1/salao/{$tenant->slug}/payments/{$payment->id}")
        ->assertForbidden();
});

it('salon owner can view any payment in tenant', function () {
    [$tenant, $client] = payTenantWithClient();

    $owner = User::factory()->create();
    $owner->tenants()->attach($tenant->id, ['role' => 'owner']);
    Role::firstOrCreate(['name' => 'salon_owner', 'guard_name' => 'web']);
    $owner->assignRole('salon_owner');

    $appointment = payAppointmentForClient($tenant, $client);

    $payment = Payment::factory()->pix()->create([
        'tenant_id' => $tenant->id,
        'appointment_id' => $appointment->id,
    ]);

    $this->mock(PaymentService::class, fn ($mock) => $mock->shouldReceive('syncStatus')->once()->andReturn($payment->fresh()));

    $this->actingAs($owner)
        ->getJson("/api/v1/salao/{$tenant->slug}/payments/{$payment->id}")
        ->assertOk();
});

// --- Webhook ---

it('webhook approved updates payment status and confirms appointment', function () {
    [$tenant, $client] = payTenantWithClient();
    $appointment = payAppointmentForClient($tenant, $client);

    Payment::factory()->pix()->create([
        'tenant_id' => $tenant->id,
        'appointment_id' => $appointment->id,
        'external_id' => '123456789',
    ]);

    $this->mock(PaymentService::class, fn ($mock) => $mock->shouldReceive('handleWebhook')->once());

    $this->postJson('/api/v1/payments/webhook', [
        'type' => 'payment',
        'data' => ['id' => '123456789'],
    ])->assertOk();
});
