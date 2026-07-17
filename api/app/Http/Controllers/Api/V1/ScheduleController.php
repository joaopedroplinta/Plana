<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScheduleRequest;
use App\Http\Requests\SyncSchedulesRequest;
use App\Http\Requests\UpdateScheduleRequest;
use App\Http\Resources\ScheduleResource;
use App\Models\Professional;
use App\Models\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ScheduleController extends Controller
{
    public function index(Request $request, string $tenant, Professional $professional): AnonymousResourceCollection
    {
        $schedules = Schedule::where('professional_id', $professional->id)
            ->orderBy('day_of_week')
            ->get();

        return ScheduleResource::collection($schedules);
    }

    /**
     * Substitui de uma vez toda a semana de trabalho do profissional (o editor
     * semanal do dashboard envia todos os dias trabalhados). Dias ausentes =
     * folga. Mais simples e atômico que orquestrar POST/PUT/DELETE por dia.
     */
    public function sync(SyncSchedulesRequest $request, string $tenant, Professional $professional): AnonymousResourceCollection
    {
        Gate::authorize('create', Schedule::class);

        $schedules = DB::transaction(function () use ($request, $professional) {
            Schedule::where('professional_id', $professional->id)->delete();

            foreach ($request->validated('schedules') as $day) {
                Schedule::create([
                    'professional_id' => $professional->id,
                    'day_of_week' => $day['day_of_week'],
                    'start_time' => $day['start_time'],
                    'end_time' => $day['end_time'],
                ]);
            }

            return Schedule::where('professional_id', $professional->id)
                ->orderBy('day_of_week')
                ->get();
        });

        return ScheduleResource::collection($schedules);
    }

    public function store(StoreScheduleRequest $request, string $tenant, Professional $professional): JsonResponse
    {
        Gate::authorize('create', Schedule::class);

        $schedule = Schedule::create(array_merge(
            $request->validated(),
            ['professional_id' => $professional->id]
        ));

        return (new ScheduleResource($schedule))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, string $tenant, Professional $professional, Schedule $schedule): ScheduleResource
    {
        return new ScheduleResource($schedule);
    }

    public function update(UpdateScheduleRequest $request, string $tenant, Professional $professional, Schedule $schedule): ScheduleResource
    {
        Gate::authorize('update', $schedule);

        $schedule->update($request->validated());

        return new ScheduleResource($schedule);
    }

    public function destroy(Request $request, string $tenant, Professional $professional, Schedule $schedule): JsonResponse
    {
        Gate::authorize('delete', $schedule);

        $schedule->delete();

        return response()->json(null, 204);
    }
}
