<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $settings = $this->settings ?? [];

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'plan' => $this->plan,
            'active' => $this->active,
            'description' => $settings['description'] ?? null,
            'phone' => $settings['phone'] ?? null,
            'whatsapp' => $settings['whatsapp'] ?? null,
            'address' => $settings['address'] ?? null,
            'instagram' => $settings['instagram'] ?? null,
        ];
    }
}
