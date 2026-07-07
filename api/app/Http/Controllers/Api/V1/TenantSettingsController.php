<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTenantSettingsRequest;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;

class TenantSettingsController extends Controller
{
    /** Campos de perfil que vivem no jsonb `settings`. */
    private const PROFILE_FIELDS = ['description', 'phone', 'whatsapp', 'address', 'instagram'];

    public function update(UpdateTenantSettingsRequest $request): TenantResource
    {
        /** @var Tenant $tenant */
        $tenant = app('currentTenant');

        if ($request->has('name')) {
            $tenant->name = $request->string('name')->toString();
        }

        $settings = $tenant->settings ?? [];

        foreach (self::PROFILE_FIELDS as $field) {
            if ($request->has($field)) {
                $settings[$field] = $request->input($field);
            }
        }

        $tenant->settings = $settings;
        $tenant->save();

        return new TenantResource($tenant);
    }
}
