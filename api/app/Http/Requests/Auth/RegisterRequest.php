<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            // owner (padrão) cria um salão novo; client cria conta apenas para agendar.
            'account_type' => ['sometimes', 'in:owner,client'],
            'salon_name' => ['sometimes', 'string', 'max:255'],
            'tenant_slug' => ['sometimes', 'string', 'exists:tenants,slug'],
        ];
    }
}
