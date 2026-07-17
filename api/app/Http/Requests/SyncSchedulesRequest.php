<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class SyncSchedulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isStaffOfTenant(app('currentTenant'));
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'schedules' => ['present', 'array', 'max:7'],
            'schedules.*.day_of_week' => ['required', 'integer', 'min:0', 'max:6', 'distinct'],
            'schedules.*.start_time' => ['required', 'date_format:H:i'],
            'schedules.*.end_time' => ['required', 'date_format:H:i'],
        ];
    }

    /**
     * Fim do expediente deve ser após o início (por item — o wildcard não é
     * resolvido em regras como `after`).
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                foreach ((array) $this->input('schedules', []) as $i => $day) {
                    $start = $day['start_time'] ?? null;
                    $end = $day['end_time'] ?? null;

                    if ($start && $end && $end <= $start) {
                        $validator->errors()->add("schedules.{$i}.end_time", 'O fim deve ser após o início.');
                    }
                }
            },
        ];
    }
}
