<?php

namespace App\Http\Resources;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Estado da conexão MercadoPago de um tenant. NUNCA expõe tokens — apenas o
 * status público (conectado ou não, quando, e o id do vendedor no MP).
 *
 * @mixin Tenant
 */
class MercadoPagoStatusResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'connected' => $this->hasMercadoPagoConnected(),
            'connected_at' => $this->mp_connected_at?->toIso8601String(),
            'mp_user_id' => $this->mp_user_id,
        ];
    }
}
