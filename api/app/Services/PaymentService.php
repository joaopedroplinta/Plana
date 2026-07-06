<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\PaymentApproved;
use App\Notifications\SubscriptionActivated;
use Illuminate\Support\Facades\Notification;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;

class PaymentService
{
    public function __construct()
    {
        $token = config('services.mercadopago.access_token');

        if ($token) {
            MercadoPagoConfig::setAccessToken($token);
        }
    }

    public function createPix(Appointment $appointment, User $payer): Payment
    {
        $client = new PaymentClient;
        $result = $client->create([
            'transaction_amount' => round($appointment->price / 100, 2),
            'description' => 'Agendamento '.$appointment->id,
            'payment_method_id' => 'pix',
            'payer' => ['email' => $payer->email],
            'external_reference' => $appointment->id,
        ]);

        return Payment::create([
            'appointment_id' => $appointment->id,
            'amount' => $appointment->price,
            'method' => 'pix',
            'external_id' => (string) $result->id,
            'status' => $result->status ?? 'pending',
            'pix_qr_code' => $result->point_of_interaction->transaction_data->qr_code ?? null,
            'pix_qr_code_base64' => $result->point_of_interaction->transaction_data->qr_code_base64 ?? null,
        ]);
    }

    public function createCheckoutPro(Appointment $appointment, User $payer, string $slug): Payment
    {
        $client = new PreferenceClient;
        $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:3000'), '/');

        $result = $client->create([
            'items' => [[
                'title' => 'Agendamento '.$appointment->id,
                'quantity' => 1,
                'unit_price' => round($appointment->price / 100, 2),
            ]],
            'payer' => ['email' => $payer->email],
            'external_reference' => $appointment->id,
            'back_urls' => [
                'success' => "{$frontendUrl}/{$slug}/payment-success",
                'failure' => "{$frontendUrl}/{$slug}/payment-failure",
                'pending' => "{$frontendUrl}/{$slug}/payment-success",
            ],
            'auto_return' => 'approved',
            'notification_url' => config('app.url').'/api/v1/payments/webhook',
        ]);

        return Payment::create([
            'appointment_id' => $appointment->id,
            'amount' => $appointment->price,
            'method' => 'credit_card',
            'preference_id' => $result->id,
            'status' => 'pending',
        ]);
    }

    public function syncStatus(Payment $payment): Payment
    {
        if (! $payment->external_id) {
            return $payment;
        }

        $result = $this->fetchPayment($payment->external_id);

        $this->applyStatus($payment, (string) $result->status);

        return $payment->fresh();
    }

    /**
     * Busca um pagamento na API do MercadoPago (extraído para permitir mock em testes).
     */
    protected function fetchPayment(string $externalId): object
    {
        $client = new PaymentClient;

        return $client->get((int) $externalId);
    }

    public function handleWebhook(array $data): void
    {
        $type = $data['type'] ?? null;
        if ($type !== 'payment') {
            return;
        }

        $externalId = (string) ($data['data']['id'] ?? '');
        if (! $externalId) {
            return;
        }

        $result = $this->fetchPayment($externalId);
        $status = (string) $result->status;
        $reference = (string) ($result->external_reference ?? '');

        // 1. PIX payments store the MP payment id upfront.
        $payment = Payment::withoutTenantScope()->where('external_id', $externalId)->first();

        // 2. Checkout Pro payments only know the preference id — match the
        //    pending payment through the appointment in external_reference.
        if (! $payment && $reference && ! str_starts_with($reference, 'subscription_')) {
            $payment = Payment::withoutTenantScope()
                ->where('appointment_id', $reference)
                ->whereNotNull('preference_id')
                ->where('status', 'pending')
                ->latest()
                ->first();

            $payment?->update(['external_id' => $externalId]);
        }

        if ($payment) {
            $this->applyStatus($payment, $status);

            return;
        }

        $this->handleSubscriptionWebhook($externalId, $reference, $status);
    }

    private function applyStatus(Payment $payment, string $status): void
    {
        $becameApproved = $status === 'approved' && $payment->status !== 'approved';

        $payment->update([
            'status' => $status,
            'paid_at' => $status === 'approved' ? ($payment->paid_at ?? now()) : $payment->paid_at,
        ]);

        if (! $becameApproved) {
            return;
        }

        if ($payment->appointment && $payment->appointment->status === 'pending') {
            $payment->appointment->update(['status' => 'confirmed']);
        }

        $payment->appointment?->client?->notify(new PaymentApproved($payment));
    }

    private function handleSubscriptionWebhook(string $externalId, string $reference, string $status): void
    {
        $subscription = Subscription::withoutTenantScope()->where('mp_payment_id', $externalId)->first();

        // Checkout Pro subscriptions carry "subscription_{tenant_id}_{plan}".
        if (! $subscription && str_starts_with($reference, 'subscription_')) {
            $parts = explode('_', substr($reference, strlen('subscription_')));
            $plan = array_pop($parts);
            $tenantId = implode('_', $parts);

            $subscription = Subscription::withoutTenantScope()
                ->where('tenant_id', $tenantId)
                ->where('plan', $plan)
                ->where('status', 'pending')
                ->whereNotNull('mp_preference_id')
                ->latest()
                ->first();

            $subscription?->update(['mp_payment_id' => $externalId]);
        }

        if ($subscription && $status === 'approved' && $subscription->status !== 'approved') {
            $subscription->update([
                'status' => 'approved',
                'paid_at' => now(),
                'expires_at' => now()->addMonth(),
            ]);
            $subscription->tenant->update(['plan' => $subscription->plan]);

            Notification::send($subscription->tenant->owner, new SubscriptionActivated($subscription));
        } elseif ($subscription && $subscription->status !== 'approved') {
            $subscription->update(['status' => $status]);
        }
    }
}
