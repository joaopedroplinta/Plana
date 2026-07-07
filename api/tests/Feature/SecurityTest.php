<?php

use App\Jobs\ProcessPaymentWebhook;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

// --- Rate limiting ---

it('bloqueia brute force no login com 429 apos 5 tentativas', function () {
    User::factory()->create(['email' => 'vitima@test.com']);

    foreach (range(1, 5) as $i) {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'vitima@test.com',
            'password' => 'senha-errada',
        ])->assertStatus(401);
    }

    $this->postJson('/api/v1/auth/login', [
        'email' => 'vitima@test.com',
        'password' => 'senha-errada',
    ])->assertStatus(429);
});

it('aplica throttle tambem no register', function () {
    foreach (range(1, 5) as $i) {
        $this->postJson('/api/v1/auth/register', [
            'name' => "User {$i}",
            'email' => "user{$i}@test.com",
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
    }

    $this->postJson('/api/v1/auth/register', [
        'name' => 'User 6',
        'email' => 'user6@test.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertStatus(429);
});

// --- Webhook secret obrigatório em produção ---

it('rejeita webhook sem secret configurado em producao com 503', function () {
    config(['services.mercadopago.webhook_secret' => null]);
    $this->app->detectEnvironment(fn () => 'production');

    $this->postJson('/api/v1/payments/webhook', [
        'type' => 'payment',
        'data' => ['id' => '123'],
    ])->assertStatus(503);
});

it('rejeita webhook com assinatura invalida quando secret esta configurado', function () {
    config(['services.mercadopago.webhook_secret' => 'segredo-teste']);

    $this->postJson('/api/v1/payments/webhook?data_id=123', [
        'type' => 'payment',
        'data' => ['id' => '123'],
    ], [
        'x-signature' => 'ts=1700000000,v1=deadbeef',
        'x-request-id' => 'req-1',
    ])->assertStatus(401);
});

it('aceita webhook com assinatura valida', function () {
    Queue::fake();
    config(['services.mercadopago.webhook_secret' => 'segredo-teste']);

    $ts = '1700000000';
    $dataId = '123';
    $requestId = 'req-1';
    $manifest = "id:{$dataId};request-id:{$requestId};ts:{$ts}";
    $hash = hash_hmac('sha256', $manifest, 'segredo-teste');

    $this->postJson("/api/v1/payments/webhook?data_id={$dataId}", [
        'type' => 'payment',
        'data' => ['id' => $dataId],
    ], [
        'x-signature' => "ts={$ts},v1={$hash}",
        'x-request-id' => $requestId,
    ])->assertOk();

    Queue::assertPushed(ProcessPaymentWebhook::class);
});
