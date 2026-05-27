<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBlockedDateRequest;
use App\Http\Resources\BlockedDateResource;
use App\Models\BlockedDate;
use App\Models\Professional;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class BlockedDateController extends Controller
{
    public function index(Request $request, string $tenant, Professional $professional): AnonymousResourceCollection
    {
        $blockedDates = BlockedDate::where('professional_id', $professional->id)
            ->orderBy('date')
            ->get();

        return BlockedDateResource::collection($blockedDates);
    }

    public function store(StoreBlockedDateRequest $request, string $tenant, Professional $professional): JsonResponse
    {
        Gate::authorize('create', BlockedDate::class);

        $blockedDate = BlockedDate::create(array_merge(
            $request->validated(),
            ['professional_id' => $professional->id]
        ));

        return (new BlockedDateResource($blockedDate))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, string $tenant, Professional $professional, BlockedDate $blockedDate): BlockedDateResource
    {
        return new BlockedDateResource($blockedDate);
    }

    public function destroy(Request $request, string $tenant, Professional $professional, BlockedDate $blockedDate): JsonResponse
    {
        Gate::authorize('delete', $blockedDate);

        $blockedDate->delete();

        return response()->json(null, 204);
    }
}
