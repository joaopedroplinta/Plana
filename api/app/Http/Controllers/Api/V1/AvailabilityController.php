<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\BlockedDate;
use App\Models\Professional;
use App\Models\Schedule;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AvailabilityController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $tenantId = app('currentTenant')->id;

        $data = $request->validate([
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
            'date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $date = Carbon::parse($data['date']);
        $professional = Professional::findOrFail($data['professional_id']);
        $service = Service::findOrFail($data['service_id']);
        $duration = $service->duration_minutes;

        if (! $professional->active || ! $service->active) {
            return response()->json(['data' => []]);
        }

        $schedule = Schedule::query()
            ->where('professional_id', $professional->id)
            ->where('day_of_week', $date->dayOfWeek)
            ->first();

        if (! $schedule) {
            return response()->json(['data' => []]);
        }

        $blocked = BlockedDate::query()
            ->where('professional_id', $professional->id)
            ->whereDate('date', $date)
            ->exists();

        if ($blocked) {
            return response()->json(['data' => []]);
        }

        $existing = Appointment::query()
            ->where('professional_id', $professional->id)
            ->whereNotIn('status', ['cancelled'])
            ->whereDate('starts_at', $date)
            ->get(['starts_at', 'ends_at']);

        $slots = [];
        $start = Carbon::parse($date->format('Y-m-d').' '.$schedule->start_time);
        $end = Carbon::parse($date->format('Y-m-d').' '.$schedule->end_time);
        $now = now();

        while ($start->copy()->addMinutes($duration)->lte($end)) {
            $slotEnd = $start->copy()->addMinutes($duration);

            $isPast = $start->lte($now);

            $occupied = $existing->first(
                fn ($appt) => $appt->starts_at->lt($slotEnd) && $appt->ends_at->gt($start)
            );

            if (! $occupied && ! $isPast) {
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
