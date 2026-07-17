<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTenantSettingsRequest;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;

class TenantSettingsController extends Controller
{
    /**
     * Campos que vivem no jsonb `settings`: perfil do salão + padrão de sinal
     * (deposit_type/deposit_value) aplicado aos serviços sem override próprio.
     */
    private const SETTINGS_FIELDS = ['description', 'phone', 'whatsapp', 'address', 'instagram', 'deposit_type', 'deposit_value'];

    public function update(UpdateTenantSettingsRequest $request): TenantResource
    {
        /** @var Tenant $tenant */
        $tenant = app('currentTenant');

        if ($request->has('name')) {
            $tenant->name = $request->string('name')->toString();
        }

        $settings = $tenant->settings ?? [];

        foreach (self::SETTINGS_FIELDS as $field) {
            if ($request->has($field)) {
                $settings[$field] = $request->input($field);
            }
        }

        // Sinal 'none' não tem valor associado — evita lixo herdado no jsonb.
        if (($settings['deposit_type'] ?? null) === 'none') {
            $settings['deposit_value'] = null;
        }

        $tenant->settings = $settings;
        $tenant->save();

        return new TenantResource($tenant);
    }
}
