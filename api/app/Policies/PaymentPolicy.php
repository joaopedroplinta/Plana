<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function create(User $user, Appointment $appointment): bool
    {
        $tenant = app('currentTenant');

        return $user->belongsToTenant($tenant)
            && $appointment->tenant_id === $tenant->id
            && $appointment->client_id === $user->id;
    }

    public function view(User $user, Payment $payment): bool
    {
        $tenant = app('currentTenant');

        if ($payment->tenant_id !== $tenant->id) {
            return false;
        }

        if ($user->hasAnyRole(['salon_owner', 'salon_staff'])) {
            return $user->belongsToTenant($tenant);
        }

        return $user->hasRole('client')
            && $payment->appointment->client_id === $user->id;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['salon_owner', 'salon_staff', 'client']);
    }
}
