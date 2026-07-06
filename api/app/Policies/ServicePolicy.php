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

        return $user->isStaffOfTenant($currentTenant);
    }

    public function update(User $user, Service $service): bool
    {
        /** @var Tenant $currentTenant */
        $currentTenant = app('currentTenant');

        return $user->isStaffOfTenant($currentTenant)
            && $service->tenant_id === $currentTenant->id;
    }

    public function delete(User $user, Service $service): bool
    {
        /** @var Tenant $currentTenant */
        $currentTenant = app('currentTenant');

        return $user->ownsTenant($currentTenant)
            && $service->tenant_id === $currentTenant->id;
    }

    public function uploadImage(User $user, Service $service): bool
    {
        return $this->update($user, $service);
    }
}
