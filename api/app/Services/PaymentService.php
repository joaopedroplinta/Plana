<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\PackagePurchaseStatus;
use App\Models\Appointment;
use App\Models\PackagePurchase;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PaymentApproved;
use App\Notifications\SubscriptionActivated;
use App\Services\Concerns\InteractsWithMercadoPagoOrders;
use Illuminate\Support\Facades\Notification;
use MercadoPago\Client\Order\OrderClient;
use MercadoPago\MercadoPagoConfig;

class PaymentService
{
    use InteractsWithMercadoPagoOrders;

    public function __construct()
    {
        $this->useGlobalToken();
    }

    /**
     * Marketplace (Fase 1): os pagamentos de agendamento/pacote devem cair na
     * conta do salão dono do recurso. Como o SDK guarda o access token em
     * estado ESTÁTICO (MercadoPagoConfig), definimos o token correto
     * imediatamente antes de cada operação — o tenant conectado usa o próprio
     * token (com refresh se expirado); sem conta conectada, cai no token
     * GLOBAL da plataforma (comportamento legado). Fee = 0 nesta fase.
     */
    private function useTenantToken(?Tenant $tenant): void
    {
        // Resolvido pelo container (e não injetado no construtor) porque o SDK
        // guarda o token estaticamente e os testes usam partialMock, que não
        // dispara a injeção de dependências do construtor.
        $token = $tenant ? app(MercadoPagoOAuthService::class)->accessTokenFor($tenant) : null;

        if ($token) {
            MercadoPagoConfig::setAccessToken($token);

            return;
        }

        $this->useGlobalToken();
    }

    private function useGlobalToken(): void
    {
        $token = config('services.mercadopago.access_token');

        if ($token) {
            MercadoPagoConfig::setAccessToken($token);
        }
    }

    private function tenantForAppointment(Appointment $appointment): ?Tenant
    {
        return $appointment->tenant_id ? Tenant::find($appointment->tenant_id) : null;
    }

    private function tenantForPayment(Payment $payment): ?Tenant
    {
        $tenantId = $payment->tenant_id ?? $payment->appointment?->tenant_id;

        return $tenantId ? Tenant::find($tenantId) : null;
    }

    public function createPix(Appointment $appointment, User $payer): Payment
    {
        $this->useTenantToken($this->tenantForAppointment($appointment));

        $order = $this->createMercadoPagoOrder(
            amountInCents: $appointment->price,
            reference: $appointment->id,
            paymentMethod: ['id' => 'pix', 'type' => 'bank_transfer'],
            payer: ['email' => $payer->email],
        );

        $payment = Payment::create([
            'appointment_id' => $appointment->id,
            'amount' => $appointment->price,
            'method' => 'pix',
            'external_id' => $order->id,
            'status' => 'pending',
            'pix_qr_code' => $this->orderPaymentMethodField($order, 'qr_code'),
            'pix_qr_code_base64' => $this->orderPaymentMethodField($order, 'qr_code_base64'),
        ]);

        $this->applyStatus($payment, $this->mapMercadoPagoStatus($order));

        return $payment->fresh();
    }

    /**
     * Cartão de crédito via Checkout Transparente (Card Payment Brick no
     * frontend gera o token — nunca recebemos os dados crus do cartão).
     * Diferente do antigo Checkout Pro, a Order API processa o pagamento de
     * forma síncrona: o resultado (aprovado/recusado) já vem na resposta do
     * create(), então aplicamos o status imediatamente em vez de esperar o
     * webhook.
     *
     * @param  array{token: string, payment_method_id: string, installments: int, issuer_id?: ?string, payer?: array<string, mixed>}  $card
     */
    public function createCheckoutPro(Appointment $appointment, User $payer, array $card): Payment
    {
        $this->useTenantToken($this->tenantForAppointment($appointment));

        $order = $this->createMercadoPagoOrder(
            amountInCents: $appointment->price,
            reference: $appointment->id,
            paymentMethod: $this->cardPaymentMethod($card),
            payer: $card['payer'] ?? ['email' => $payer->email],
        );

        $payment = Payment::create([
            'appointment_id' => $appointment->id,
            'amount' => $appointment->price,
            'method' => 'credit_card',
            'external_id' => $order->id,
            'status' => 'pending',
        ]);

        $this->applyStatus($payment, $this->mapMercadoPagoStatus($order));

        return $payment->fresh();
    }

