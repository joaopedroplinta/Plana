<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServicePackageRequest;
use App\Http\Requests\UpdateServicePackageRequest;
use App\Http\Resources\ServicePackageResource;
use App\Models\ServicePackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ServicePackageController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $packages = ServicePackage::with('services')->paginate(15);

        return ServicePackageResource::collection($packages);
    }

    public function store(StoreServicePackageRequest $request): JsonResponse
    {
        Gate::authorize('create', ServicePackage::class);

        $validated = $request->validated();
        $serviceIds = $validated['service_ids'] ?? [];
        unset($validated['service_ids']);

        $package = ServicePackage::create($validated);

        if (! empty($serviceIds)) {
            $package->services()->sync($serviceIds);
        }

        $package->load('services');

        return (new ServicePackageResource($package))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, string $tenant, ServicePackage $package): ServicePackageResource
    {
        $package->load('services');

        return new ServicePackageResource($package);
    }

    public function update(UpdateServicePackageRequest $request, string $tenant, ServicePackage $package): ServicePackageResource
    {
        Gate::authorize('update', $package);

        $validated = $request->validated();
        $serviceIds = $validated['service_ids'] ?? null;
        unset($validated['service_ids']);

        $package->update($validated);

        if ($serviceIds !== null) {
            $package->services()->sync($serviceIds);
        }

        $package->load('services');

        return new ServicePackageResource($package);
    }

    public function destroy(Request $request, string $tenant, ServicePackage $package): JsonResponse
    {
        Gate::authorize('delete', $package);

        $package->delete();

        return response()->json(null, 204);
    }
}
