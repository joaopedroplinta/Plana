<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->ownsTenant(app('currentTenant'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'whatsapp' => ['sometimes', 'nullable', 'string', 'max:30'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'instagram' => ['sometimes', 'nullable', 'string', 'max:100'],
            // Sinal padrão do salão: 'none' (nenhum), 'fixed' (centavos) ou
            // 'percentage' (1..100% do preço). Aplicado a todo serviço que não
            // tenha override próprio.
            'deposit_type' => ['sometimes', Rule::in(['none', 'fixed', 'percentage'])],
            'deposit_value' => [
                'nullable',
                'integer',
                'min:1',
                'required_if:deposit_type,fixed,percentage',
                Rule::when($this->input('deposit_type') === 'percentage', ['max:100']),
            ],
        ];
    }
}
