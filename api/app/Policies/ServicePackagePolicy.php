<?php

namespace App\Policies;

use App\Models\ServicePackage;
use App\Models\Tenant;
use App\Models\User;

class ServicePackagePolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, ServicePackage $servicePackage): bool
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

    public function update(User $user, ServicePackage $servicePackage): bool
    {
        return $user->hasRole(['salon_owner', 'salon_staff'])
            && $user->belongsToTenant(app('currentTenant'));
    }

    public function delete(User $user, ServicePackage $servicePackage): bool
    {
        return $user->hasRole('salon_owner')
            && $user->belongsToTenant(app('currentTenant'));
    }
}
