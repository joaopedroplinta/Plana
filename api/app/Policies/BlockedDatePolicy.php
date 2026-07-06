<?php

namespace App\Policies;

use App\Models\BlockedDate;
use App\Models\Tenant;
use App\Models\User;

class BlockedDatePolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, BlockedDate $blockedDate): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        /** @var Tenant $currentTenant */
        $currentTenant = app('currentTenant');

        return $user->isStaffOfTenant($currentTenant);
    }

    public function delete(User $user, BlockedDate $blockedDate): bool
    {
        /** @var Tenant $currentTenant */
        $currentTenant = app('currentTenant');

        return $user->isStaffOfTenant($currentTenant)
            && $blockedDate->tenant_id === $currentTenant->id;
    }
}
