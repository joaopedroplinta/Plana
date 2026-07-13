<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Professional;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SubscriptionActivated;
use App\Services\Concerns\InteractsWithMercadoPagoOrders;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use MercadoPago\Client\Order\OrderClient;
use MercadoPago\MercadoPagoConfig;

class SubscriptionService
{
    use InteractsWithMercadoPagoOrders;

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

        if (Professional::where('active', true)->count() >= $limit) {
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
                'starts_at' => ["O negócio atingiu o limite de {$limit} agendamentos neste mês. Fale com o negócio para mais informações."],
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
        $reference = "subscription_{$tenant->id}_{$plan}";

        $order = $this->createMercadoPagoOrder(
            amountInCents: $planData['price'],
            reference: $reference,
            paymentMethod: ['id' => 'pix', 'type' => 'bank_transfer'],
            payer: ['email' => $payer->email],
        );

        $subscription = Subscription::create([
            'tenant_id' => $tenant->id,
            'plan' => $plan,
            'amount' => $planData['price'],
            'method' => 'pix',
            'status' => 'pending',
            'mp_payment_id' => $order->id,
            'pix_qr_code' => $this->orderPaymentMethodField($order, 'qr_code'),
            'pix_qr_code_base64' => $this->orderPaymentMethodField($order, 'qr_code_base64'),
        ]);

        return $this->applyStatus($subscription, $this->mapMercadoPagoStatus($order));
    }

    /**
     * Cartão via Checkout Transparente (Card Payment Brick) — assim como no
     * agendamento, a Order API processa de forma síncrona, então aplicamos
     * o status já na criação em vez de depender só do webhook.
     *
     * @param  array{token: string, payment_method_id: string, installments: int, issuer_id?: ?string, payer?: array<string, mixed>}  $card
     */
    public function createCheckoutProSubscription(Tenant $tenant, User $payer, string $plan, array $card): Subscription
    {
        $planData = self::PLANS[$plan];
        $reference = "subscription_{$tenant->id}_{$plan}";

        $order = $this->createMercadoPagoOrder(
            amountInCents: $planData['price'],
            reference: $reference,
            paymentMethod: $this->cardPaymentMethod($card),
            payer: $card['payer'] ?? ['email' => $payer->email],
        );

        $subscription = Subscription::create([
            'tenant_id' => $tenant->id,
            'plan' => $plan,
            'amount' => $planData['price'],
            'method' => 'credit_card',
            'status' => 'pending',
            'mp_payment_id' => $order->id,
        ]);

        return $this->applyStatus($subscription, $this->mapMercadoPagoStatus($order));
    }

    public function syncStatus(Subscription $subscription): Subscription
    {
        if (! $subscription->mp_payment_id) {
            return $subscription;
        }

        $client = new OrderClient;
        $order = $client->get($subscription->mp_payment_id);

        return $this->applyStatus($subscription, $this->mapMercadoPagoStatus($order));
    }

    private function applyStatus(Subscription $subscription, string $status): Subscription
    {
        $becameApproved = $status === 'approved' && $subscription->status !== 'approved';

        $updates = ['status' => $status];

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
