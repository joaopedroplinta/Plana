<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->resource['token'],
            'user' => new UserResource($this->resource['user']),
            'tenant' => $this->resource['tenant']
                ? new TenantResource($this->resource['tenant'])
                : null,
        ];
    }
}
