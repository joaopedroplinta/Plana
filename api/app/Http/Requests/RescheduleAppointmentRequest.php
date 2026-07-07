<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RescheduleAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // A policy (reschedule) decide entre staff e o próprio cliente.
        return $this->user() !== null;
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return [
            'starts_at' => ['required', 'date', 'after:now'],
        ];
    }
}
