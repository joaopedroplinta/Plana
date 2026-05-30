<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\User;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;

class PaymentService
{
    public function __construct()
    {
        MercadoPagoConfig::setAccessToken(config('services.mercadopago.access_token'));
    }

    public function createPix(Appointment $appointment, User $payer): Payment
    {
        $client = new PaymentClient;
        $result = $client->create([
            'transaction_amount' => round($appointment->price / 100, 2),
            'description' => 'Agendamento '.$appointment->id,
            'payment_method_id' => 'pix',
            'payer' => ['email' => $payer->email],
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

        $client = new PaymentClient;
        $result = $client->get((int) $payment->external_id);

        $payment->update([
            'status' => $result->status,
            'paid_at' => $result->status === 'approved' ? now() : null,
        ]);

        return $payment->fresh();
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

        $payment = Payment::withoutTenantScope()->where('external_id', $externalId)->first();
        if (! $payment) {
            return;
        }

        $client = new PaymentClient;
        $result = $client->get((int) $externalId);

        $payment->update([
            'status' => $result->status,
            'paid_at' => $result->status === 'approved' ? now() : null,
        ]);

        if ($result->status === 'approved') {
            $payment->appointment->update(['status' => 'confirmed']);
        }
    }
}
