<?php

use App\Http\Controllers\Api\V1\AdminMetricsController;
use App\Http\Controllers\Api\V1\AdminTenantController;
use App\Http\Controllers\Api\V1\AppointmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AvailabilityController;
use App\Http\Controllers\Api\V1\BlockedDateController;
use App\Http\Controllers\Api\V1\BusinessHourController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\GalleryImageController;
use App\Http\Controllers\Api\V1\MercadoPagoController;
use App\Http\Controllers\Api\V1\PackagePurchaseController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ProfessionalController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ScheduleController;
use App\Http\Controllers\Api\V1\SchedulerController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\ServicePackageController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\TenantSettingsController;
use App\Http\Resources\TenantResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Webhook — public, outside tenant prefix, no auth required
Route::post('v1/payments/webhook', [PaymentController::class, 'webhook']);

// MercadoPago OAuth callback — public (é o MercadoPago que chama), fora do
// prefixo de tenant e sem auth. Resolve o tenant pelo `state` anti-CSRF.
Route::get('v1/mercadopago/callback', [MercadoPagoController::class, 'callback']);

// Disparo do scheduler via HTTP — protegido por SCHEDULER_TOKEN, usado em
// deploys sem worker/cron persistente (ver config/app.php).
Route::post('v1/system/scheduler', SchedulerController::class);

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::middleware('throttle:auth')->group(function () {
            Route::post('register', [AuthController::class, 'register']);
            Route::post('login', [AuthController::class, 'login']);
            Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
            Route::post('reset-password', [AuthController::class, 'resetPassword']);
        });
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);

            Route::get('profile', [ProfileController::class, 'show']);
            Route::patch('profile', [ProfileController::class, 'update']);
            Route::put('profile/password', [ProfileController::class, 'updatePassword']);
            Route::post('profile/avatar', [ProfileController::class, 'uploadAvatar']);
        });
    });

    // Rotas públicas do tenant
    Route::prefix('negocio/{tenant:slug}')->middleware('tenant')->group(function () {
        Route::get('/', fn (Request $request) => new TenantResource($request->tenant));
        Route::get('ping', fn () => response()->json(['ok' => true]))->name('tenant.ping');

        Route::get('availability', AvailabilityController::class);
        Route::get('business-hours', [BusinessHourController::class, 'index']);
        Route::get('gallery', [GalleryImageController::class, 'index']);

        Route::apiResource('services', ServiceController::class)->only(['index', 'show']);
        Route::apiResource('packages', ServicePackageController::class)->only(['index', 'show']);
        Route::apiResource('professionals', ProfessionalController::class)->only(['index', 'show']);
        Route::apiResource('professionals/{professional}/schedules', ScheduleController::class)->only(['index', 'show'])->scoped();
        Route::apiResource('professionals/{professional}/blocked-dates', BlockedDateController::class)->only(['index', 'show'])->scoped();
    });

    // Rotas autenticadas do tenant
    Route::prefix('negocio/{tenant:slug}')
        ->middleware(['tenant', 'auth:sanctum'])
        ->group(function () {
            Route::apiResource('services', ServiceController::class)->except(['index', 'show']);
            Route::post('services/{service}/image', [ServiceController::class, 'uploadImage']);

            Route::apiResource('packages', ServicePackageController::class)->except(['index', 'show']);

            Route::apiResource('professionals', ProfessionalController::class)->except(['index', 'show']);
            Route::put('professionals/{professional}/schedules', [ScheduleController::class, 'sync']);
            Route::apiResource('professionals/{professional}/schedules', ScheduleController::class)->except(['index', 'show'])->scoped();

            Route::put('business-hours', [BusinessHourController::class, 'sync']);
            Route::apiResource('professionals/{professional}/blocked-dates', BlockedDateController::class)
                ->except(['index', 'show', 'update'])->scoped();

            Route::get('appointments', [AppointmentController::class, 'index']);
            Route::post('appointments', [AppointmentController::class, 'store']);
            Route::get('appointments/{appointment}', [AppointmentController::class, 'show']);
            Route::patch('appointments/{appointment}/confirm', [AppointmentController::class, 'confirm']);
            Route::patch('appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);
            Route::patch('appointments/{appointment}/complete', [AppointmentController::class, 'complete']);
            Route::patch('appointments/{appointment}/no-show', [AppointmentController::class, 'noShow']);
            Route::patch('appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule']);

            Route::get('dashboard', DashboardController::class);

            Route::patch('settings', [TenantSettingsController::class, 'update']);
            Route::post('logo', [TenantSettingsController::class, 'uploadLogo']);

            Route::post('gallery', [GalleryImageController::class, 'store']);
            Route::delete('gallery/{galleryImage}', [GalleryImageController::class, 'destroy']);

            Route::get('team', [TeamController::class, 'index']);
            Route::post('team', [TeamController::class, 'store']);
            Route::delete('team/{user}', [TeamController::class, 'destroy']);

            Route::get('subscription', [SubscriptionController::class, 'index']);
            Route::post('subscription', [SubscriptionController::class, 'store']);

            // Marketplace MercadoPago (Fase 1) — conexão da conta do salão
            Route::get('mercadopago/connect', [MercadoPagoController::class, 'connect']);
            Route::get('mercadopago/status', [MercadoPagoController::class, 'status']);
            Route::delete('mercadopago/disconnect', [MercadoPagoController::class, 'disconnect']);

            Route::get('appointments/{appointment}/payments', [PaymentController::class, 'index']);
            Route::post('appointments/{appointment}/payments', [PaymentController::class, 'store']);
            Route::get('payments/{payment}', [PaymentController::class, 'show']);

            Route::post('packages/{package}/purchase', [PackagePurchaseController::class, 'store']);
            Route::get('package-purchases', [PackagePurchaseController::class, 'index']);
            Route::get('package-purchases/{packagePurchase}', [PackagePurchaseController::class, 'show']);
        });

    // Rotas super admin
    Route::prefix('admin')
        ->middleware(['auth:sanctum', 'role:super_admin'])
        ->group(function () {
            Route::get('metrics', AdminMetricsController::class);
            Route::get('tenants', [AdminTenantController::class, 'index']);
            Route::get('tenants/{tenant}', [AdminTenantController::class, 'show']);
            Route::patch('tenants/{tenant}', [AdminTenantController::class, 'update']);
        });
});
