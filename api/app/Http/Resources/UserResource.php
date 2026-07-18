<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'notes' => $this->notes,
            'avatar_url' => $this->avatar_url,
            'email_verified_at' => $this->email_verified_at,
            'roles' => $this->getRoleNames(),
            'tenant' => $this->whenLoaded('tenants', fn () => new TenantResource($this->tenants->first())),
        ];
    }
}
