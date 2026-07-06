<?php

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SubscriptionExpired;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function downgradeOwner(Tenant $tenant): User
{
    Role::firstOrCreate(['name' => 'salon_owner', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('salon_owner');
    $tenant->users()->attach($user->id, ['role' => 'owner']);

    return $user;
}

it('rebaixa tenant com assinatura expirada e notifica o owner', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create(['plan' => 'pro']);
    $owner = downgradeOwner($tenant);

    Subscription::create([
        'tenant_id' => $tenant->id,
        'plan' => 'pro',
        'amount' => 9700,
        'method' => 'pix',
        'status' => 'approved',
        'paid_at' => now()->subMonths(2),
        'expires_at' => now()->subMonth(),
    ]);

    $this->artisan('subscriptions:downgrade-expired')->assertSuccessful();

    expect($tenant->fresh()->plan)->toBe('starter');
    Notification::assertSentTo($owner, SubscriptionExpired::class);
});

it('nao rebaixa tenant com assinatura ativa', function () {
    Notification::fake();

    $tenant = Tenant::factory()->create(['plan' => 'pro']);
    downgradeOwner($tenant);

    Subscription::create([
        'tenant_id' => $tenant->id,
        'plan' => 'pro',
        'amount' => 9700,
        'method' => 'pix',
        'status' => 'approved',
        'paid_at' => now(),
        'expires_at' => now()->addMonth(),
    ]);

    $this->artisan('subscriptions:downgrade-expired')->assertSuccessful();

    expect($tenant->fresh()->plan)->toBe('pro');
    Notification::assertNothingSent();
});

it('nao rebaixa tenant com plano concedido manualmente (sem assinatura)', function () {
    $tenant = Tenant::factory()->create(['plan' => 'enterprise']);
    downgradeOwner($tenant);

    $this->artisan('subscriptions:downgrade-expired')->assertSuccessful();

    expect($tenant->fresh()->plan)->toBe('enterprise');
});

it('renovacao conta: assinatura antiga expirada mas nova ativa mantem o plano', function () {
    $tenant = Tenant::factory()->create(['plan' => 'pro']);
    downgradeOwner($tenant);

    Subscription::create([
        'tenant_id' => $tenant->id,
        'plan' => 'pro',
        'amount' => 9700,
        'method' => 'pix',
        'status' => 'approved',
        'expires_at' => now()->subDays(3),
    ]);
    Subscription::create([
        'tenant_id' => $tenant->id,
        'plan' => 'pro',
        'amount' => 9700,
        'method' => 'pix',
        'status' => 'approved',
        'expires_at' => now()->addDays(27),
    ]);

    $this->artisan('subscriptions:downgrade-expired')->assertSuccessful();

    expect($tenant->fresh()->plan)->toBe('pro');
});
