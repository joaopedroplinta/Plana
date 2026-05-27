<?php

namespace App\Policies;

use App\Models\Schedule;
use App\Models\User;

class SchedulePolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Schedule $schedule): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['salon_owner', 'salon_staff'])
            && $user->belongsToTenant(app('currentTenant'));
    }

    public function update(User $user, Schedule $schedule): bool
    {
        $currentTenant = app('currentTenant');

        return $user->hasRole(['salon_owner', 'salon_staff'])
            && $user->belongsToTenant($currentTenant)
            && $schedule->tenant_id === $currentTenant->id;
    }

    public function delete(User $user, Schedule $schedule): bool
    {
        $currentTenant = app('currentTenant');

        return $user->hasRole(['salon_owner', 'salon_staff'])
            && $user->belongsToTenant($currentTenant)
            && $schedule->tenant_id === $currentTenant->id;
    }
}
