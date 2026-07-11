<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProfessionalRequest;
use App\Http\Requests\UpdateProfessionalRequest;
use App\Http\Resources\ProfessionalResource;
use App\Models\Professional;
use App\Models\Tenant;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ProfessionalController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        // Staff do salão vê todos os profissionais; público vê só os ativos.
        $user = $request->user('sanctum');
        $showAll = $user?->isStaffOfTenant(app('currentTenant')) ?? false;

        $professionals = Professional::query()
            ->when(! $showAll, fn ($q) => $q->where('active', true))
            ->paginate(15);

        return ProfessionalResource::collection($professionals);
    }

    public function store(StoreProfessionalRequest $request): JsonResponse
    {
        Gate::authorize('create', Professional::class);

        /** @var Tenant $tenant */
        $tenant = app('currentTenant');

        SubscriptionService::assertCanAddProfessional($tenant);

        $professional = Professional::create($request->validated());

        return (new ProfessionalResource($professional))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, string $tenant, Professional $professional): ProfessionalResource
    {
        $professional->load('schedules');

        return new ProfessionalResource($professional);
    }

    public function update(UpdateProfessionalRequest $request, string $tenant, Professional $professional): ProfessionalResource
    {
        Gate::authorize('update', $professional);

        $professional->update($request->validated());

        return new ProfessionalResource($professional);
    }

    public function destroy(Request $request, string $tenant, Professional $professional): JsonResponse
    {
        Gate::authorize('delete', $professional);

        $professional->delete();

        return response()->json(null, 204);
    }
}
