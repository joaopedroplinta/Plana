<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PackagePurchaseStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PackagePurchaseResource;
use App\Models\PackagePurchase;
use App\Models\ServicePackage;
use App\Models\Tenant;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PackagePurchaseController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', PackagePurchase::class);

        $query = PackagePurchase::with(['servicePackage', 'payment'])
            ->orderByDesc('created_at');

        if (! $request->user()->isStaffOfTenant(app('currentTenant'))) {
            $query->where('client_id', $request->user()->id);
        }

        return PackagePurchaseResource::collection($query->paginate(20));
    }

    public function store(Request $request, string $tenant, ServicePackage $package): JsonResponse
    {
        Gate::authorize('create', PackagePurchase::class);

        /** @var Tenant $currentTenant */
        $currentTenant = app('currentTenant');

        $data = $request->validate([
            'method' => ['required', 'in:pix,credit_card'],
        ]);

        $purchase = PackagePurchase::create([
            'client_id' => $request->user()->id,
            'service_package_id' => $package->id,
            'sessions_total' => $package->sessions,
            'sessions_used' => 0,
            'price_paid' => $package->price,
            'status' => PackagePurchaseStatus::Pending,
        ]);

        $data['method'] === 'pix'
            ? $this->paymentService->createPixForPackagePurchase($purchase, $request->user())
            : $this->paymentService->createCheckoutProForPackagePurchase($purchase, $request->user(), $currentTenant->slug);

        $purchase->load(['servicePackage', 'payment']);

        return (new PackagePurchaseResource($purchase))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, string $tenant, PackagePurchase $packagePurchase): JsonResponse
    {
        Gate::authorize('view', $packagePurchase);

        $packagePurchase->load(['servicePackage', 'payment']);

        if ($packagePurchase->payment?->method === 'pix' && $packagePurchase->payment->status === 'pending') {
            $this->paymentService->syncStatus($packagePurchase->payment);
            $packagePurchase->refresh()->load(['servicePackage', 'payment']);
        }

        return (new PackagePurchaseResource($packagePurchase))
            ->response()
            ->setStatusCode(200);
    }
}
