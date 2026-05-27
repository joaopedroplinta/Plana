<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreServicePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole(['salon_owner', 'salon_staff']);
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'integer', 'min:0'],
            'sessions' => ['required', 'integer', 'min:1', 'max:255'],
            'valid_days' => ['required', 'integer', 'min:1'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['uuid', 'exists:services,id'],
        ];
    }
}
