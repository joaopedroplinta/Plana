<?php

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\PackagePurchase;
use App\Models\Payment;
use App\Models\Professional;
use App\Models\Service;
use App\Models\ServicePackage;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PaymentApproved;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Net\MPDefaultHttpClient;
use Spatie\Permission\Models\Role;
use Tests\Support\FakeMercadoPagoHttpRequest;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Testes reais (sem mockar PaymentService) contra a integração com a API
| Orders do MercadoPago (/v1/orders), usando o mesmo mecanismo de mock de
| transporte HTTP que a própria SDK usa nos seus testes unitários
| (vendor/mercadopago/dx-php/tests/.../Unit/Base/BaseClient.php): trocamos
| o HttpRequest por uma implementação fake que devolve um JSON fixo, sem
| nenhuma chamada de rede de verdade.
|--------------------------------------------------------------------------
*/

function useFakeMercadoPagoHttpClient(array $payload, int $statusCode = 200): void
{
    MercadoPagoConfig::setHttpClient(new MPDefaultHttpClient(new FakeMercadoPagoHttpRequest($payload, $statusCode)));
    MercadoPagoConfig::setAccessToken('TEST-fake-access-token-for-tests');
}

function ordersTenantWithClient(): array
{
    $tenant = Tenant::factory()->create();
    $client = User::factory()->create();
    $client->tenants()->attach($tenant->id, ['role' => 'client']);
    Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
    $client->assignRole('client');

    // BelongsToTenant::bootBelongsToTenant() auto-preenche tenant_id lendo
    // app('currentTenant') — normalmente resolvido pelo middleware
    // ResolveTenant numa request HTTP de verdade. Aqui chamamos os
    // Services diretamente (sem controller/rota), então simulamos o bind.
    app()->instance('currentTenant', $tenant);

    return [$tenant, $client];
}

function ordersAppointmentForClient(Tenant $tenant, User $client): Appointment
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

afterEach(function () {
    // Evita vazar o http client fake para outros testes do processo.
    MercadoPagoConfig::setHttpClient(new MPDefaultHttpClient);
});

// --- PIX ---

it('createPix consulta a API Orders e grava o qr code no novo formato', function () {
    [$tenant, $client] = ordersTenantWithClient();
    $appointment = ordersAppointmentForClient($tenant, $client);

    useFakeMercadoPagoHttpClient([
        'id' => 'ORD01HRYFWNYRE1MR1E60MW3X0T2P',
        'type' => 'online',
        'total_amount' => '50.00',
        'external_reference' => $appointment->id,
        'status' => 'action_required',
        'status_detail' => 'waiting_transfer',
        'transactions' => [
            'payments' => [[
                'id' => 'pay_01HRYFXQ53Q3JPEC48MYWMR0TE',
                'status' => 'action_required',
                'status_detail' => 'waiting_transfer',
                'amount' => '50.00',
                'payment_method' => [
                    'id' => 'pix',
                    'type' => 'bank_transfer',
                    'qr_code' => '00020126borrowedpixcode',
                    'qr_code_base64' => 'aGVsbG8=',
                ],
            ]],
        ],
        'payer' => ['email' => $client->email],
    ]);

    $payment = app(PaymentService::class)->createPix($appointment, $client);

    expect($payment->external_id)->toBe('ORD01HRYFWNYRE1MR1E60MW3X0T2P')
        ->and($payment->method)->toBe('pix')
        ->and($payment->status)->toBe('pending')
        ->and($payment->pix_qr_code)->toBe('00020126borrowedpixcode')
        ->and($payment->pix_qr_code_base64)->toBe('aGVsbG8=');
});

it('createPix ja aprova o pagamento se a order vier processed/accredited de cara', function () {
    Notification::fake();

    [$tenant, $client] = ordersTenantWithClient();
    $appointment = ordersAppointmentForClient($tenant, $client);

    useFakeMercadoPagoHttpClient([
        'id' => 'ORD_ALREADY_PAID',
        'external_reference' => $appointment->id,
        'status' => 'processed',
        'status_detail' => 'accredited',
        'transactions' => [
            'payments' => [[
                'id' => 'pay_already_paid',
                'status' => 'processed',
                'status_detail' => 'accredited',
                'amount' => '50.00',
                'payment_method' => ['id' => 'pix', 'type' => 'bank_transfer'],
            ]],
        ],
        'payer' => ['email' => $client->email],
    ]);

    $payment = app(PaymentService::class)->createPix($appointment, $client);

    expect($payment->status)->toBe('approved')
        ->and($payment->paid_at)->not->toBeNull()
        ->and($appointment->fresh()->status)->toBe(AppointmentStatus::Confirmed);

    Notification::assertSentTo($client, PaymentApproved::class);
});

// --- Cartão (Checkout Transparente / Card Payment Brick) ---

it('createCheckoutPro processa o cartao de forma sincrona via API Orders', function () {
    Notification::fake();

    [$tenant, $client] = ordersTenantWithClient();
    $appointment = ordersAppointmentForClient($tenant, $client);

    useFakeMercadoPagoHttpClient([
        'id' => 'ORD_CARD_APPROVED',
        'external_reference' => $appointment->id,
        'status' => 'processed',
        'status_detail' => 'accredited',
        'transactions' => [
            'payments' => [[
                'id' => 'pay_card_approved',
                'status' => 'processed',
                'status_detail' => 'accredited',
                'amount' => '50.00',
                'payment_method' => [
                    'id' => 'master',
                    'type' => 'credit_card',
                    'token' => 'card_token',
                    'installments' => 1,
                ],
            ]],
        ],
        'payer' => ['email' => $client->email],
    ]);

    $payment = app(PaymentService::class)->createCheckoutPro($appointment, $client, [
        'token' => 'card_token',
        'payment_method_id' => 'master',
        'installments' => 1,
    ]);

    expect($payment->method)->toBe('credit_card')
        ->and($payment->external_id)->toBe('ORD_CARD_APPROVED')
        ->and($payment->status)->toBe('approved')
        ->and($appointment->fresh()->status)->toBe(AppointmentStatus::Confirmed);

    Notification::assertSentTo($client, PaymentApproved::class);
});

