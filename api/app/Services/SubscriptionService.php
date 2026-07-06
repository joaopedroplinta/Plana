<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;

class SubscriptionService
{
    /** @var array<string, array<string, mixed>> */
    private const PLANS = [
        'starter' => [
            'key' => 'starter',
            'name' => 'Starter',
            'price' => 0,
            'professionals' => '1 profissional',
            'appointments' => '50 agendamentos/mês',
            'features' => ['1 profissional', '50 agendamentos/mês', 'Suporte básico'],
        ],
        'pro' => [
            'key' => 'pro',
            'name' => 'Pro',
            'price' => 9700,
            'professionals' => '5 profissionais',
            'appointments' => 'Agendamentos ilimitados',
            'features' => ['5 profissionais', 'Agendamentos ilimitados', 'Suporte prioritário', 'Relatórios avançados'],
        ],
        'enterprise' => [
            'key' => 'enterprise',
            'name' => 'Enterprise',
            'price' => 19700,
            'professionals' => 'Profissionais ilimitados',
            'appointments' => 'Agendamentos ilimitados',
            'features' => ['Profissionais ilimitados', 'Agendamentos ilimitados', 'Suporte dedicado', 'Relatórios avançados', 'API access'],
        ],
    ];

    public function __construct()
    {
        MercadoPagoConfig::setAccessToken(config('services.mercadopago.access_token'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getPlans(): array
    {
        return array_values(self::PLANS);
    }

    public function createPixSubscription(Tenant $tenant, User $payer, string $plan): Subscription
    {
        $planData = self::PLANS[$plan];
        $client = new PaymentClient;

        $result = $client->create([
            'transaction_amount' => round($planData['price'] / 100, 2),
            'description' => "Assinatura plano {$planData['name']}",
            'payment_method_id' => 'pix',
            'payer' => ['email' => $payer->email],
            'metadata' => [
                'type' => 'subscription',
                'tenant_id' => $tenant->id,
                'plan' => $plan,
            ],
        ]);

        return Subscription::create([
            'tenant_id' => $tenant->id,
            'plan' => $plan,
            'amount' => $planData['price'],
            'method' => 'pix',
            'status' => $result->status ?? 'pending',
            'mp_payment_id' => (string) $result->id,
            'pix_qr_code' => $result->point_of_interaction->transaction_data->qr_code ?? null,
            'pix_qr_code_base64' => $result->point_of_interaction->transaction_data->qr_code_base64 ?? null,
        ]);
    }

    public function createCheckoutProSubscription(Tenant $tenant, User $payer, string $plan): Subscription
    {
        $planData = self::PLANS[$plan];
        $client = new PreferenceClient;
        $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:3000'), '/');

        $result = $client->create([
            'items' => [[
                'title' => "Assinatura plano {$planData['name']}",
                'quantity' => 1,
                'unit_price' => round($planData['price'] / 100, 2),
            ]],
            'payer' => ['email' => $payer->email],
            'external_reference' => "subscription_{$tenant->id}_{$plan}",
            'back_urls' => [
                'success' => "{$frontendUrl}/{$tenant->slug}/subscription-success",
                'failure' => "{$frontendUrl}/{$tenant->slug}/subscription-success",
                'pending' => "{$frontendUrl}/{$tenant->slug}/subscription-success",
            ],
            'auto_return' => 'approved',
            'notification_url' => config('app.url').'/api/v1/payments/webhook',
            'metadata' => [
                'type' => 'subscription',
                'tenant_id' => $tenant->id,
                'plan' => $plan,
            ],
        ]);

        return Subscription::create([
            'tenant_id' => $tenant->id,
            'plan' => $plan,
            'amount' => $planData['price'],
            'method' => 'credit_card',
            'status' => 'pending',
            'mp_preference_id' => $result->id,
        ]);
    }

    public function syncStatus(Subscription $subscription): Subscription
    {
        if (! $subscription->mp_payment_id) {
            return $subscription;
        }

        $client = new PaymentClient;
        $result = $client->get((int) $subscription->mp_payment_id);

        $updates = ['status' => $result->status];

        if ($result->status === 'approved') {
            $updates['paid_at'] = now();
            $updates['expires_at'] = now()->addYear();
        }

        $subscription->update($updates);

        if ($result->status === 'approved') {
            $subscription->tenant->update(['plan' => $subscription->plan]);
        }

        return $subscription->fresh();
    }
}
