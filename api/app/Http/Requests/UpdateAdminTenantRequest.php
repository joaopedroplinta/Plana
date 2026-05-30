<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'plan' => ['sometimes', 'string', Rule::in(['starter', 'pro', 'enterprise'])],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
