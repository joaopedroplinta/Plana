<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Professional;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SubscriptionActivated;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
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
            'max_professionals' => 1,
            'max_appointments_per_month' => 50,
            'professionals' => '1 profissional',
            'appointments' => '50 agendamentos/mês',
            'features' => ['1 profissional', '50 agendamentos/mês', 'Suporte básico'],
        ],
        'pro' => [
            'key' => 'pro',
            'name' => 'Pro',
            'price' => 9700,
            'max_professionals' => 5,
            'max_appointments_per_month' => null,
            'professionals' => '5 profissionais',
            'appointments' => 'Agendamentos ilimitados',
            'features' => ['5 profissionais', 'Agendamentos ilimitados', 'Suporte prioritário', 'Relatórios avançados'],
        ],
        'enterprise' => [
            'key' => 'enterprise',
            'name' => 'Enterprise',
            'price' => 19700,
            'max_professionals' => null,
            'max_appointments_per_month' => null,
            'professionals' => 'Profissionais ilimitados',
            'appointments' => 'Agendamentos ilimitados',
            'features' => ['Profissionais ilimitados', 'Agendamentos ilimitados', 'Suporte dedicado', 'Relatórios avançados', 'API access'],
        ],
    ];

    public static function maxProfessionals(string $plan): ?int
    {
        return (self::PLANS[$plan] ?? self::PLANS['starter'])['max_professionals'];
    }

    public static function maxAppointmentsPerMonth(string $plan): ?int
    {
        return (self::PLANS[$plan] ?? self::PLANS['starter'])['max_appointments_per_month'];
    }

    public static function assertCanAddProfessional(Tenant $tenant): void
    {
        $limit = self::maxProfessionals($tenant->plan);

        if ($limit === null) {
            return;
        }

        if (Professional::count() >= $limit) {
            $plural = $limit === 1 ? 'profissional' : 'profissionais';

            throw ValidationException::withMessages([
                'name' => ["Seu plano permite no máximo {$limit} {$plural}. Faça upgrade para adicionar mais."],
            ]);
        }
    }

    public static function assertCanCreateAppointment(Tenant $tenant, Carbon $startsAt): void
    {
        $limit = self::maxAppointmentsPerMonth($tenant->plan);

        if ($limit === null) {
            return;
        }

        $count = Appointment::query()
            ->whereNotIn('status', ['cancelled'])
            ->whereBetween('starts_at', [
                $startsAt->copy()->startOfMonth(),
                $startsAt->copy()->endOfMonth(),
            ])
            ->count();

        if ($count >= $limit) {
            throw ValidationException::withMessages([
                'starts_at' => ["O salão atingiu o limite de {$limit} agendamentos neste mês. Fale com o salão para mais informações."],
            ]);
        }
    }

    public function __construct()
    {
        $token = config('services.mercadopago.access_token');

        if ($token) {
            MercadoPagoConfig::setAccessToken($token);
        }
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

        $becameApproved = $result->status === 'approved' && $subscription->status !== 'approved';

        $updates = ['status' => $result->status];

        if ($becameApproved) {
            $updates['paid_at'] = now();
            $updates['expires_at'] = now()->addMonth();
        }

        $subscription->update($updates);

        if ($becameApproved) {
            $subscription->tenant->update(['plan' => $subscription->plan]);
            Notification::send($subscription->tenant->owner, new SubscriptionActivated($subscription));
        }

        return $subscription->fresh();
    }
}
