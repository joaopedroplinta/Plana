<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('tenant com slug válido é resolvido pelo middleware', function () {
    $tenant = Tenant::factory()->create(['slug' => 'salao-teste', 'active' => true]);

    $response = $this->getJson('/api/v1/salao/salao-teste/ping');

    $response->assertOk()
        ->assertJson(['ok' => true]);
});

test('tenant inexistente retorna 404', function () {
    $response = $this->getJson('/api/v1/salao/nao-existe/ping');

    $response->assertNotFound();
});

test('tenant inativo retorna 404', function () {
    Tenant::factory()->inactive()->create(['slug' => 'salao-inativo']);

    $response = $this->getJson('/api/v1/salao/salao-inativo/ping');

    $response->assertNotFound();
});

test('pivot tenant_user registra corretamente o owner', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();

    $tenant->users()->attach($user->id, ['role' => 'owner']);

    $pivotRow = \Illuminate\Support\Facades\DB::table('tenant_user')
        ->where('tenant_id', $tenant->id)
        ->where('user_id', $user->id)
        ->first();

    expect($pivotRow)->not->toBeNull()
        ->and($pivotRow->role)->toBe('owner');

    expect($tenant->owner()->count())->toBe(1);
    expect($user->belongsToTenant($tenant))->toBeTrue();
});
