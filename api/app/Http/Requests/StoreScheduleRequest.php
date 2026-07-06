<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isStaffOfTenant(app('currentTenant'));
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'day_of_week' => [
                'required',
                'integer',
                'min:0',
                'max:6',
                Rule::unique('schedules', 'day_of_week')
                    ->where('professional_id', $this->route('professional')?->id),
            ],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ];
    }
}
