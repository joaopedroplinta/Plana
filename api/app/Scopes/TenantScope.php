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
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        /** @var Tenant|null $currentTenant */
        $currentTenant = app()->bound('currentTenant') ? app('currentTenant') : null;

        if ($currentTenant) {
            $builder->where($model->getTable().'.tenant_id', $currentTenant->id);
        }
    }
}
