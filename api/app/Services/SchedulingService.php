<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\BlockedDate;
use App\Models\Professional;
use App\Models\Schedule;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class SchedulingService
{
    /**
     * Validate every business rule for a new appointment slot.
     * Must run inside a transaction — the conflict check locks
     * the professional's appointments to prevent double booking.
     * Pass $ignoreAppointmentId when rescheduling so the appointment
     * doesn't conflict with its own current slot.
     *
     * @throws ValidationException
     */
    public function assertSlotAvailable(Professional $professional, Service $service, Carbon $startsAt, ?string $ignoreAppointmentId = null): void
    {
        if (! $professional->active || ! $service->active) {
            throw ValidationException::withMessages([
                'starts_at' => ['Profissional ou serviço indisponível para agendamento.'],
            ]);
        }

        $endsAt = $startsAt->copy()->addMinutes($service->duration_minutes);

        $schedule = Schedule::query()
            ->where('professional_id', $professional->id)
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

        if (! $this->isAlignedToGrid($dayStart, $startsAt, $service->duration_minutes)) {
            throw ValidationException::withMessages([
                'starts_at' => ['Horário inválido para a duração do serviço.'],
            ]);
        }

        $blocked = BlockedDate::query()
            ->where('professional_id', $professional->id)
            ->whereDate('date', $startsAt->toDateString())
            ->exists();

        if ($blocked) {
            throw ValidationException::withMessages([
                'starts_at' => ['O profissional não atende nesta data.'],
            ]);
        }

        if ($this->hasConflict($professional->id, $startsAt, $endsAt, $ignoreAppointmentId, lock: true)) {
            throw ValidationException::withMessages([
                'starts_at' => ['Horário indisponível.'],
            ]);
        }
    }

    /**
     * List the free slots for a professional/service on a given date.
     * Pass $ignoreAppointmentId when listing slots for a reschedule so the
     * appointment's own current slot isn't reported as occupied.
     *
     * @return list<array{starts_at: string, ends_at: string}>
     */
    public function availableSlots(Professional $professional, Service $service, Carbon $date, ?string $ignoreAppointmentId = null): array
    {
        if (! $professional->active || ! $service->active) {
            return [];
        }

        $duration = $service->duration_minutes;

        $schedule = Schedule::query()
            ->where('professional_id', $professional->id)
            ->where('day_of_week', $date->dayOfWeek)
            ->first();

        if (! $schedule) {
            return [];
        }

        $blocked = BlockedDate::query()
            ->where('professional_id', $professional->id)
            ->whereDate('date', $date)
            ->exists();

        if ($blocked) {
            return [];
        }

        $slots = [];
        $start = $date->copy()->setTimeFromTimeString($schedule->start_time);
        $end = $date->copy()->setTimeFromTimeString($schedule->end_time);
        $now = now();

        while ($start->copy()->addMinutes($duration)->lte($end)) {
            $slotEnd = $start->copy()->addMinutes($duration);
            $isPast = $start->lte($now);

            if (! $isPast && ! $this->hasConflict($professional->id, $start, $slotEnd, $ignoreAppointmentId)) {
                $slots[] = [
                    'starts_at' => $start->format('H:i'),
                    'ends_at' => $slotEnd->format('H:i'),
                ];
            }

            $start->addMinutes($duration);
        }

        return $slots;
    }

    private function isAlignedToGrid(Carbon $dayStart, Carbon $startsAt, int $duration): bool
    {
        $minutesFromStart = (int) $dayStart->diffInMinutes($startsAt);

        return $minutesFromStart % $duration === 0;
    }

    private function hasConflict(string $professionalId, Carbon $startsAt, Carbon $endsAt, ?string $ignoreAppointmentId, bool $lock = false): bool
    {
        $query = Appointment::query()
            ->where('professional_id', $professionalId)
            ->whereNotIn('status', ['cancelled'])
            ->when($ignoreAppointmentId, fn ($q) => $q->where('id', '!=', $ignoreAppointmentId))
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->exists();
    }
}
