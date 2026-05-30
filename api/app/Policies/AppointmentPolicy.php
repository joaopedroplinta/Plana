<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\Tenant;
use App\Models\User;

class AppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['salon_owner', 'salon_staff', 'client']);
    }

    public function view(User $user, Appointment $appointment): bool
    {
        /** @var Tenant $tenant */
        $tenant = app('currentTenant');

        if ($appointment->tenant_id !== $tenant->id) {
            return false;
        }

        if ($user->hasAnyRole(['salon_owner', 'salon_staff'])) {
            return $user->belongsToTenant($tenant);
        }

        return $user->hasRole('client') && $appointment->client_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->belongsToTenant(app('currentTenant'));
    }

    public function confirm(User $user, Appointment $appointment): bool
    {
        /** @var Tenant $tenant */
        $tenant = app('currentTenant');

        return $user->hasAnyRole(['salon_owner', 'salon_staff'])
            && $user->belongsToTenant($tenant)
            && $appointment->tenant_id === $tenant->id;
    }

    public function cancel(User $user, Appointment $appointment): bool
    {
        /** @var Tenant $tenant */
        $tenant = app('currentTenant');

        if ($appointment->tenant_id !== $tenant->id) {
            return false;
        }

        if ($user->hasAnyRole(['salon_owner', 'salon_staff'])) {
            return $user->belongsToTenant($tenant);
        }

        return $user->hasRole('client') && $appointment->client_id === $user->id;
    }

    public function complete(User $user, Appointment $appointment): bool
    {
        return $this->confirm($user, $appointment);
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        /** @var Tenant $tenant */
        $tenant = app('currentTenant');

        return $user->hasRole('salon_owner')
            && $user->belongsToTenant($tenant)
            && $appointment->tenant_id === $tenant->id;
    }
}
