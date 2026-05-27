<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class ServiceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $services = Service::where('active', true)->paginate(15);

        return ServiceResource::collection($services);
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        Gate::authorize('create', Service::class);

        $service = Service::create($request->validated());

        return (new ServiceResource($service))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, string $tenant, Service $service): ServiceResource
    {
        return new ServiceResource($service);
    }

    public function update(UpdateServiceRequest $request, string $tenant, Service $service): ServiceResource
    {
        Gate::authorize('update', $service);

        $service->update($request->validated());

        return new ServiceResource($service);
    }

    public function destroy(Request $request, string $tenant, Service $service): JsonResponse
    {
        Gate::authorize('delete', $service);

        $service->delete();

        return response()->json(null, 204);
    }

    public function uploadImage(Request $request, string $tenant, Service $service): ServiceResource
    {
        Gate::authorize('uploadImage', $service);

        $request->validate([
            'image' => ['required', 'image', 'max:2048'],
        ]);

        $path = $request->file('image')->store('services', 'public');

        $service->update(['image_url' => Storage::url($path)]);

        return new ServiceResource($service);
    }
}
