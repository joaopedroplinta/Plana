<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SyncBusinessHoursRequest;
use App\Http\Resources\BusinessHourResource;
use App\Models\BusinessHour;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * Horário de funcionamento do salão. `index` é público (usado na landing e no
 * fluxo de agendamento); `sync` substitui a semana inteira e é restrito ao dono
 * do salão. Uma vez configurado, o horário limita a agenda dos profissionais
 * (ver SchedulingService::salonWindow()).
 */
class BusinessHourController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $hours = BusinessHour::query()->orderBy('day_of_week')->get();

        return BusinessHourResource::collection($hours);
    }

    public function sync(SyncBusinessHoursRequest $request): AnonymousResourceCollection
    {
        $tenantId = app('currentTenant')->id;

        $hours = DB::transaction(function () use ($request, $tenantId) {
            BusinessHour::where('tenant_id', $tenantId)->delete();

            foreach ($request->validated('days') as $day) {
                $isOpen = (bool) $day['is_open'];

                BusinessHour::create([
                    'day_of_week' => $day['day_of_week'],
                    'is_open' => $isOpen,
                    'open_time' => $isOpen ? $day['open_time'] : null,
                    'close_time' => $isOpen ? $day['close_time'] : null,
                ]);
            }

            return BusinessHour::query()->orderBy('day_of_week')->get();
        });

        return BusinessHourResource::collection($hours);
    }
}
