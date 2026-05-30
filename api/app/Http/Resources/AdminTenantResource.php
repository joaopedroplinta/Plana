<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminTenantResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $owner = $this->whenLoaded(
            'users',
            fn () => $this->users->first(fn ($u) => $u->pivot->role === 'owner')
        );

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'plan' => $this->plan,
            'active' => $this->active,
            'created_at' => $this->created_at,
            'trial_ends_at' => $this->trial_ends_at,
            'user_count' => $this->whenLoaded('users', fn () => $this->users->count(), 0),
            'owner' => $owner ? ['name' => $owner->name, 'email' => $owner->email] : null,
        ];
    }
}
