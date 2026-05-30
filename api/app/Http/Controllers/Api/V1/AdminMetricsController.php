<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminMetricsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                'total_tenants' => Tenant::count(),
                'active_tenants' => Tenant::where('active', true)->count(),
                'tenants_by_plan' => Tenant::selectRaw('plan, count(*) as count')
                    ->groupBy('plan')
                    ->get(),
                'total_users' => User::count(),
                'total_appointments' => Appointment::withoutGlobalScopes()->count(),
                'total_revenue' => Payment::withoutGlobalScopes()
                    ->where('status', 'approved')
                    ->sum('amount'),
            ],
        ]);
    }
}
