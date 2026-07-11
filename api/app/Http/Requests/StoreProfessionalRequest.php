<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProfessionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isStaffOfTenant(app('currentTenant'));
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'avatar_url' => ['nullable', 'string', 'max:500'],
            'active' => ['boolean'],
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('tenant_user', 'user_id')->where('tenant_id', app('currentTenant')->id),
            ],
        ];
    }
}
