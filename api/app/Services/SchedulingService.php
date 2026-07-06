<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\BlockedDate;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class SchedulingService
{
    /**
     * Validate every business rule for a new appointment slot.
     * Must run inside a transaction — the conflict check locks
     * the professional's appointments to prevent double booking.
     *
     * @throws ValidationException
     */
    public function assertSlotAvailable(string $professionalId, Carbon $startsAt, Carbon $endsAt): void
    {
        $schedule = Schedule::query()
            ->where('professional_id', $professionalId)
            ->where('day_of_week', $startsAt->dayOfWeek)
            ->first();

        if (! $schedule) {
            throw ValidationException::withMessages([
                'starts_at' => ['O profissional não atende neste dia.'],
            ]);
        }

        $dayStart = $startsAt->copy()->setTimeFromTimeString($schedule->start_time);
        $dayEnd = $startsAt->copy()->setTimeFromTimeString($schedule->end_time);

        if ($startsAt->lt($dayStart) || $endsAt->gt($dayEnd)) {
            throw ValidationException::withMessages([
                'starts_at' => ['Horário fora do expediente do profissional.'],
            ]);
        }

        $blocked = BlockedDate::query()
            ->where('professional_id', $professionalId)
            ->whereDate('date', $startsAt->toDateString())
            ->exists();

        if ($blocked) {
            throw ValidationException::withMessages([
                'starts_at' => ['O profissional não atende nesta data.'],
            ]);
        }

        $conflict = Appointment::query()
            ->where('professional_id', $professionalId)
            ->whereNotIn('status', ['cancelled'])
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->lockForUpdate()
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'starts_at' => ['Horário indisponível.'],
            ]);
        }
    }
}
