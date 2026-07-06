<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateScheduleRequest extends FormRequest
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
                'sometimes',
                'integer',
                'min:0',
                'max:6',
                Rule::unique('schedules', 'day_of_week')
                    ->where('professional_id', $this->route('professional')?->id)
                    ->ignore($this->route('schedule')?->id),
            ],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time' => ['sometimes', 'date_format:H:i', ...$this->endTimeAfterRule()],
        ];
    }

    /**
     * end_time deve ser depois do start_time enviado ou, em updates
     * parciais, do start_time já salvo no schedule.
     *
     * @return array<string>
     */
    private function endTimeAfterRule(): array
    {
        $start = $this->input('start_time') ?? $this->route('schedule')?->start_time;

        return $start ? ["after:{$start}"] : [];
    }
}
