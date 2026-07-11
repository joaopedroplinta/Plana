<?php

namespace App\Http\Controllers\Api\V1\Concerns;

/**
 * Compartilhado por PaymentController, PackagePurchaseController e
 * SubscriptionController — todos aceitam `method: pix|credit_card` e, no
 * caso de cartão, os dados tokenizados pelo Card Payment Brick no frontend
 * (Checkout Transparente via API Orders).
 *
 * Os campos de cartão são `nullable` (não `required_if:method,credit_card`)
 * de propósito: testes desta suíte que mockam o *Service (não a chamada
 * real ao MercadoPago) enviam `method: credit_card` sem token algum, e
 * devem continuar passando sem alteração. Em produção, sem token, a própria
 * API Orders do MercadoPago rejeita a criação da order — o erro só chega
 * mais tarde (na camada do SDK) em vez de na validação do Form Request.
 */
trait HandlesCardPaymentData
{
    /**
     * @return array<string, list<string>>
     */
    protected function paymentValidationRules(): array
    {
        return [
            'method' => ['required', 'in:pix,credit_card'],
            'token' => ['nullable', 'string'],
            'payment_method_id' => ['nullable', 'string'],
            'installments' => ['nullable', 'integer', 'min:1'],
            'issuer_id' => ['nullable', 'string'],
            'payer' => ['nullable', 'array'],
            'payer.email' => ['nullable', 'email'],
            'payer.identification' => ['nullable', 'array'],
            'payer.identification.type' => ['nullable', 'string'],
            'payer.identification.number' => ['nullable', 'string'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{token: string, payment_method_id: string, installments: int, issuer_id: ?string, payer: ?array<string, mixed>}
     */
    protected function cardData(array $data): array
    {
        return [
            'token' => (string) ($data['token'] ?? ''),
            'payment_method_id' => (string) ($data['payment_method_id'] ?? ''),
            'installments' => (int) ($data['installments'] ?? 1),
            'issuer_id' => $data['issuer_id'] ?? null,
            'payer' => $data['payer'] ?? null,
        ];
    }
}
