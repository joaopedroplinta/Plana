<?php

namespace App\Policies;

use App\Models\Professional;
use App\Models\Tenant;
use App\Models\User;

class ProfessionalPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Professional $professional): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        /** @var Tenant $currentTenant */
        $currentTenant = app('currentTenant');

        return $user->isStaffOfTenant($currentTenant);
    }

    public function update(User $user, Professional $professional): bool
    {
        /** @var Tenant $currentTenant */
        $currentTenant = app('currentTenant');

        return $user->isStaffOfTenant($currentTenant)
            && $professional->tenant_id === $currentTenant->id;
    }

    public function delete(User $user, Professional $professional): bool
    {
        /** @var Tenant $currentTenant */
        $currentTenant = app('currentTenant');

        return $user->ownsTenant($currentTenant)
            && $professional->tenant_id === $currentTenant->id;
    }
}
