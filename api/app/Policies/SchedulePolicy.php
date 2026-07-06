<?php

namespace App\Policies;

use App\Models\Schedule;
use App\Models\Tenant;
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
        /** @var Tenant $currentTenant */
        $currentTenant = app('currentTenant');

        return $user->isStaffOfTenant($currentTenant);
    }

    public function update(User $user, Schedule $schedule): bool
    {
        /** @var Tenant $currentTenant */
        $currentTenant = app('currentTenant');

        return $user->isStaffOfTenant($currentTenant)
            && $schedule->tenant_id === $currentTenant->id;
    }

    public function delete(User $user, Schedule $schedule): bool
    {
        /** @var Tenant $currentTenant */
        $currentTenant = app('currentTenant');

        return $user->isStaffOfTenant($currentTenant)
            && $schedule->tenant_id === $currentTenant->id;
    }
}
