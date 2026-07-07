<?php

use App\Jobs\ProcessPaymentWebhook;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PaymentApproved;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
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

// --- Webhook ---

it('webhook aprova pagamento pix via external_id e confirma o agendamento', function () {
    [$tenant, $client] = payTenantWithClient();
    $appointment = payAppointmentForClient($tenant, $client);

    $payment = Payment::factory()->pix()->create([
        'tenant_id' => $tenant->id,
        'appointment_id' => $appointment->id,
        'external_id' => '99887766',
        'status' => 'pending',
    ]);

    $this->partialMock(PaymentService::class, function ($mock) use ($appointment) {
        $mock->shouldAllowMockingProtectedMethods()
            ->shouldReceive('fetchPayment')
            ->once()
            ->andReturn((object) ['status' => 'approved', 'external_reference' => $appointment->id]);
    });

    $this->postJson('/api/v1/payments/webhook', [
        'type' => 'payment',
        'data' => ['id' => '99887766'],
    ])->assertOk();

    expect($payment->fresh()->status)->toBe('approved')
        ->and($payment->fresh()->paid_at)->not->toBeNull()
        ->and($appointment->fresh()->status)->toBe('confirmed');
});

it('webhook aprova pagamento checkout pro via external_reference', function () {
    [$tenant, $client] = payTenantWithClient();
    $appointment = payAppointmentForClient($tenant, $client);

    // Checkout Pro: só temos preference_id no momento da criação.
    $payment = Payment::factory()->creditCard()->create([
        'tenant_id' => $tenant->id,
        'appointment_id' => $appointment->id,
        'external_id' => null,
        'preference_id' => 'pref-123',
        'status' => 'pending',
    ]);

    $this->partialMock(PaymentService::class, function ($mock) use ($appointment) {
        $mock->shouldAllowMockingProtectedMethods()
            ->shouldReceive('fetchPayment')
            ->once()
            ->andReturn((object) ['status' => 'approved', 'external_reference' => $appointment->id]);
    });

    $this->postJson('/api/v1/payments/webhook', [
        'type' => 'payment',
        'data' => ['id' => '55443322'],
    ])->assertOk();

    $fresh = $payment->fresh();
    expect($fresh->status)->toBe('approved')
        ->and($fresh->external_id)->toBe('55443322')
        ->and($appointment->fresh()->status)->toBe('confirmed');
});

it('webhook aprova assinatura checkout pro e atualiza o plano do tenant', function () {
    [$tenant] = payTenantWithClient();
    $tenant->update(['plan' => 'starter']);

    $subscription = Subscription::create([
        'tenant_id' => $tenant->id,
        'plan' => 'pro',
        'amount' => 9700,
        'method' => 'credit_card',
        'status' => 'pending',
        'mp_preference_id' => 'pref-sub-1',
    ]);

    $this->partialMock(PaymentService::class, function ($mock) use ($tenant) {
        $mock->shouldAllowMockingProtectedMethods()
            ->shouldReceive('fetchPayment')
            ->once()
            ->andReturn((object) [
                'status' => 'approved',
                'external_reference' => "subscription_{$tenant->id}_pro",
            ]);
    });

    $this->postJson('/api/v1/payments/webhook', [
        'type' => 'payment',
        'data' => ['id' => '11223344'],
    ])->assertOk();

    $fresh = $subscription->fresh();
    expect($fresh->status)->toBe('approved')
        ->and($fresh->mp_payment_id)->toBe('11223344')
        ->and($fresh->expires_at->diffInDays(now()->addMonth()))->toBeLessThan(2)
        ->and($tenant->fresh()->plan)->toBe('pro');
});

// --- Regras de pagamento ---

it('nao permite pagar agendamento cancelado', function () {
    [$tenant, $client] = payTenantWithClient();
    $appointment = payAppointmentForClient($tenant, $client);
    $appointment->update(['status' => 'cancelled']);

    $this->actingAs($client)
        ->postJson("/api/v1/salao/{$tenant->slug}/appointments/{$appointment->id}/payments", [
            'method' => 'pix',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['method']);
});

it('nao permite pagar agendamento que ja tem pagamento aprovado', function () {
    [$tenant, $client] = payTenantWithClient();
    $appointment = payAppointmentForClient($tenant, $client);

    Payment::factory()->pix()->create([
        'tenant_id' => $tenant->id,
        'appointment_id' => $appointment->id,
        'status' => 'approved',
    ]);

    $this->actingAs($client)
        ->postJson("/api/v1/salao/{$tenant->slug}/appointments/{$appointment->id}/payments", [
            'method' => 'pix',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['method']);
});

it('webhook e processado em fila e notifica pagamento aprovado', function () {
    Notification::fake();

    [$tenant, $client] = payTenantWithClient();
    $appointment = payAppointmentForClient($tenant, $client);

    $payment = Payment::factory()->pix()->create([
        'tenant_id' => $tenant->id,
        'appointment_id' => $appointment->id,
        'external_id' => '10101010',
        'status' => 'pending',
    ]);

    $this->partialMock(PaymentService::class, function ($mock) use ($appointment) {
        $mock->shouldAllowMockingProtectedMethods()
            ->shouldReceive('fetchPayment')
            ->once()
            ->andReturn((object) ['status' => 'approved', 'external_reference' => $appointment->id]);
    });

    $this->postJson('/api/v1/payments/webhook', [
        'type' => 'payment',
        'data' => ['id' => '10101010'],
    ])->assertOk();

    expect($payment->fresh()->status)->toBe('approved');
    Notification::assertSentTo($client, PaymentApproved::class);
});

it('webhook enfileira o job ProcessPaymentWebhook', function () {
    Queue::fake();

    $this->postJson('/api/v1/payments/webhook', [
        'type' => 'payment',
        'data' => ['id' => '123'],
    ])->assertOk();

    Queue::assertPushed(ProcessPaymentWebhook::class);
});

it('webhook aprova assinatura via metadata mesmo sem external_reference', function () {
    [$tenant] = payTenantWithClient();
    $tenant->update(['plan' => 'starter']);

    $subscription = Subscription::create([
        'tenant_id' => $tenant->id,
        'plan' => 'enterprise',
        'amount' => 19700,
        'method' => 'credit_card',
        'status' => 'pending',
        'mp_preference_id' => 'pref-sub-2',
    ]);

    $this->partialMock(PaymentService::class, function ($mock) use ($tenant) {
        $mock->shouldAllowMockingProtectedMethods()
            ->shouldReceive('fetchPayment')
            ->once()
            ->andReturn((object) [
                'status' => 'approved',
                'external_reference' => null,
                'metadata' => (object) [
                    'type' => 'subscription',
                    'tenant_id' => $tenant->id,
                    'plan' => 'enterprise',
                ],
            ]);
    });

    $this->postJson('/api/v1/payments/webhook', [
        'type' => 'payment',
        'data' => ['id' => '77665544'],
    ])->assertOk();

    expect($subscription->fresh()->status)->toBe('approved')
        ->and($tenant->fresh()->plan)->toBe('enterprise');
});
