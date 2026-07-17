<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTenantSettingsRequest;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TenantSettingsController extends Controller
{
    /**
     * Campos que vivem no jsonb `settings`: perfil do salão, padrão de sinal
     * (deposit_type/deposit_value) e personalização da landing (brand_color;
     * logo_url é gravado pelo upload dedicado abaixo).
     */
    private const SETTINGS_FIELDS = ['description', 'phone', 'whatsapp', 'address', 'instagram', 'deposit_type', 'deposit_value', 'brand_color'];

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

    /**
     * Upload da logo do salão (disk público). Substitui a anterior, se houver.
     */
    public function uploadLogo(Request $request): TenantResource
    {
        /** @var Tenant $tenant */
        $tenant = app('currentTenant');

        abort_unless($request->user()->ownsTenant($tenant), 403);

        $request->validate([
            'logo' => ['required', 'image', 'max:2048'],
        ]);

        $settings = $tenant->settings ?? [];

        // Remove a logo anterior para não acumular lixo no disco.
        if (! empty($settings['logo_url'])) {
            $old = Str::after($settings['logo_url'], '/storage/');
            if ($old !== $settings['logo_url']) {
                Storage::disk('public')->delete($old);
            }
        }

        $path = $request->file('logo')->store('logos', 'public');
        $settings['logo_url'] = Storage::url($path);

        $tenant->settings = $settings;
        $tenant->save();

        return new TenantResource($tenant);
    }
}
