<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('viewDashboard');

        $period = min((int) $request->get('period', 30), 90);
        $startDate = now()->subDays($period)->startOfDay();

        $summary = [
            'total_appointments' => Appointment::count(),
            'completed_appointments' => Appointment::where('status', 'completed')->count(),
            'appointments_today' => Appointment::whereDate('starts_at', today())
                ->whereNotIn('status', ['cancelled'])->count(),
            'total_clients' => Appointment::distinct('client_id')->count('client_id'),
            'total_revenue' => Payment::where('status', 'approved')->sum('amount'),
            'revenue_this_month' => Payment::where('status', 'approved')
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->sum('amount'),
        ];

        $byStatus = Appointment::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->get();

        $revenueByDay = Payment::where('status', 'approved')
            ->where('paid_at', '>=', $startDate)
            ->selectRaw("DATE(paid_at AT TIME ZONE 'UTC') as date, SUM(amount) as revenue, COUNT(*) as count")
            ->groupByRaw("DATE(paid_at AT TIME ZONE 'UTC')")
            ->orderBy('date')
            ->get();

        $topServices = Appointment::join('services', 'appointments.service_id', '=', 'services.id')
            ->where('appointments.starts_at', '>=', $startDate)
            ->whereNotIn('appointments.status', ['cancelled'])
            ->selectRaw('services.name, COUNT(appointments.id) as count, SUM(appointments.price) as revenue')
            ->groupBy('services.id', 'services.name')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        $byProfessional = Appointment::join('professionals', 'appointments.professional_id', '=', 'professionals.id')
            ->where('appointments.starts_at', '>=', $startDate)
            ->whereNotIn('appointments.status', ['cancelled'])
            ->selectRaw('professionals.name, COUNT(appointments.id) as count, SUM(appointments.price) as revenue')
            ->groupBy('professionals.id', 'professionals.name')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        return response()->json([
            'data' => [
                'summary' => $summary,
                'appointments_by_status' => $byStatus,
                'revenue_by_day' => $revenueByDay,
                'top_services' => $topServices,
                'appointments_by_professional' => $byProfessional,
            ],
            'period' => $period,
        ]);
    }
}
