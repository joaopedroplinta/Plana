<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Professional;
use App\Models\Service;
use App\Services\SchedulingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AvailabilityController extends Controller
{
    public function __construct(private readonly SchedulingService $schedulingService) {}

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
            'ignore_appointment_id' => [
                'nullable',
                'uuid',
                Rule::exists('appointments', 'id')->where('tenant_id', $tenantId),
            ],
        ]);

        $date = Carbon::parse($data['date']);
        $professional = Professional::findOrFail($data['professional_id']);
        $service = Service::findOrFail($data['service_id']);

        $slots = $this->schedulingService->availableSlots(
            $professional,
            $service,
            $date,
            $data['ignore_appointment_id'] ?? null,
        );

        return response()->json(['data' => $slots]);
    }
}
