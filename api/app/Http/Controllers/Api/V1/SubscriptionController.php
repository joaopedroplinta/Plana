<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptionService) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = app('currentTenant');

        $subscriptions = Subscription::where('tenant_id', $tenant->id)
            ->latest()
            ->take(5)
            ->get();

        return response()->json([
            'data' => [
                'current_plan' => $tenant->plan,
                'plans' => $this->subscriptionService->getPlans(),
                'subscriptions' => SubscriptionResource::collection($subscriptions)->resolve(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = app('currentTenant');

        if (! $request->user()->ownsTenant($tenant)) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        $validated = $request->validate([
            'plan' => ['required', 'string', 'in:starter,pro,enterprise'],
            'method' => ['required', 'string', 'in:pix,credit_card'],
        ]);

        // Starter is free — update the plan directly without payment
        if ($validated['plan'] === 'starter') {
            $tenant->update(['plan' => 'starter']);

            return response()->json([
                'data' => [
                    'plan' => 'starter',
                    'status' => 'approved',
                    'amount' => 0,
                ],
            ], 201);
        }

        if ($validated['method'] === 'pix') {
            $subscription = $this->subscriptionService->createPixSubscription(
                $tenant,
                $request->user(),
                $validated['plan']
            );
        } else {
            $subscription = $this->subscriptionService->createCheckoutProSubscription(
                $tenant,
                $request->user(),
                $validated['plan']
            );
        }

        return (new SubscriptionResource($subscription))
            ->response()
            ->setStatusCode(201);
    }
}
