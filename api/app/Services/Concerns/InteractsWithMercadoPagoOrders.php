<?php

namespace App\Services\Concerns;

use Illuminate\Support\Str;
use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Client\Order\OrderClient;
use MercadoPago\Resources\Order;

/**
 * Compartilhado por PaymentService e SubscriptionService — ambos criam e
 * consultam MercadoPago\Client\Order\OrderClient (API Orders, /v1/orders),
 * que substituiu PaymentClient (PIX) e PreferenceClient (Checkout Pro) nesta
 * aplicação porque a credencial de teste do projeto só é aceita por /v1/orders.
 *
 * @see https://www.mercadopago.com/developers/en/reference/order/_v1_orders/post
 */
trait InteractsWithMercadoPagoOrders
{
    /**
     * Status internos herdados da antiga integração (PaymentClient/
     * PreferenceClient) e usados nos testes desta suíte, que mockam
     * fetchPayment()/syncStatus() devolvendo esse vocabulário diretamente
     * (ex.: 'approved'). Tratados como passthrough para não quebrar esses
     * mocks nem dados históricos — a API Orders nunca devolve esses valores
     * de verdade no campo `status` (ver mapMercadoPagoStatus()).
     *
     * @var list<string>
     */
    private const LEGACY_STATUSES = ['approved', 'pending', 'in_process', 'rejected', 'cancelled', 'refunded', 'charged_back'];

    /**
     * Cria uma Order (/v1/orders) com um único pagamento (pix ou cartão).
     *
     * Marketplace (Fase 2): quando `$marketplaceFeeInCents` é informado (só nos
     * pagamentos de agendamento feitos numa conta de salão CONECTADA), a
     * plataforma retém esse valor via `marketplace_fee` — campo de nível da
     * order, string em reais (ex.: "2.50"), conforme a API Orders
     * (@see vendor/mercadopago/dx-php/examples/Order/CreateOrderWithIndustryFields.php).
     * Sem fee (null), a order segue idêntica à Fase 1.
     *
     * @param  array<string, mixed>  $paymentMethod
     * @param  array<string, mixed>  $payer
     */
    protected function createMercadoPagoOrder(int $amountInCents, string $reference, array $paymentMethod, array $payer, ?int $marketplaceFeeInCents = null): Order
    {
        $client = new OrderClient;
        $amount = (string) round($amountInCents / 100, 2);

        $requestOptions = new RequestOptions;
        $requestOptions->setCustomHeaders(['X-Idempotency-Key: '.(string) Str::uuid()]);

        $body = [
            'type' => 'online',
            'total_amount' => $amount,
            'external_reference' => $reference,
            'processing_mode' => 'automatic',
            'transactions' => [
                'payments' => [[
                    'amount' => $amount,
                    'payment_method' => $paymentMethod,
                ]],
            ],
            'payer' => $payer,
        ];

        if ($marketplaceFeeInCents !== null && $marketplaceFeeInCents > 0) {
            $body['marketplace_fee'] = (string) round($marketplaceFeeInCents / 100, 2);
        }

        return $client->create($body, $requestOptions);
    }

    /**
     * Monta o `payment_method` de cartão a partir dos dados tokenizados no
     * frontend pelo Card Payment Brick (MercadoPago.js) — nunca recebemos o
     * número do cartão em si, só o token.
     *
     * @param  array{token: string, payment_method_id: string, installments: int, issuer_id?: ?string}  $card
     * @return array<string, mixed>
     */
    private function cardPaymentMethod(array $card): array
    {
        return array_filter([
            'id' => $card['payment_method_id'],
            'type' => 'credit_card',
            'token' => $card['token'],
            'installments' => $card['installments'],
            'issuer_id' => $card['issuer_id'] ?? null,
        ], fn ($value) => $value !== null);
    }

    /**
     * O `payment_method` (com qr_code/qr_code_base64 no caso do PIX) do
     * primeiro (e único, neste projeto) pagamento dentro da order.
     */
    private function orderPaymentMethod(Order $order): ?object
    {
        return $order->transactions->payments[0]->payment_method ?? null;
    }

    /**
     * Acesso seguro a um campo do payment_method da order.
     *
     * Importante: `MercadoPago\Resources\Order\PaymentMethod` declara suas
     * propriedades (`qr_code`, `qr_code_base64`, etc.) como `public
     * ?string` SEM valor padrão — em PHP, campos assim ficam "não
     * inicializados" quando o JSON de resposta não os inclui (ex.:
     * pagamento de cartão não tem qr_code), e acessá-los diretamente
     * (`$paymentMethod->qr_code`) lança `Error: must not be accessed
     * before initialization` em vez de devolver null. `??` é seguro aqui
     * porque seu operando esquerdo é avaliado com semântica de `isset()`.
     */
    private function orderPaymentMethodField(Order $order, string $field): ?string
    {
        return $this->orderPaymentMethod($order)?->{$field} ?? null;
    }

    /**
     * Mapeia o status de uma Order (ou de um mock/objeto legado usado nos
     * testes) para o vocabulário interno já usado em payments.status /
     * subscriptions.status: pending, approved, cancelled, refunded.
     *
     * Decisão de design: a Order API usa um vocabulário de status
     * diferente do antigo endpoint /v1/payments ('processed' em vez de
     * 'approved', 'action_required' em vez de 'pending', etc. — ver
     * vendor/mercadopago/dx-php/tests/.../Mocks/Response/Order/*.json).
     * Optamos por 'status_detail' == 'accredited' OU status == 'processed'
     * como sinal de aprovação (o PIX chega com status_detail
     * 'waiting_transfer' + status 'action_required' enquanto pendente).
     */
    private function mapMercadoPagoStatus(object $result): string
    {
        $status = $result->status ?? 'pending';
        $statusDetail = $result->status_detail ?? null;

        if (in_array($status, self::LEGACY_STATUSES, true)) {
            return $status;
        }

        return match (true) {
            $status === 'processed' && is_string($statusDetail) && str_contains($statusDetail, 'refund') => 'refunded',
            $status === 'processed' => 'approved',
            in_array($status, ['canceled', 'cancelled'], true) => 'cancelled',
            $status === 'expired' => 'cancelled',
            in_array($status, ['refunded', 'partially_refunded'], true) => 'refunded',
            default => 'pending', // action_required, etc.
        };
    }
}
