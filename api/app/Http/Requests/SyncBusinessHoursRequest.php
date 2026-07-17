<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class SyncBusinessHoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->ownsTenant(app('currentTenant'));
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'days' => ['required', 'array', 'max:7'],
            'days.*.day_of_week' => ['required', 'integer', 'min:0', 'max:6', 'distinct'],
            'days.*.is_open' => ['required', 'boolean'],
            'days.*.open_time' => ['nullable', 'date_format:H:i'],
            'days.*.close_time' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * Dia aberto exige abertura/fechamento válidos (fechamento após abertura).
     * Feito aqui porque `required_if` não resolve o wildcard por item.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                foreach ((array) $this->input('days', []) as $i => $day) {
                    if (empty($day['is_open'])) {
                        continue;
                    }

                    $open = $day['open_time'] ?? null;
                    $close = $day['close_time'] ?? null;

                    if (! $open || ! $close) {
                        $validator->errors()->add("days.{$i}.open_time", 'Informe abertura e fechamento para o dia aberto.');

                        continue;
                    }

                    if ($close <= $open) {
                        $validator->errors()->add("days.{$i}.close_time", 'O fechamento deve ser após a abertura.');
                    }
                }
            },
        ];
    }
}
