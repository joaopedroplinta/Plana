<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class SchedulerController extends Controller
{
    /**
     * Dispara `schedule:run` via HTTP — usado em deploys sem worker/cron
     * persistente (ex: Render free tier), acionado por um cron externo
     * (cron-job.org) a cada poucos minutos.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $token = config('app.scheduler_token');

        if (! $token && app()->isProduction()) {
            Log::error('SCHEDULER_TOKEN não configurado — chamada ao scheduler rejeitada.');

            return response()->json(['message' => 'Scheduler não configurado.'], 503);
        }

        if ($token && ! hash_equals($token, (string) $request->header('X-Scheduler-Token', ''))) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        Artisan::call('schedule:run');

        return response()->json(['output' => Artisan::output()]);
    }
}
