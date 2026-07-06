<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $tenantId = app('currentTenant')?->id;

        return [
            'professional_id' => [
                'required',
                'uuid',
                Rule::exists('professionals', 'id')->where('tenant_id', $tenantId),
            ],
            'service_id' => [
                'required',
                'uuid',
                Rule::exists('services', 'id')->where('tenant_id', $tenantId),
            ],
            'starts_at' => ['required', 'date', 'after:now'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
