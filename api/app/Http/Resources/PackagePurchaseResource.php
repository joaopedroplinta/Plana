<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackagePurchaseResource extends JsonResource
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
            'service_package' => new ServicePackageResource($this->whenLoaded('servicePackage')),
            'sessions_total' => $this->sessions_total,
            'sessions_used' => $this->sessions_used,
            'sessions_remaining' => $this->sessionsRemaining(),
            'price_paid' => $this->price_paid,
            'status' => $this->status->value,
            'purchased_at' => $this->purchased_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'payment' => $this->whenLoaded('payment', fn () => $this->payment ? new PaymentResource($this->payment) : null),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
