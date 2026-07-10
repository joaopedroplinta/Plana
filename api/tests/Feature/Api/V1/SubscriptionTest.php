<?php

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// --- Helpers ---

function subOwner(Tenant $tenant): User
{
    Role::firstOrCreate(['name' => 'salon_owner', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('salon_owner');
    $tenant->users()->attach($user->id, ['role' => 'owner']);

    return $user;
}

function subStaff(Tenant $tenant): User
{
    Role::firstOrCreate(['name' => 'salon_staff', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('salon_staff');
    $tenant->users()->attach($user->id, ['role' => 'staff']);

    return $user;
}

// --- GET /subscription ---

it('returns current plan and list of plans for tenant', function () {
    $tenant = Tenant::factory()->create(['plan' => 'pro']);
    $owner = subOwner($tenant);

    $this->mock(SubscriptionService::class, fn ($mock) => $mock
        ->shouldReceive('getPlans')
        ->once()
        ->andReturn([
            ['key' => 'starter', 'name' => 'Starter', 'price' => 0, 'professionals' => '1', 'appointments' => '50/mês', 'features' => []],
            ['key' => 'pro', 'name' => 'Pro', 'price' => 9700, 'professionals' => '5', 'appointments' => 'ilimitados', 'features' => []],
            ['key' => 'enterprise', 'name' => 'Enterprise', 'price' => 19700, 'professionals' => 'ilimitados', 'appointments' => 'ilimitados', 'features' => []],
        ])
    );

    $response = $this->actingAs($owner)
        ->getJson("/api/v1/salao/{$tenant->slug}/subscription");

    $response->assertOk()
        ->assertJsonPath('data.current_plan', 'pro')
        ->assertJsonCount(3, 'data.plans')
        ->assertJsonStructure(['data' => ['current_plan', 'plans', 'subscriptions']]);
});

it('includes recent subscriptions in index response', function () {
    $tenant = Tenant::factory()->create(['plan' => 'starter']);
    $owner = subOwner($tenant);

    Subscription::create([
        'tenant_id' => $tenant->id,
        'plan' => 'pro',
        'amount' => 9700,
        'method' => 'pix',
        'status' => 'approved',
        'mp_payment_id' => 'mp_123',
    ]);

    $this->mock(SubscriptionService::class, fn ($mock) => $mock
        ->shouldReceive('getPlans')
        ->once()
        ->andReturn([])
    );

    $response = $this->actingAs($owner)
        ->getJson("/api/v1/salao/{$tenant->slug}/subscription");

    $response->assertOk()
        ->assertJsonCount(1, 'data.subscriptions');
});

it('returns 401 when unauthenticated on GET', function () {
    $tenant = Tenant::factory()->create();

    $this->getJson("/api/v1/salao/{$tenant->slug}/subscription")
        ->assertUnauthorized();
});

// --- POST /subscription — starter (free) ---

it('switches to starter plan for free without calling MercadoPago', function () {
    $tenant = Tenant::factory()->create(['plan' => 'pro']);
    $owner = subOwner($tenant);

    $response = $this->actingAs($owner)
        ->postJson("/api/v1/salao/{$tenant->slug}/subscription", [
            'plan' => 'starter',
            'method' => 'pix',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.plan', 'starter')
        ->assertJsonPath('data.status', 'approved');

    $tenant->refresh();
    expect($tenant->plan)->toBe('starter');
});

// --- POST /subscription — pro via pix ---

it('creates a pending pix subscription for pro plan', function () {
    $tenant = Tenant::factory()->create(['plan' => 'starter']);
    $owner = subOwner($tenant);

    $fakeSubscription = new Subscription([
        'tenant_id' => $tenant->id,
        'plan' => 'pro',
        'amount' => 9700,
        'method' => 'pix',
        'status' => 'pending',
        'mp_payment_id' => 'mp_999',
        'pix_qr_code' => 'qr_code_text',
        'pix_qr_code_base64' => 'qr_base64',
    ]);
    $fakeSubscription->id = (string) Str::uuid();
    $fakeSubscription->created_at = now();
    $fakeSubscription->updated_at = now();

    $this->mock(SubscriptionService::class, fn ($mock) => $mock
        ->shouldReceive('createPixSubscription')
        ->once()
        ->andReturn($fakeSubscription)
    );

    $response = $this->actingAs($owner)
        ->postJson("/api/v1/salao/{$tenant->slug}/subscription", [
            'plan' => 'pro',
            'method' => 'pix',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.plan', 'pro')
        ->assertJsonPath('data.method', 'pix')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonStructure(['data' => ['id', 'plan', 'amount', 'method', 'status', 'pix_qr_code', 'pix_qr_code_base64']]);
});

// --- POST /subscription — role check ---

it('returns 403 for salon_staff trying to subscribe', function () {
    $tenant = Tenant::factory()->create();
    $staff = subStaff($tenant);

    $this->actingAs($staff)
        ->postJson("/api/v1/salao/{$tenant->slug}/subscription", [
            'plan' => 'pro',
            'method' => 'pix',
        ])
        ->assertForbidden();
});

it('returns 401 when unauthenticated on POST', function () {
    $tenant = Tenant::factory()->create();

    $this->postJson("/api/v1/salao/{$tenant->slug}/subscription", [
        'plan' => 'pro',
        'method' => 'pix',
    ])->assertUnauthorized();
});

// --- Cross-tenant isolation ---

it('salon_owner from another tenant cannot subscribe for a different tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $ownerA = subOwner($tenantA);

    // ownerA acts on tenantB — they have no relation to tenantB
    $this->actingAs($ownerA)
        ->postJson("/api/v1/salao/{$tenantB->slug}/subscription", [
            'plan' => 'pro',
            'method' => 'pix',
        ])
        ->assertForbidden();
});

it('owner of one tenant who is staff at another cannot change that tenant subscription', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $user = subOwner($tenantA);
    $tenantB->users()->attach($user->id, ['role' => 'staff']);

    $this->actingAs($user)
        ->postJson("/api/v1/salao/{$tenantB->slug}/subscription", [
            'plan' => 'pro',
            'method' => 'pix',
        ])
        ->assertForbidden();

    $tenantB->refresh();
    expect($tenantB->plan)->not->toBe('pro');
});

it('owner can change the subscription of their own tenant', function () {
    $tenant = Tenant::factory()->create(['plan' => 'pro']);
    $owner = subOwner($tenant);

    $response = $this->actingAs($owner)
        ->postJson("/api/v1/salao/{$tenant->slug}/subscription", [
            'plan' => 'starter',
            'method' => 'pix',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.plan', 'starter')
        ->assertJsonPath('data.status', 'approved');

    $tenant->refresh();
    expect($tenant->plan)->toBe('starter');
});

it('subscription records do not leak across tenants', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $ownerA = subOwner($tenantA);
    $ownerB = subOwner($tenantB);

    Subscription::create([
        'tenant_id' => $tenantA->id,
        'plan' => 'pro',
        'amount' => 9700,
        'method' => 'pix',
        'status' => 'approved',
        'mp_payment_id' => 'mp_aaa',
    ]);

    $this->mock(SubscriptionService::class, fn ($mock) => $mock
        ->shouldReceive('getPlans')
        ->once()
        ->andReturn([])
    );

    $response = $this->actingAs($ownerB)
        ->getJson("/api/v1/salao/{$tenantB->slug}/subscription");

    $response->assertOk()
        ->assertJsonCount(0, 'data.subscriptions');
});