it('createCheckoutPro para pacote ativa a compra imediatamente quando aprovado', function () {
    [$tenant, $client] = ordersTenantWithClient();
    $package = ServicePackage::factory()->create(['tenant_id' => $tenant->id, 'sessions' => 3, 'price' => 15000, 'valid_days' => 60]);

    $purchase = PackagePurchase::create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'service_package_id' => $package->id,
        'sessions_total' => 3,
        'sessions_used' => 0,
        'price_paid' => 15000,
        'status' => 'pending',
    ]);

    useFakeMercadoPagoHttpClient([
        'id' => 'ORD_PACKAGE_CARD',
        'external_reference' => "package_purchase_{$purchase->id}",
        'status' => 'processed',
        'status_detail' => 'accredited',
        'transactions' => [
            'payments' => [[
                'id' => 'pay_package_card',
                'status' => 'processed',
                'status_detail' => 'accredited',
                'amount' => '150.00',
                'payment_method' => ['id' => 'master', 'type' => 'credit_card', 'token' => 'tok', 'installments' => 2],
            ]],
        ],
        'payer' => ['email' => $client->email],
    ]);

    app(PaymentService::class)->createCheckoutProForPackagePurchase($purchase, $client, [
        'token' => 'tok',
        'payment_method_id' => 'master',
        'installments' => 2,
    ]);

    expect($purchase->fresh()->status->value)->toBe('active');
});

// --- Webhook ---

it('webhook aprova o pagamento consultando a Order pelo novo formato de resposta', function () {
    Notification::fake();

    [$tenant, $client] = ordersTenantWithClient();
    $appointment = ordersAppointmentForClient($tenant, $client);

    $payment = Payment::factory()->pix()->create([
        'tenant_id' => $tenant->id,
        'appointment_id' => $appointment->id,
        'external_id' => 'ORD_WEBHOOK_TEST',
        'status' => 'pending',
    ]);

    useFakeMercadoPagoHttpClient([
        'id' => 'ORD_WEBHOOK_TEST',
        'external_reference' => $appointment->id,
        'status' => 'processed',
        'status_detail' => 'accredited',
        'transactions' => [
            'payments' => [[
                'id' => 'pay_webhook_test',
                'status' => 'processed',
                'status_detail' => 'accredited',
                'amount' => '50.00',
                'payment_method' => ['id' => 'pix', 'type' => 'bank_transfer'],
            ]],
        ],
        'payer' => ['email' => $client->email],
    ]);

    app(PaymentService::class)->handleWebhook([
        'type' => 'payment',
        'data' => ['id' => 'ORD_WEBHOOK_TEST'],
    ]);

    expect($payment->fresh()->status)->toBe('approved')
        ->and($appointment->fresh()->status)->toBe(AppointmentStatus::Confirmed);
});

it('webhook aceita o topico order alem de payment', function () {
    [$tenant, $client] = ordersTenantWithClient();
    $appointment = ordersAppointmentForClient($tenant, $client);

    $payment = Payment::factory()->pix()->create([
        'tenant_id' => $tenant->id,
        'appointment_id' => $appointment->id,
        'external_id' => 'ORD_TOPIC_TEST',
        'status' => 'pending',
    ]);

    useFakeMercadoPagoHttpClient([
        'id' => 'ORD_TOPIC_TEST',
        'external_reference' => $appointment->id,
        'status' => 'processed',
        'status_detail' => 'accredited',
        'transactions' => ['payments' => [['id' => 'p1', 'status' => 'processed', 'status_detail' => 'accredited', 'amount' => '50.00', 'payment_method' => ['id' => 'pix', 'type' => 'bank_transfer']]]],
        'payer' => ['email' => $client->email],
    ]);

    app(PaymentService::class)->handleWebhook([
        'type' => 'order',
        'data' => ['id' => 'ORD_TOPIC_TEST'],
    ]);

    expect($payment->fresh()->status)->toBe('approved');
});

it('mapeia status action_required/cancelled/expired da Order corretamente', function () {
    [$tenant, $client] = ordersTenantWithClient();
    $appointment = ordersAppointmentForClient($tenant, $client);

    $payment = Payment::factory()->pix()->create([
        'tenant_id' => $tenant->id,
        'appointment_id' => $appointment->id,
        'external_id' => 'ORD_CANCELLED',
        'status' => 'pending',
    ]);

    useFakeMercadoPagoHttpClient([
        'id' => 'ORD_CANCELLED',
        'external_reference' => $appointment->id,
        'status' => 'cancelled',
        'status_detail' => 'cancelled',
        'transactions' => ['payments' => [['id' => 'p1', 'status' => 'cancelled', 'status_detail' => 'cancelled_transaction', 'amount' => '50.00', 'payment_method' => ['id' => 'pix', 'type' => 'bank_transfer']]]],
        'payer' => ['email' => $client->email],
    ]);

    $synced = app(PaymentService::class)->syncStatus($payment);

    expect($synced->status)->toBe('cancelled');
});
