<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