    public function createPixForPackagePurchase(PackagePurchase $purchase, User $payer): Payment
    {
        $this->useTenantToken($purchase->tenant_id ? Tenant::find($purchase->tenant_id) : null);

        $order = $this->createMercadoPagoOrder(
            amountInCents: $purchase->price_paid,
            reference: "package_purchase_{$purchase->id}",
            paymentMethod: ['id' => 'pix', 'type' => 'bank_transfer'],
            payer: ['email' => $payer->email],
        );

        $payment = Payment::create([
            'tenant_id' => $purchase->tenant_id,
            'amount' => $purchase->price_paid,
            'method' => 'pix',
            'external_id' => $order->id,
            'status' => 'pending',
            'pix_qr_code' => $this->orderPaymentMethodField($order, 'qr_code'),
            'pix_qr_code_base64' => $this->orderPaymentMethodField($order, 'qr_code_base64'),
        ]);

        $purchase->update(['payment_id' => $payment->id]);

        $this->applyStatus($payment, $this->mapMercadoPagoStatus($order));

        return $payment->fresh();
    }

    /**
     * @param  array{token: string, payment_method_id: string, installments: int, issuer_id?: ?string, payer?: array<string, mixed>}  $card
     */
    public function createCheckoutProForPackagePurchase(PackagePurchase $purchase, User $payer, array $card): Payment
    {
        $this->useTenantToken($purchase->tenant_id ? Tenant::find($purchase->tenant_id) : null);

        $order = $this->createMercadoPagoOrder(
            amountInCents: $purchase->price_paid,
            reference: "package_purchase_{$purchase->id}",
            paymentMethod: $this->cardPaymentMethod($card),
            payer: $card['payer'] ?? ['email' => $payer->email],
        );

        $payment = Payment::create([
            'tenant_id' => $purchase->tenant_id,
            'amount' => $purchase->price_paid,
            'method' => 'credit_card',
            'external_id' => $order->id,
            'status' => 'pending',
        ]);

        $purchase->update(['payment_id' => $payment->id]);

        $this->applyStatus($payment, $this->mapMercadoPagoStatus($order));

        return $payment->fresh();
    }

    public function syncStatus(Payment $payment): Payment
    {
        if (! $payment->external_id) {
            return $payment;
        }

        // A order foi criada na conta do salão (se conectado), então a
        // consulta precisa usar o mesmo token — senão o MP devolveria 404.
        $this->useTenantToken($this->tenantForPayment($payment));

        $result = $this->fetchPayment($payment->external_id);

        $this->applyStatus($payment, $this->mapMercadoPagoStatus($result));

        return $payment->fresh();
    }

    /**
     * Busca uma Order na API do MercadoPago (extraído para permitir mock em
     * testes). `$externalId` é o id da Order (payments.external_id) — não o
     * id aninhado do pagamento dentro de transactions.payments[].
     */
    protected function fetchPayment(string $externalId): object
    {
        $client = new OrderClient;

        return $client->get($externalId);
    }

