<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAdminTenantRequest;
use App\Http\Resources\AdminTenantResource;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminTenantController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $tenants = Tenant::with('users')->paginate(20);

        return AdminTenantResource::collection($tenants);
    }

    public function show(Tenant $tenant): AdminTenantResource
    {
        $tenant->load('users');

        return new AdminTenantResource($tenant);
    }

    public function update(UpdateAdminTenantRequest $request, Tenant $tenant): JsonResponse
    {
        $tenant->update($request->validated());
        $tenant->load('users');

        return (new AdminTenantResource($tenant))
            ->response()
            ->setStatusCode(200);
    }
}
