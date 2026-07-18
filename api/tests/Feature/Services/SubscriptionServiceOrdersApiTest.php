<?php

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Net\MPDefaultHttpClient;
use Tests\Support\FakeMercadoPagoHttpRequest;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Testes reais (sem mockar SubscriptionService) do fluxo de cobrança
| ANUAL (issue #95) contra a integração com a API Orders do MercadoPago,
| usando o mesmo mock de transporte HTTP usado em
| PaymentServiceOrdersApiTest.
|--------------------------------------------------------------------------
*/

function useSubscriptionFakeHttpClient(array $payload, int $statusCode = 200): FakeMercadoPagoHttpRequest
{
    $fake = new FakeMercadoPagoHttpRequest($payload, $statusCode);
    MercadoPagoConfig::setHttpClient(new MPDefaultHttpClient($fake));
    MercadoPagoConfig::setAccessToken('TEST-fake-access-token-for-tests');

    return $fake;
}

afterEach(function () {
    MercadoPagoConfig::setHttpClient(new MPDefaultHttpClient);
});

it('cobra o valor anual cheio (nao 12x o mensal) ao criar assinatura pix anual do plano pro', function () {
    $tenant = Tenant::factory()->create(['plan' => 'starter']);
    $owner = User::factory()->create();

    $fake = useSubscriptionFakeHttpClient([
        'id' => 'ORD_SUB_YEARLY_PRO',
        'type' => 'online',
        'total_amount' => '970.00',
        'external_reference' => "subscription_{$tenant->id}_pro_yearly",
        'status' => 'action_required',
        'status_detail' => 'waiting_transfer',
        'transactions' => [
            'payments' => [[
                'id' => 'pay_sub_yearly_pro',
                'status' => 'action_required',
                'status_detail' => 'waiting_transfer',
                'amount' => '970.00',
                'payment_method' => [
                    'id' => 'pix',
                    'type' => 'bank_transfer',
                    'qr_code' => 'pixcode',
                    'qr_code_base64' => 'YQ==',
                ],
            ]],
        ],
        'payer' => ['email' => $owner->email],
    ]);

    $subscription = app(SubscriptionService::class)->createPixSubscription($tenant, $owner, 'pro', 'yearly');

    expect($fake->lastRequestBody['total_amount'])->toBe('970')
        ->and($subscription->billing_cycle)->toBe('yearly')
        ->and($subscription->amount)->toBe(97000)
        ->and($subscription->status)->toBe('pending')
        ->and($subscription->expires_at)->toBeNull();
});

it('ativa o plano anual e define expires_at para +1 ano (nao +1 mes) quando a order ja vem aprovada', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create(['plan' => 'starter']);
    $owner = User::factory()->create();

    useSubscriptionFakeHttpClient([
        'id' => 'ORD_SUB_YEARLY_ENT',
        'external_reference' => "subscription_{$tenant->id}_enterprise_yearly",
        'status' => 'processed',
        'status_detail' => 'accredited',
        'transactions' => [
            'payments' => [[
                'id' => 'pay_sub_yearly_ent',
                'status' => 'processed',
                'status_detail' => 'accredited',
                'amount' => '1970.00',
                'payment_method' => ['id' => 'master', 'type' => 'credit_card', 'token' => 'tok', 'installments' => 1],
            ]],
        ],
        'payer' => ['email' => $owner->email],
    ]);

    $subscription = app(SubscriptionService::class)->createCheckoutProSubscription($tenant, $owner, 'enterprise', [
        'token' => 'tok',
        'payment_method_id' => 'master',
        'installments' => 1,
    ], 'yearly');

    expect($subscription->billing_cycle)->toBe('yearly')
        ->and($subscription->amount)->toBe(197000)
        ->and($subscription->status)->toBe('approved')
        ->and($subscription->paid_at)->not->toBeNull()
        ->and($subscription->expires_at)->not->toBeNull()
        ->and($subscription->expires_at->diffInDays($subscription->paid_at->copy()->addYear()))->toBeLessThan(1)
        ->and($tenant->fresh()->plan)->toBe('enterprise');
});

it('syncStatus (equivalente ao webhook) aprova uma assinatura pix anual pendente e define expires_at para +1 ano', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create(['plan' => 'starter']);
    $owner = User::factory()->create();

    $subscription = Subscription::create([
        'tenant_id' => $tenant->id,
        'plan' => 'pro',
        'billing_cycle' => 'yearly',
        'amount' => 97000,
        'method' => 'pix',
        'status' => 'pending',
        'mp_payment_id' => 'ORD_SUB_YEARLY_SYNC',
    ]);

    useSubscriptionFakeHttpClient([
        'id' => 'ORD_SUB_YEARLY_SYNC',
        'external_reference' => "subscription_{$tenant->id}_pro_yearly",
        'status' => 'processed',
        'status_detail' => 'accredited',
        'transactions' => [
            'payments' => [[
                'id' => 'pay_sub_yearly_sync',
                'status' => 'processed',
                'status_detail' => 'accredited',
                'amount' => '970.00',
                'payment_method' => ['id' => 'pix', 'type' => 'bank_transfer'],
            ]],
        ],
        'payer' => ['email' => $owner->email],
    ]);

    $synced = app(SubscriptionService::class)->syncStatus($subscription);

    expect($synced->status)->toBe('approved')
        ->and($synced->paid_at)->not->toBeNull()
        ->and($synced->expires_at->diffInDays($synced->paid_at->copy()->addYear()))->toBeLessThan(1)
        ->and($tenant->fresh()->plan)->toBe('pro');
});
