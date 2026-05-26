<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
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
        ->middleware(['auth:sanctum'])
        ->group(function () {
            // aqui virão as rotas de super admin
        });
});
