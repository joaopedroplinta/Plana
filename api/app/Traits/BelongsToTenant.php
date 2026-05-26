<?php

namespace App\Traits;

use App\Models\Tenant;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToTenant
{
    /**
     * Boot the trait: add global scope and auto-fill tenant_id on creating.
     */
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model) {
            /** @var Tenant|null $currentTenant */
            $currentTenant = app()->bound('currentTenant') ? app('currentTenant') : null;

            if ($currentTenant && empty($model->tenant_id)) {
                $model->tenant_id = $currentTenant->id;
            }
        });
    }

    /**
     * Return a query builder without the tenant global scope.
     *
     * @return Builder<static>
     */
    public static function withoutTenantScope(): Builder
    {
        return static::withoutGlobalScope(TenantScope::class);
    }
}
