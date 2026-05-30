<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Appointment;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $paymentService) {}

    public function index(Request $request, string $tenant, Appointment $appointment): AnonymousResourceCollection
    {
        Gate::authorize('view', $appointment);

        return PaymentResource::collection($appointment->payments()->orderByDesc('created_at')->get());
    }

    public function store(Request $request, string $tenant, Appointment $appointment): JsonResponse
    {
        Gate::authorize('create', [Payment::class, $appointment]);

        $data = $request->validate([
            'method' => ['required', 'in:pix,credit_card'],
        ]);

        $currentTenant = app('currentTenant');
        $payment = $data['method'] === 'pix'
            ? $this->paymentService->createPix($appointment, $request->user())
            : $this->paymentService->createCheckoutPro($appointment, $request->user(), $currentTenant->slug);

        return (new PaymentResource($payment))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, string $tenant, Payment $payment): JsonResponse
    {
        Gate::authorize('view', $payment);

        if ($payment->method === 'pix' && $payment->status === 'pending') {
            $payment = $this->paymentService->syncStatus($payment);
        }

        return (new PaymentResource($payment))->response()->setStatusCode(200);
    }

    public function webhook(Request $request): JsonResponse
    {
        $secret = config('services.mercadopago.webhook_secret');
        if ($secret) {
            $xSignature = $request->header('x-signature', '');
            $xRequestId = $request->header('x-request-id', '');
            $dataId = $request->query('data_id', $request->input('data.id', ''));

            preg_match('/ts=(\d+)/', $xSignature, $tsMatch);
            $ts = $tsMatch[1] ?? '';
            $manifest = "id:{$dataId};request-id:{$xRequestId};ts:{$ts}";

            preg_match('/v1=([a-f0-9]+)/', $xSignature, $hashMatch);
            $receivedHash = $hashMatch[1] ?? '';
            $expectedHash = hash_hmac('sha256', $manifest, $secret);

            if (! hash_equals($expectedHash, $receivedHash)) {
                return response()->json(['message' => 'Invalid signature'], 401);
            }
        }

        $this->paymentService->handleWebhook($request->all());

        return response()->json(['ok' => true]);
    }
}
