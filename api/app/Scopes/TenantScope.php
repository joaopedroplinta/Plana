<?php

namespace App\Scopes;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * During route model binding the ResolveTenant middleware has not yet
     * run, so app('currentTenant') is unbound. We fall back to resolving
     * the Tenant from the {tenant:slug} route parameter directly so that
     * cross-tenant model lookups correctly return 404.
     *
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        /** @var Tenant|null $currentTenant */
        $currentTenant = app()->bound('currentTenant') ? app('currentTenant') : null;

        if (! $currentTenant) {
            $routeTenant = request()->route('tenant');

            if ($routeTenant instanceof Tenant) {
                $currentTenant = $routeTenant;
            } elseif (is_string($routeTenant) && $routeTenant !== '') {
                $currentTenant = Tenant::withoutGlobalScope(self::class)
                    ->where('slug', $routeTenant)
                    ->first();
            }
        }

        if ($currentTenant) {
            $builder->where($model->getTable().'.tenant_id', $currentTenant->id);
        }
    }
}
