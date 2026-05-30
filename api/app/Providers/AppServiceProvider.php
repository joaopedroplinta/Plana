<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\User;
use App\Policies\AppointmentPolicy;
use App\Policies\PaymentPolicy;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Gate;
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

        Gate::define('viewDashboard', fn (User $user) => $user->hasAnyRole(['salon_owner', 'salon_staff'])
            && $user->belongsToTenant(app('currentTenant'))
        );
    }
}
