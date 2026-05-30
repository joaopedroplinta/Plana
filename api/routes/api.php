<?php

use App\Http\Controllers\Api\V1\AppointmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AvailabilityController;
use App\Http\Controllers\Api\V1\BlockedDateController;
use App\Http\Controllers\Api\V1\ProfessionalController;
use App\Http\Controllers\Api\V1\ScheduleController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\ServicePackageController;
use App\Http\Resources\TenantResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
        });
    });

    // Rotas públicas do tenant
    Route::prefix('salao/{tenant:slug}')->middleware('tenant')->group(function () {
        Route::get('/', fn (Request $request) => new TenantResource($request->tenant));
        Route::get('ping', fn () => response()->json(['ok' => true]))->name('tenant.ping');

        Route::get('availability', AvailabilityController::class);

        Route::apiResource('services', ServiceController::class)->only(['index', 'show']);
        Route::apiResource('packages', ServicePackageController::class)->only(['index', 'show']);
        Route::apiResource('professionals', ProfessionalController::class)->only(['index', 'show']);
        Route::apiResource('professionals/{professional}/schedules', ScheduleController::class)->only(['index', 'show']);
        Route::apiResource('professionals/{professional}/blocked-dates', BlockedDateController::class)->only(['index', 'show']);
    });

    // Rotas autenticadas do tenant
    Route::prefix('salao/{tenant:slug}')
        ->middleware(['tenant', 'auth:sanctum'])
        ->group(function () {
            Route::apiResource('services', ServiceController::class)->except(['index', 'show']);
            Route::post('services/{service}/image', [ServiceController::class, 'uploadImage']);

            Route::apiResource('packages', ServicePackageController::class)->except(['index', 'show']);

            Route::apiResource('professionals', ProfessionalController::class)->except(['index', 'show']);
            Route::apiResource('professionals/{professional}/schedules', ScheduleController::class)->except(['index', 'show']);
            Route::apiResource('professionals/{professional}/blocked-dates', BlockedDateController::class)
                ->except(['index', 'show', 'update']);

            Route::get('appointments', [AppointmentController::class, 'index']);
            Route::post('appointments', [AppointmentController::class, 'store']);
            Route::get('appointments/{appointment}', [AppointmentController::class, 'show']);
            Route::patch('appointments/{appointment}/confirm', [AppointmentController::class, 'confirm']);
            Route::patch('appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);
            Route::patch('appointments/{appointment}/complete', [AppointmentController::class, 'complete']);
        });

    // Rotas super admin
    Route::prefix('admin')
        ->middleware(['auth:sanctum', 'role:super_admin'])
        ->group(function () {
            // aqui virão as rotas de super admin
        });
});
