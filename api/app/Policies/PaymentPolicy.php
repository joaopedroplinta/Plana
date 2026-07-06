<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;

class PaymentPolicy
{
    public function create(User $user, Appointment $appointment): bool
    {
        /** @var Tenant $tenant */
        $tenant = app('currentTenant');

        return $appointment->tenant_id === $tenant->id
            && $appointment->client_id === $user->id;
    }

    public function view(User $user, Payment $payment): bool
    {
        /** @var Tenant $tenant */
        $tenant = app('currentTenant');

        if ($payment->tenant_id !== $tenant->id) {
            return false;
        }

        return $user->isStaffOfTenant($tenant)
            || $payment->appointment->client_id === $user->id;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }
}
