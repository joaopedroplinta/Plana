<?php

namespace App\Policies;

use App\Models\PackagePurchase;
use App\Models\Tenant;
use App\Models\User;

class PackagePurchasePolicy
{
    /**
     * Qualquer usuário autenticado pode listar — o controller já restringe
     * a consulta às próprias compras do cliente.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PackagePurchase $packagePurchase): bool
    {
        /** @var Tenant $tenant */
        $tenant = app('currentTenant');

        if ($packagePurchase->tenant_id !== $tenant->id) {
            return false;
        }

        return $user->isStaffOfTenant($tenant) || $packagePurchase->client_id === $user->id;
    }

    /**
     * A compra é sempre feita em nome do próprio usuário autenticado
     * (client_id = auth()->id() no controller) — qualquer cliente pode.
     */
    public function create(User $user): bool
    {
        return true;
    }
}
