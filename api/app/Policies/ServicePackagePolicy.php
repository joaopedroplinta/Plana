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

        return $user->isStaffOfTenant($currentTenant);
    }

    public function update(User $user, ServicePackage $servicePackage): bool
    {
        /** @var Tenant $currentTenant */
        $currentTenant = app('currentTenant');

        return $user->isStaffOfTenant($currentTenant)
            && $servicePackage->tenant_id === $currentTenant->id;
    }

    public function delete(User $user, ServicePackage $servicePackage): bool
    {
        /** @var Tenant $currentTenant */
        $currentTenant = app('currentTenant');

        return $user->ownsTenant($currentTenant)
            && $servicePackage->tenant_id === $currentTenant->id;
    }
}
