<?php

use App\Http\Controllers\Api\V1\AuthController;
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
        // aqui virão as rotas públicas do salão
        Route::get('ping', fn () => response()->json(['ok' => true]))->name('tenant.ping');
    });

    // Rotas autenticadas do tenant
    Route::prefix('salao/{tenant:slug}')
        ->middleware(['tenant', 'auth:sanctum'])
        ->group(function () {
            // aqui virão as rotas autenticadas
        });

    // Rotas super admin
    Route::prefix('admin')
        ->middleware(['auth:sanctum', 'role:super_admin'])
        ->group(function () {
            // aqui virão as rotas de super admin
        });
});
