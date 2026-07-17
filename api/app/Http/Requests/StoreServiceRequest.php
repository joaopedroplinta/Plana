<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isStaffOfTenant(app('currentTenant'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'integer', 'min:0'],
            // Sinal do serviço: null herda o padrão do salão; 'none' desativa;
            // 'fixed' = centavos; 'percentage' = 1..100% do preço.
            'deposit_type' => ['nullable', Rule::in(['none', 'fixed', 'percentage'])],
            'deposit_value' => [
                'nullable',
                'integer',
                'min:1',
                'required_if:deposit_type,fixed,percentage',
                Rule::when($this->input('deposit_type') === 'percentage', ['max:100']),
            ],
            'duration_minutes' => ['required', 'integer', 'min:15'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'active' => ['boolean'],
        ];
    }
}
