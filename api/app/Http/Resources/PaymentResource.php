<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'appointment_id' => $this->appointment_id,
            'amount' => $this->amount,
            'platform_fee' => $this->platform_fee,
            'method' => $this->method,
            'status' => $this->status,
            'pix_qr_code' => $this->pix_qr_code,
            'pix_qr_code_base64' => $this->pix_qr_code_base64,
            'preference_url' => $this->preference_id
                ? "https://www.mercadopago.com.br/checkout/v1/redirect?pref_id={$this->preference_id}"
                : null,
            'paid_at' => $this->paid_at?->toIso8601String(),
        ];
    }
}
