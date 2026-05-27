<?php

namespace App\Policies;

use App\Models\BlockedDate;
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
        return $user->hasRole(['salon_owner', 'salon_staff'])
            && $user->belongsToTenant(app('currentTenant'));
    }

    public function delete(User $user, BlockedDate $blockedDate): bool
    {
        return $user->hasRole(['salon_owner', 'salon_staff'])
            && $user->belongsToTenant(app('currentTenant'));
    }
}
