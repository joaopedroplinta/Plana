<?php

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
        ->getJson("/api/v1/negocio/{$tenant->slug}/subscription");

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
        ->getJson("/api/v1/negocio/{$tenant->slug}/subscription");

    $response->assertOk()
        ->assertJsonCount(1, 'data.subscriptions');
});

it('returns 401 when unauthenticated on GET', function () {
    $tenant = Tenant::factory()->create();

    $this->getJson("/api/v1/negocio/{$tenant->slug}/subscription")
        ->assertUnauthorized();
});

// --- POST /subscription — starter (free) ---

it('switches to starter plan for free without calling MercadoPago', function () {
    $tenant = Tenant::factory()->create(['plan' => 'pro']);
    $owner = subOwner($tenant);

    $response = $this->actingAs($owner)
        ->postJson("/api/v1/negocio/{$tenant->slug}/subscription", [
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
        ->postJson("/api/v1/negocio/{$tenant->slug}/subscription", [
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
        ->postJson("/api/v1/negocio/{$tenant->slug}/subscription", [
            'plan' => 'pro',
            'method' => 'pix',
        ])
        ->assertForbidden();
});

it('returns 401 when unauthenticated on POST', function () {
    $tenant = Tenant::factory()->create();

    $this->postJson("/api/v1/negocio/{$tenant->slug}/subscription", [
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
        ->postJson("/api/v1/negocio/{$tenantB->slug}/subscription", [
            'plan' => 'pro',
            'method' => 'pix',
        ])
        ->assertForbidden();
});

it('owner of one tenant who is staff at another cannot change that tenant subscription', function () {
    $tenantA = Tenant::factory()->create();
    // Plano fixo (não 'pro'): TenantFactory sorteia o plano aleatoriamente,
    // e a asserção abaixo verifica que a mudança para 'pro' foi bloqueada.
    $tenantB = Tenant::factory()->create(['plan' => 'starter']);

    $user = subOwner($tenantA);
    $tenantB->users()->attach($user->id, ['role' => 'staff']);

    $this->actingAs($user)
        ->postJson("/api/v1/negocio/{$tenantB->slug}/subscription", [
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
        ->postJson("/api/v1/negocio/{$tenant->slug}/subscription", [
            'plan' => 'starter',
            'method' => 'pix',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.plan', 'starter')
        ->assertJsonPath('data.status', 'approved');

    $tenant->refresh();
    expect($tenant->plan)->toBe('starter');
});

// --- POST /subscription — yearly billing cycle ---

it('creates a pending pix subscription with yearly billing cycle and full annual amount for pro', function () {
    $tenant = Tenant::factory()->create(['plan' => 'starter']);
    $owner = subOwner($tenant);

    $fakeSubscription = new Subscription([
        'tenant_id' => $tenant->id,
        'plan' => 'pro',
        'billing_cycle' => 'yearly',
        'amount' => 97000,
        'method' => 'pix',
        'status' => 'pending',
        'mp_payment_id' => 'mp_yearly_1',
        'pix_qr_code' => 'qr_code_text',
        'pix_qr_code_base64' => 'qr_base64',
    ]);
    $fakeSubscription->id = (string) Str::uuid();
    $fakeSubscription->created_at = now();
    $fakeSubscription->updated_at = now();

    $this->mock(SubscriptionService::class, fn ($mock) => $mock
        ->shouldReceive('createPixSubscription')
        ->once()
        ->with(
            Mockery::on(fn ($arg) => $arg instanceof Tenant && $arg->id === $tenant->id),
            Mockery::on(fn ($arg) => $arg instanceof User),
            'pro',
            'yearly'
        )
        ->andReturn($fakeSubscription)
    );

    $response = $this->actingAs($owner)
        ->postJson("/api/v1/negocio/{$tenant->slug}/subscription", [
            'plan' => 'pro',
            'method' => 'pix',
            'billing_cycle' => 'yearly',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.plan', 'pro')
        ->assertJsonPath('data.billing_cycle', 'yearly')
        ->assertJsonPath('data.amount', 97000)
        ->assertJsonPath('data.status', 'pending');
});

it('creates a checkout pro subscription with yearly billing cycle and full annual amount for enterprise', function () {
    $tenant = Tenant::factory()->create(['plan' => 'starter']);
    $owner = subOwner($tenant);

    $fakeSubscription = new Subscription([
        'tenant_id' => $tenant->id,
        'plan' => 'enterprise',
        'billing_cycle' => 'yearly',
        'amount' => 197000,
        'method' => 'credit_card',
        'status' => 'approved',
        'mp_payment_id' => 'mp_yearly_2',
    ]);
    $fakeSubscription->id = (string) Str::uuid();
    $fakeSubscription->created_at = now();
    $fakeSubscription->updated_at = now();

    $this->mock(SubscriptionService::class, fn ($mock) => $mock
        ->shouldReceive('createCheckoutProSubscription')
        ->once()
        ->with(
            Mockery::on(fn ($arg) => $arg instanceof Tenant && $arg->id === $tenant->id),
            Mockery::on(fn ($arg) => $arg instanceof User),
            'enterprise',
            Mockery::type('array'),
            'yearly'
        )
        ->andReturn($fakeSubscription)
    );

    $response = $this->actingAs($owner)
        ->postJson("/api/v1/negocio/{$tenant->slug}/subscription", [
            'plan' => 'enterprise',
            'method' => 'credit_card',
            'billing_cycle' => 'yearly',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.plan', 'enterprise')
        ->assertJsonPath('data.billing_cycle', 'yearly')
        ->assertJsonPath('data.amount', 197000)
        ->assertJsonPath('data.status', 'approved');
});

it('defaults to monthly billing cycle when not informed', function () {
    $tenant = Tenant::factory()->create(['plan' => 'starter']);
    $owner = subOwner($tenant);

    $this->mock(SubscriptionService::class, fn ($mock) => $mock
        ->shouldReceive('createPixSubscription')
        ->once()
        ->with(Mockery::any(), Mockery::any(), 'pro', 'monthly')
        ->andReturn(tap(new Subscription([
            'tenant_id' => $tenant->id,
            'plan' => 'pro',
            'billing_cycle' => 'monthly',
            'amount' => 9700,
            'method' => 'pix',
            'status' => 'pending',
        ]), function (Subscription $s) {
            $s->id = (string) Str::uuid();
            $s->created_at = now();
            $s->updated_at = now();
        }))
    );

    $response = $this->actingAs($owner)
        ->postJson("/api/v1/negocio/{$tenant->slug}/subscription", [
            'plan' => 'pro',
            'method' => 'pix',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.billing_cycle', 'monthly');
});

it('rejects yearly billing cycle for the starter plan', function () {
    $tenant = Tenant::factory()->create(['plan' => 'starter']);
    $owner = subOwner($tenant);

    $response = $this->actingAs($owner)
        ->postJson("/api/v1/negocio/{$tenant->slug}/subscription", [
            'plan' => 'starter',
            'method' => 'pix',
            'billing_cycle' => 'yearly',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('billing_cycle');
});

it('rejects an invalid billing_cycle value', function () {
    $tenant = Tenant::factory()->create(['plan' => 'starter']);
    $owner = subOwner($tenant);

    $response = $this->actingAs($owner)
        ->postJson("/api/v1/negocio/{$tenant->slug}/subscription", [
            'plan' => 'pro',
            'method' => 'pix',
            'billing_cycle' => 'weekly',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('billing_cycle');
});

// --- Real SubscriptionService::amountFor() ---

it('amountFor returns the exact annual amount in cents for pro and enterprise', function () {
    expect(SubscriptionService::amountFor('pro', 'monthly'))->toBe(9700)
        ->and(SubscriptionService::amountFor('pro', 'yearly'))->toBe(97000)
        ->and(SubscriptionService::amountFor('enterprise', 'monthly'))->toBe(19700)
        ->and(SubscriptionService::amountFor('enterprise', 'yearly'))->toBe(197000)
        ->and(SubscriptionService::amountFor('starter', 'monthly'))->toBe(0);
});

it('amountFor throws a validation exception for starter yearly', function () {
    expect(fn () => SubscriptionService::amountFor('starter', 'yearly'))
        ->toThrow(ValidationException::class);
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
        ->getJson("/api/v1/negocio/{$tenantB->slug}/subscription");

    $response->assertOk()
        ->assertJsonCount(0, 'data.subscriptions');
});
