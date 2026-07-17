<?php

namespace App\Policies;

use App\Models\SalonGalleryImage;
use App\Models\Tenant;
use App\Models\User;

class SalonGalleryImagePolicy
{
    /**
     * Only the salon owner manages the gallery.
     */
    public function create(User $user): bool
    {
        /** @var Tenant $currentTenant */
        $currentTenant = app('currentTenant');

        return $user->ownsTenant($currentTenant);
    }

    public function delete(User $user, SalonGalleryImage $salonGalleryImage): bool
    {
        /** @var Tenant $currentTenant */
        $currentTenant = app('currentTenant');

        return $user->ownsTenant($currentTenant)
            && $salonGalleryImage->tenant_id === $currentTenant->id;
    }
}
