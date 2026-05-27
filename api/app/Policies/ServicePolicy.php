<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;

class ServicePolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Service $service): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        /** @var Tenant $currentTenant */
        $currentTenant = app('currentTenant');

        return $user->hasRole(['salon_owner', 'salon_staff'])
            && $user->belongsToTenant($currentTenant);
    }

    public function update(User $user, Service $service): bool
    {
        return $user->hasRole(['salon_owner', 'salon_staff'])
            && $user->belongsToTenant(app('currentTenant'));
    }

    public function delete(User $user, Service $service): bool
    {
        return $user->hasRole('salon_owner')
            && $user->belongsToTenant(app('currentTenant'));
    }

    public function uploadImage(User $user, Service $service): bool
    {
        return $user->hasRole(['salon_owner', 'salon_staff'])
            && $user->belongsToTenant(app('currentTenant'));
    }
}
