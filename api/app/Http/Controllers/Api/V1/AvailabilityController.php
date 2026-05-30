<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\BlockedDate;
use App\Models\Schedule;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'professional_id' => ['required', 'uuid'],
            'service_id' => ['required', 'uuid'],
            'date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $date = Carbon::parse($data['date']);
        $service = Service::withoutTenantScope()->findOrFail($data['service_id']);
        $duration = $service->duration_minutes;

        $schedule = Schedule::withoutTenantScope()
            ->where('professional_id', $data['professional_id'])
            ->where('day_of_week', $date->dayOfWeek)
            ->first();

        if (! $schedule) {
            return response()->json(['data' => []]);
        }

        $blocked = BlockedDate::withoutTenantScope()
            ->where('professional_id', $data['professional_id'])
            ->whereDate('date', $date)
            ->exists();

        if ($blocked) {
            return response()->json(['data' => []]);
        }

        $existing = Appointment::withoutTenantScope()
            ->where('professional_id', $data['professional_id'])
            ->whereNotIn('status', ['cancelled'])
            ->whereDate('starts_at', $date)
            ->get(['starts_at', 'ends_at']);

        $slots = [];
        $start = Carbon::parse($date->format('Y-m-d').' '.$schedule->start_time);
        $end = Carbon::parse($date->format('Y-m-d').' '.$schedule->end_time);

        while ($start->copy()->addMinutes($duration)->lte($end)) {
            $slotEnd = $start->copy()->addMinutes($duration);

            $occupied = $existing->first(
                fn ($appt) => $appt->starts_at->lt($slotEnd) && $appt->ends_at->gt($start)
            );

            if (! $occupied) {
                $slots[] = [
                    'starts_at' => $start->format('H:i'),
                    'ends_at' => $slotEnd->format('H:i'),
                ];
            }

            $start->addMinutes($duration);
        }

        return response()->json(['data' => $slots]);
    }
}
