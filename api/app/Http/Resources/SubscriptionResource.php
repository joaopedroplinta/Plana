<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
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
            'plan' => $this->plan,
            'billing_cycle' => $this->billing_cycle,
            'amount' => $this->amount,
            'method' => $this->method,
            'status' => $this->status,
            'pix_qr_code' => $this->pix_qr_code,
            'pix_qr_code_base64' => $this->pix_qr_code_base64,
            'mp_preference_id' => $this->mp_preference_id,
            'paid_at' => $this->paid_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
