<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SchedulingService;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class AppointmentController extends Controller
{
    /** Transições de status permitidas. */
    private const TRANSITIONS = [
        'confirm' => ['pending'],
        'cancel' => ['pending', 'confirmed'],
        'complete' => ['pending', 'confirmed'],
    ];

    public function __construct(private readonly SchedulingService $schedulingService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Appointment::class);

        $query = Appointment::with(['client', 'professional', 'service'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->date, fn ($q, $d) => $q->whereDate('starts_at', $d))
            ->when($request->professional_id, fn ($q, $id) => $q->where('professional_id', $id))
            ->orderBy('starts_at', 'desc');

        if (! $request->user()->isStaffOfTenant(app('currentTenant'))) {
            $query->where('client_id', $request->user()->id);
        }

        return AppointmentResource::collection($query->paginate(20));
    }

    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        Gate::authorize('create', Appointment::class);

        /** @var Tenant $tenant */
        $tenant = app('currentTenant');

        $service = Service::findOrFail($request->service_id);
        $startsAt = Carbon::parse($request->starts_at);
        $endsAt = $startsAt->copy()->addMinutes($service->duration_minutes);

        $this->assertPlanAllowsNewAppointment($tenant, $startsAt);

        $appointment = DB::transaction(function () use ($request, $service, $startsAt, $endsAt) {
            $this->schedulingService->assertSlotAvailable($request->professional_id, $startsAt, $endsAt);

            return Appointment::create([
                'client_id' => $request->user()->id,
                'professional_id' => $request->professional_id,
                'service_id' => $request->service_id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => 'pending',
                'price' => $service->price,
                'notes' => $request->notes,
            ]);
        });

        $this->attachClientToTenant($request->user(), $tenant);

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

        return $this->transition($appointment, 'confirm', 'confirmed');
    }

    public function cancel(Request $request, string $tenant, Appointment $appointment): AppointmentResource
    {
        Gate::authorize('cancel', $appointment);

        return $this->transition($appointment, 'cancel', 'cancelled');
    }

    public function complete(Request $request, string $tenant, Appointment $appointment): AppointmentResource
    {
        Gate::authorize('complete', $appointment);

        return $this->transition($appointment, 'complete', 'completed');
    }

    private function transition(Appointment $appointment, string $action, string $newStatus): AppointmentResource
    {
        if (! in_array($appointment->status, self::TRANSITIONS[$action], true)) {
            throw ValidationException::withMessages([
                'status' => ["Não é possível alterar um agendamento com status '{$appointment->status}'."],
            ]);
        }

        $appointment->update(['status' => $newStatus]);

        return new AppointmentResource($appointment->load(['client', 'professional', 'service']));
    }

    private function assertPlanAllowsNewAppointment(Tenant $tenant, Carbon $startsAt): void
    {
        $limit = SubscriptionService::maxAppointmentsPerMonth($tenant->plan);

        if ($limit === null) {
            return;
        }

        $count = Appointment::query()
            ->whereNotIn('status', ['cancelled'])
            ->whereBetween('starts_at', [
                $startsAt->copy()->startOfMonth(),
                $startsAt->copy()->endOfMonth(),
            ])
            ->count();

        if ($count >= $limit) {
            throw ValidationException::withMessages([
                'starts_at' => ["O salão atingiu o limite de {$limit} agendamentos neste mês. Fale com o salão para mais informações."],
            ]);
        }
    }

    /**
     * Um usuário autenticado que agenda em um salão passa a ser
     * cliente daquele salão (vínculo criado no primeiro agendamento).
     */
    private function attachClientToTenant(User $user, Tenant $tenant): void
    {
        if ($user->belongsToTenant($tenant)) {
            return;
        }

        $tenant->users()->attach($user->id, ['role' => 'client']);

        if (! $user->hasAnyRole(['salon_owner', 'salon_staff', 'client', 'super_admin'])) {
            Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
            $user->assignRole('client');
        }
    }
}
