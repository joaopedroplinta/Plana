<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RescheduleAppointmentRequest;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AppointmentBooked;
use App\Notifications\AppointmentCancelled;
use App\Notifications\AppointmentConfirmed;
use App\Notifications\AppointmentRescheduled;
use App\Notifications\NewAppointmentReceived;
use App\Services\SchedulingService;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class AppointmentController extends Controller
{
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

        $professional = Professional::findOrFail($request->professional_id);
        $service = Service::findOrFail($request->service_id);
        $startsAt = Carbon::parse($request->starts_at);
        $endsAt = $startsAt->copy()->addMinutes($service->duration_minutes);

        $this->assertPlanAllowsNewAppointment($tenant, $startsAt);

        $appointment = DB::transaction(function () use ($request, $professional, $service, $startsAt, $endsAt) {
            $this->schedulingService->assertSlotAvailable($professional, $service, $startsAt);

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

        $appointment->load(['client', 'professional', 'service']);

        $request->user()->notify(new AppointmentBooked($appointment));
        Notification::send($tenant->owner, new NewAppointmentReceived($appointment));

        return (new AppointmentResource($appointment))
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

    public function noShow(Request $request, string $tenant, Appointment $appointment): AppointmentResource
    {
        Gate::authorize('noShow', $appointment);

        // Só faz sentido marcar falta depois do horário marcado.
        if ($appointment->starts_at->isFuture()) {
            throw ValidationException::withMessages([
                'status' => ['Só é possível marcar falta após o horário do agendamento.'],
            ]);
        }

        return $this->transition($appointment, 'no_show', 'no_show');
    }

    public function reschedule(RescheduleAppointmentRequest $request, string $tenant, Appointment $appointment): AppointmentResource
    {
        Gate::authorize('reschedule', $appointment);

        if (! $appointment->status->allows('reschedule')) {
            throw ValidationException::withMessages([
                'status' => ["Não é possível remarcar um agendamento com status '{$appointment->status->value}'."],
            ]);
        }

        $startsAt = Carbon::parse($request->starts_at);
        $endsAt = $startsAt->copy()->addMinutes($appointment->service->duration_minutes);

        // Remarcação feita pelo cliente volta para pending (o salão
        // reconfirma); staff remarcando mantém o status atual.
        $isStaff = $request->user()->isStaffOfTenant(app('currentTenant'));

        DB::transaction(function () use ($appointment, $startsAt, $endsAt, $isStaff) {
            $this->schedulingService->assertSlotAvailable(
                $appointment->professional,
                $appointment->service,
                $startsAt,
                $appointment->id,
            );

            $appointment->update([
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => $isStaff ? $appointment->status : 'pending',
                'reminder_sent_at' => null,
            ]);
        });

        $appointment->load(['client', 'professional', 'service']);

        $appointment->client?->notify(new AppointmentRescheduled($appointment));
        Notification::send(app('currentTenant')->owner, new AppointmentRescheduled($appointment));

        return new AppointmentResource($appointment);
    }

    private function transition(Appointment $appointment, string $action, string $newStatus): AppointmentResource
    {
        if (! $appointment->status->allows($action)) {
            throw ValidationException::withMessages([
                'status' => ["Não é possível alterar um agendamento com status '{$appointment->status->value}'."],
            ]);
        }

        $appointment->update(['status' => $newStatus]);
        $appointment->load(['client', 'professional', 'service']);

        $this->notifyTransition($appointment, $newStatus);

        return new AppointmentResource($appointment);
    }

    private function notifyTransition(Appointment $appointment, string $newStatus): void
    {
        /** @var Tenant $tenant */
        $tenant = app('currentTenant');

        if ($newStatus === 'confirmed') {
            $appointment->client?->notify(new AppointmentConfirmed($appointment));
        }

        if ($newStatus === 'cancelled') {
            $appointment->client?->notify(new AppointmentCancelled($appointment));
            Notification::send($tenant->owner, new AppointmentCancelled($appointment));
        }
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
