<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BlockedDateController;
use App\Http\Controllers\Api\V1\ProfessionalController;
use App\Http\Controllers\Api\V1\ScheduleController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\ServicePackageController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
        });
    });

    // Rotas públicas do tenant
    Route::prefix('salao/{tenant:slug}')->middleware('tenant')->group(function () {
        Route::get('ping', fn () => response()->json(['ok' => true]))->name('tenant.ping');

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
        });

    // Rotas super admin
    Route::prefix('admin')
        ->middleware(['auth:sanctum', 'role:super_admin'])
        ->group(function () {
            // aqui virão as rotas de super admin
        });
});
