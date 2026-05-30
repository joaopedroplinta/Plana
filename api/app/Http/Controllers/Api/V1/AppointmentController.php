<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AppointmentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Appointment::class);

        $query = Appointment::with(['client', 'professional', 'service'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->date, fn ($q, $d) => $q->whereDate('starts_at', $d))
            ->when($request->professional_id, fn ($q, $id) => $q->where('professional_id', $id))
            ->orderBy('starts_at', 'desc');

        if ($request->user()->hasRole('client')) {
            $query->where('client_id', $request->user()->id);
        }

        return AppointmentResource::collection($query->paginate(20));
    }

    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        Gate::authorize('create', Appointment::class);

        $service = Service::withoutTenantScope()->findOrFail($request->service_id);
        $startsAt = Carbon::parse($request->starts_at);
        $endsAt = $startsAt->copy()->addMinutes($service->duration_minutes);

        $conflict = Appointment::withoutTenantScope()
            ->where('professional_id', $request->professional_id)
            ->whereNotIn('status', ['cancelled'])
            ->where(function ($q) use ($startsAt, $endsAt) {
                $q->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt);
            })
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages(['starts_at' => ['Horário indisponível.']]);
        }

        $appointment = Appointment::create([
            'client_id' => $request->user()->id,
            'professional_id' => $request->professional_id,
            'service_id' => $request->service_id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => 'pending',
            'price' => $service->price,
            'notes' => $request->notes,
        ]);

        return (new AppointmentResource($appointment->load(['client', 'professional', 'service'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, string $tenant, Appointment $appointment): AppointmentResource
    {
        Gate::authorize('view', $appointment);

        return new AppointmentResource($appointment->load(['client', 'professional', 'service']));
    }

    public function confirm(Request $request, string $tenant, Appointment $appointment): AppointmentResource
    {
        Gate::authorize('confirm', $appointment);

        $appointment->update(['status' => 'confirmed']);

        return new AppointmentResource($appointment->load(['client', 'professional', 'service']));
    }

    public function cancel(Request $request, string $tenant, Appointment $appointment): AppointmentResource
    {
        Gate::authorize('cancel', $appointment);

        $appointment->update(['status' => 'cancelled']);

        return new AppointmentResource($appointment->load(['client', 'professional', 'service']));
    }

    public function complete(Request $request, string $tenant, Appointment $appointment): AppointmentResource
    {
        Gate::authorize('complete', $appointment);

        $appointment->update(['status' => 'completed']);

        return new AppointmentResource($appointment->load(['client', 'professional', 'service']));
    }
}
