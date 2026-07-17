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

function useFakeMercadoPagoHttpClient(array $payload, int $statusCode = 200): FakeMercadoPagoHttpRequest
{
    $fake = new FakeMercadoPagoHttpRequest($payload, $statusCode);
    MercadoPagoConfig::setHttpClient(new MPDefaultHttpClient($fake));
    MercadoPagoConfig::setAccessToken('TEST-fake-access-token-for-tests');

    return $fake;
}

/**
 * Tenant com conta MercadoPago "conectada" (Fase 1): token não-expirado e
 * mp_connected_at preenchidos, para que accessTokenFor() devolva o token do
 * salão e a comissão de marketplace (Fase 2) seja aplicada.
 */
function ordersConnectedTenant(string $plan = 'starter'): Tenant
{
    return Tenant::factory()->create([
        'plan' => $plan,
        'mp_access_token' => 'APP_USR-tenant-connected-token',
        'mp_connected_at' => now(),
        'mp_token_expires_at' => now()->addHour(),
    ]);
}

function ordersTenantWithClient(?Tenant $tenant = null): array
{
    $tenant ??= Tenant::factory()->create();
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

// --- Marketplace: comissão da plataforma (Fase 2) ---

function ordersPixResponse(string $orderId, string $reference, string $email): array
{
    return [
        'id' => $orderId,
        'type' => 'online',
        'total_amount' => '50.00',
        'external_reference' => $reference,
        'status' => 'action_required',
        'status_detail' => 'waiting_transfer',
        'transactions' => [
            'payments' => [[
                'id' => 'pay_fee_test',
                'status' => 'action_required',
                'status_detail' => 'waiting_transfer',
                'amount' => '50.00',
                'payment_method' => ['id' => 'pix', 'type' => 'bank_transfer', 'qr_code' => 'qr', 'qr_code_base64' => 'YQ=='],
            ]],
        ],
        'payer' => ['email' => $email],
    ];
}

it('createPix cobra marketplace_fee conforme o plano quando o salão está conectado', function (string $plan, string $expectedFee, int $expectedCents) {
    [$tenant, $client] = ordersTenantWithClient(ordersConnectedTenant($plan));
    $appointment = ordersAppointmentForClient($tenant, $client); // price 5000

    $fake = useFakeMercadoPagoHttpClient(ordersPixResponse('ORD_FEE', $appointment->id, $client->email));

    $payment = app(PaymentService::class)->createPix($appointment, $client);

    expect($fake->lastRequestBody['marketplace_fee'])->toBe($expectedFee)
        ->and($payment->platform_fee)->toBe($expectedCents);
})->with([
    'starter (5%)' => ['starter', '2.5', 250],
    'pro (3%)' => ['pro', '1.5', 150],
    'enterprise (1%)' => ['enterprise', '0.5', 50],
]);

it('createCheckoutPro também cobra marketplace_fee quando conectado', function () {
    [$tenant, $client] = ordersTenantWithClient(ordersConnectedTenant('starter'));
    $appointment = ordersAppointmentForClient($tenant, $client); // price 5000

    $fake = useFakeMercadoPagoHttpClient([
        'id' => 'ORD_CARD_FEE',
        'external_reference' => $appointment->id,
        'status' => 'processed',
        'status_detail' => 'accredited',
        'transactions' => ['payments' => [['id' => 'p1', 'status' => 'processed', 'status_detail' => 'accredited', 'amount' => '50.00', 'payment_method' => ['id' => 'master', 'type' => 'credit_card', 'token' => 't', 'installments' => 1]]]],
        'payer' => ['email' => $client->email],
    ]);

    $payment = app(PaymentService::class)->createCheckoutPro($appointment, $client, [
        'token' => 't', 'payment_method_id' => 'master', 'installments' => 1,
    ]);

    expect($fake->lastRequestBody['marketplace_fee'])->toBe('2.5')
        ->and($payment->platform_fee)->toBe(250);
});

it('NÃO cobra marketplace_fee quando o salão não conectou conta MercadoPago', function () {
    [$tenant, $client] = ordersTenantWithClient(); // tenant sem conexão MP
    $appointment = ordersAppointmentForClient($tenant, $client);

    $fake = useFakeMercadoPagoHttpClient(ordersPixResponse('ORD_NO_FEE', $appointment->id, $client->email));

    $payment = app(PaymentService::class)->createPix($appointment, $client);

    expect($fake->lastRequestBody)->not->toHaveKey('marketplace_fee')
        ->and($payment->platform_fee)->toBeNull();
});

it('cobra apenas o sinal (deposit_amount) e calcula a comissão sobre ele', function () {
    [$tenant, $client] = ordersTenantWithClient(ordersConnectedTenant('starter')); // 5%
    $appointment = ordersAppointmentForClient($tenant, $client); // price 5000
    $appointment->update(['deposit_amount' => 2000]); // sinal de R$20

    $fake = useFakeMercadoPagoHttpClient(ordersPixResponse('ORD_DEPOSIT', $appointment->id, $client->email));

    $payment = app(PaymentService::class)->createPix($appointment, $client);

    // Cobra R$20 (o sinal), não os R$50; marketplace_fee = 5% de R$20 = R$1.
    expect($fake->lastRequestBody['total_amount'])->toBe('20')
        ->and($fake->lastRequestBody['marketplace_fee'])->toBe('1')
        ->and($payment->amount)->toBe(2000)
        ->and($payment->platform_fee)->toBe(100)
        ->and($appointment->fresh()->balanceDue())->toBe(3000); // resta R$30 presencial
});

it('NÃO cobra marketplace_fee em compra de pacote, mesmo com salão conectado', function () {
    [$tenant, $client] = ordersTenantWithClient(ordersConnectedTenant('starter'));
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

    $fake = useFakeMercadoPagoHttpClient([
        'id' => 'ORD_PKG_NO_FEE',
        'external_reference' => "package_purchase_{$purchase->id}",
        'status' => 'action_required',
        'status_detail' => 'waiting_transfer',
        'transactions' => ['payments' => [['id' => 'p1', 'status' => 'action_required', 'status_detail' => 'waiting_transfer', 'amount' => '150.00', 'payment_method' => ['id' => 'pix', 'type' => 'bank_transfer']]]],
        'payer' => ['email' => $client->email],
    ]);

    $payment = app(PaymentService::class)->createPixForPackagePurchase($purchase, $client);

    expect($fake->lastRequestBody)->not->toHaveKey('marketplace_fee')
        ->and($payment->platform_fee)->toBeNull();
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
