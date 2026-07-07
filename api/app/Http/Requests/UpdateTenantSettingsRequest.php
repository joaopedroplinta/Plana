<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->ownsTenant(app('currentTenant'));
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'whatsapp' => ['sometimes', 'nullable', 'string', 'max:30'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'instagram' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