    public function handleWebhook(array $data): void
    {
        $type = $data['type'] ?? null;

        // Incerteza documentada: não conseguimos confirmar (sem acesso a um
        // webhook real de produção) se a API Orders notifica com
        // type=payment — igual à integração antiga, já que a Order ainda
        // cria um pagamento "de verdade" por baixo dos panos — ou com um
        // tópico próprio (ex.: 'order'). Aceitamos os dois por segurança.
        if (! in_array($type, ['payment', 'order'], true)) {
            return;
        }

        $externalId = (string) ($data['data']['id'] ?? '');
        if (! $externalId) {
            return;
        }

        // Best-effort: se o pagamento já foi persistido com este external_id
        // (caso do PIX, que grava o id da order na criação), usa o token do
        // salão dono do recurso para o fetch. Fluxos onde ainda não temos o
        // external_id (Checkout Pro legado) e assinaturas caem no token
        // global — assinaturas SEMPRE vivem na conta global da plataforma.
        $known = Payment::withoutTenantScope()->where('external_id', $externalId)->first();
        $this->useTenantToken($known ? $this->tenantForPayment($known) : null);

        $result = $this->fetchPayment($externalId);
        $status = $this->mapMercadoPagoStatus($result);
        $reference = (string) ($result->external_reference ?? '');

        // 1. PIX payments store the MP order id upfront.
        $payment = Payment::withoutTenantScope()->where('external_id', $externalId)->first();

        // 2. Checkout Pro (legado — pagamentos criados antes desta
        //    migração) payments only know the preference id — match the
        //    pending payment through the appointment in external_reference.
        if (! $payment && $reference
            && ! str_starts_with($reference, 'subscription_')
            && ! str_starts_with($reference, 'package_purchase_')
        ) {
            $payment = Payment::withoutTenantScope()
                ->where('appointment_id', $reference)
                ->whereNotNull('preference_id')
                ->where('status', 'pending')
                ->latest()
                ->first();

            $payment?->update(['external_id' => $externalId]);
        }

        // 3. Checkout Pro package purchase payments: the reference carries
        //    the purchase id directly, and the Payment hangs off of it via
        //    package_purchases.payment_id.
        if (! $payment && $reference && str_starts_with($reference, 'package_purchase_')) {
            $purchaseId = substr($reference, strlen('package_purchase_'));
            $purchase = PackagePurchase::withoutTenantScope()->find($purchaseId);
            $payment = $purchase?->payment;
            $payment?->update(['external_id' => $externalId]);
        }

        if ($payment) {
            $this->applyStatus($payment, $status);

            return;
        }

        $this->handleSubscriptionWebhook($externalId, $reference, $status, $result->metadata ?? null);
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

        if ($payment->appointment && $payment->appointment->status->allows('confirm')) {
            $payment->appointment->update(['status' => AppointmentStatus::Confirmed]);
        }

        $payment->appointment?->client?->notify(new PaymentApproved($payment));

        $this->activatePackagePurchase($payment);
    }

    /**
     * Reaproveitado tanto pelo webhook quanto por syncStatus (polling do
     * PIX), assim a compra ativa mesmo sem depender do webhook em dev.
     */
    private function activatePackagePurchase(Payment $payment): void
    {
        $purchase = $payment->packagePurchase;

        if (! $purchase || $purchase->status === PackagePurchaseStatus::Active) {
            return;
        }

        $purchasedAt = now();

        $purchase->update([
            'status' => PackagePurchaseStatus::Active,
            'purchased_at' => $purchasedAt,
            'expires_at' => $purchasedAt->copy()->addDays($purchase->servicePackage->valid_days),
        ]);
    }

    private function handleSubscriptionWebhook(string $externalId, string $reference, string $status, ?object $metadata = null): void
    {
        $subscription = Subscription::withoutTenantScope()->where('mp_payment_id', $externalId)->first();

        if (! $subscription) {
            // Fonte primária: metadata estruturado enviado na criação
            // (propagado pelo MP ao pagamento). Fallback: parse do
            // external_reference "subscription_{tenant_id}_{plan}".
            [$tenantId, $plan] = $this->subscriptionIdentity($metadata, $reference);

            if ($tenantId && $plan) {
                $subscription = Subscription::withoutTenantScope()
                    ->where('tenant_id', $tenantId)
                    ->where('plan', $plan)
                    ->where('status', 'pending')
                    ->whereNotNull('mp_preference_id')
                    ->latest()
                    ->first();

                $subscription?->update(['mp_payment_id' => $externalId]);
            }
        }

        $this->applySubscriptionStatus($subscription, $status);
    }

    /**
     * Extrai (tenant_id, plan) do metadata do MP ou, na falta dele,
     * do external_reference "subscription_{tenant_id}_{plan}".
     *
     * A API Orders não tem um campo `metadata` livre equivalente ao do
     * antigo PaymentClient (só `additional_info`, com schema fechado do MP)
     * — por isso, a partir desta migração, toda subscription nova (pix ou
     * cartão) grava `external_reference`, e o parse abaixo passa a ser o
     * caminho principal. O parâmetro `$metadata` continua aceito para não
     * quebrar assinaturas antigas/testes que ainda simulam esse formato.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function subscriptionIdentity(?object $metadata, string $reference): array
    {
        if ($metadata && ($metadata->type ?? null) === 'subscription') {
            return [
                $metadata->tenant_id ?? null,
                $metadata->plan ?? null,
            ];
        }

        if (! str_starts_with($reference, 'subscription_')) {
            return [null, null];
        }

        $parts = explode('_', substr($reference, strlen('subscription_')));
        $plan = array_pop($parts);
        $tenantId = implode('_', $parts);

        return [$tenantId ?: null, $plan ?: null];
    }

    private function applySubscriptionStatus(?Subscription $subscription, string $status): void
    {
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
