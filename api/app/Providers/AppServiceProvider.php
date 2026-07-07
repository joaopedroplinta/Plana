<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\User;
use App\Policies\AppointmentPolicy;
use App\Policies\PaymentPolicy;
use App\Services\PaymentService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Appointment::class, AppointmentPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);

        Gate::define('viewDashboard', fn (User $user) => $user->isStaffOfTenant(app('currentTenant')));

        $this->configureRateLimiting();
    }

    /**
     * Rate limiting: endpoints de autenticação são o alvo de brute force,
     * então têm limite agressivo por IP; o restante da API limita por
     * usuário autenticado (ou IP quando anônimo).
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user('sanctum')?->id ?? $request->ip()));
    }
}
