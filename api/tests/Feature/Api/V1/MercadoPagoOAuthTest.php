<?php

use App\Models\Appointment;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\MercadoPagoOAuthService;
use App\Services\PaymentService;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Resources\Order;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// --- Helpers ---

beforeEach(function () {
    config([
        'services.mercadopago.access_token' => 'GLOBAL_TOKEN',
        'services.mercadopago.app_id' => 'app-123',
        'services.mercadopago.client_secret' => 'client-secret-xyz',
        'services.mercadopago.redirect_uri' => 'https://api.test/api/v1/mercadopago/callback',
        'app.frontend_url' => 'https://front.test',
    ]);
});

function mpOwner(Tenant $tenant): User
{
    Role::firstOrCreate(['name' => 'salon_owner', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('salon_owner');
    $tenant->users()->attach($user->id, ['role' => 'owner']);

    return $user;
}

function mpStaff(Tenant $tenant): User
{
    Role::firstOrCreate(['name' => 'salon_staff', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('salon_staff');
    $tenant->users()->attach($user->id, ['role' => 'staff']);

    return $user;
}

function mpConnectedTenant(): Tenant
{
    return Tenant::factory()->create([
        'mp_access_token' => 'TENANT_ACCESS_TOKEN',
        'mp_refresh_token' => 'TENANT_REFRESH_TOKEN',
        'mp_user_id' => '999888',
        'mp_public_key' => 'APP_USR-pub',
        'mp_token_expires_at' => now()->addDays(30),
        'mp_connected_at' => now(),
    ]);
}

// --- connect ---

it('owner receives an authorization url with a cached state', function () {
    $tenant = Tenant::factory()->create();
    $owner = mpOwner($tenant);

    $response = $this->actingAs($owner)
        ->getJson("/api/v1/negocio/{$tenant->slug}/mercadopago/connect");

    $response->assertOk()
        ->assertJsonStructure(['data' => ['authorization_url']]);

    $url = $response->json('data.authorization_url');
    expect($url)->toContain('https://auth.mercadopago.com/authorization')
        ->and($url)->toContain('client_id=app-123')
        ->and($url)->toContain('response_type=code')
        ->and($url)->toContain('platform_id=mp');

    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    expect($query['state'])->not->toBeEmpty()
        ->and(Cache::get('mercadopago_oauth_state:'.$query['state']))->toBe($tenant->id);
});

it('rejects connect without authentication', function () {
    $tenant = Tenant::factory()->create();

    $this->getJson("/api/v1/negocio/{$tenant->slug}/mercadopago/connect")
        ->assertUnauthorized();
});

it('forbids connect for a non-owner (staff)', function () {
    $tenant = Tenant::factory()->create();
    $staff = mpStaff($tenant);

    $this->actingAs($staff)
        ->getJson("/api/v1/negocio/{$tenant->slug}/mercadopago/connect")
        ->assertForbidden();
});

// --- callback ---

it('exchanges the code for tokens and redirects with mp=connected', function () {
    Http::fake([
        'api.mercadopago.com/oauth/token' => Http::response([
            'access_token' => 'FRESH_ACCESS_TOKEN',
            'refresh_token' => 'FRESH_REFRESH_TOKEN',
            'user_id' => 555444,
            'public_key' => 'APP_USR-newpub',
            'expires_in' => 15552000,
        ]),
    ]);

    $tenant = Tenant::factory()->create();
    $state = 'valid-state-token';
    Cache::put('mercadopago_oauth_state:'.$state, $tenant->id, 600);

    $response = $this->get("/api/v1/mercadopago/callback?code=THE_CODE&state={$state}");

    $response->assertRedirect("https://front.test/{$tenant->slug}/dashboard/settings?mp=connected");

    $tenant->refresh();
    expect($tenant->mp_access_token)->toBe('FRESH_ACCESS_TOKEN')
        ->and($tenant->mp_refresh_token)->toBe('FRESH_REFRESH_TOKEN')
        ->and($tenant->mp_user_id)->toBe('555444')
        ->and($tenant->mp_connected_at)->not->toBeNull()
        ->and($tenant->hasMercadoPagoConnected())->toBeTrue();

    // state é one-time: invalidado após uso
    expect(Cache::get('mercadopago_oauth_state:'.$state))->toBeNull();
});

it('stores tokens encrypted at rest, never in plaintext', function () {
    Http::fake([
        'api.mercadopago.com/oauth/token' => Http::response([
            'access_token' => 'SECRET_ACCESS_TOKEN',
            'refresh_token' => 'SECRET_REFRESH_TOKEN',
            'user_id' => 111,
            'public_key' => 'pub',
            'expires_in' => 3600,
        ]),
    ]);

    $tenant = Tenant::factory()->create();
    $state = 'state-enc';
    Cache::put('mercadopago_oauth_state:'.$state, $tenant->id, 600);

    $this->get("/api/v1/mercadopago/callback?code=c&state={$state}")->assertRedirect();

    $raw = DB::table('tenants')->where('id', $tenant->id)->value('mp_access_token');
    expect($raw)->not->toBe('SECRET_ACCESS_TOKEN')
        ->and($raw)->not->toContain('SECRET_ACCESS_TOKEN');
});

it('redirects with mp=error when the state is invalid or expired', function () {
    Http::fake();

    $response = $this->get('/api/v1/mercadopago/callback?code=THE_CODE&state=unknown-state');

    $response->assertRedirect('https://front.test/dashboard/settings?mp=error');
    Http::assertNothingSent();
});

it('redirects with mp=error when code is missing', function () {
    $this->get('/api/v1/mercadopago/callback?state=whatever')
        ->assertRedirect('https://front.test/dashboard/settings?mp=error');
});

// --- status ---

it('reports connected true with owner-safe fields only', function () {
    $tenant = mpConnectedTenant();
    $owner = mpOwner($tenant);

    $response = $this->actingAs($owner)
        ->getJson("/api/v1/negocio/{$tenant->slug}/mercadopago/status");

    $response->assertOk()
        ->assertJsonPath('data.connected', true)
        ->assertJsonPath('data.mp_user_id', '999888')
        ->assertJsonStructure(['data' => ['connected', 'connected_at', 'mp_user_id']]);

    expect($response->getContent())->not->toContain('TENANT_ACCESS_TOKEN')
        ->and($response->getContent())->not->toContain('TENANT_REFRESH_TOKEN');
});

it('reports connected false when the tenant has no account', function () {
    $tenant = Tenant::factory()->create();
    $staff = mpStaff($tenant);

    $this->actingAs($staff)
        ->getJson("/api/v1/negocio/{$tenant->slug}/mercadopago/status")
        ->assertOk()
        ->assertJsonPath('data.connected', false)
        ->assertJsonPath('data.connected_at', null)
        ->assertJsonPath('data.mp_user_id', null);
});

it('does not let a tenant see another tenant mercadopago status or tokens', function () {
    $tenantA = mpConnectedTenant();
    $tenantB = Tenant::factory()->create();
    $ownerB = mpOwner($tenantB);

    $response = $this->actingAs($ownerB)
        ->getJson("/api/v1/negocio/{$tenantA->slug}/mercadopago/status");

    $response->assertForbidden();
    expect($response->getContent())->not->toContain('TENANT_ACCESS_TOKEN')
        ->and($response->getContent())->not->toContain('999888');
});

it('rejects status without authentication', function () {
    $tenant = Tenant::factory()->create();

    $this->getJson("/api/v1/negocio/{$tenant->slug}/mercadopago/status")
        ->assertUnauthorized();
});

// --- disconnect ---

it('owner can disconnect and all mp columns are cleared', function () {
    $tenant = mpConnectedTenant();
    $owner = mpOwner($tenant);

    $this->actingAs($owner)
        ->deleteJson("/api/v1/negocio/{$tenant->slug}/mercadopago/disconnect")
        ->assertOk()
        ->assertJsonPath('data.connected', false);

    $tenant->refresh();
    expect($tenant->mp_access_token)->toBeNull()
        ->and($tenant->mp_refresh_token)->toBeNull()
        ->and($tenant->mp_user_id)->toBeNull()
        ->and($tenant->mp_public_key)->toBeNull()
        ->and($tenant->mp_token_expires_at)->toBeNull()
        ->and($tenant->mp_connected_at)->toBeNull()
        ->and($tenant->hasMercadoPagoConnected())->toBeFalse();
});

it('forbids disconnect for a non-owner (staff)', function () {
    $tenant = mpConnectedTenant();
    $staff = mpStaff($tenant);

    $this->actingAs($staff)
        ->deleteJson("/api/v1/negocio/{$tenant->slug}/mercadopago/disconnect")
        ->assertForbidden();

    expect($tenant->fresh()->hasMercadoPagoConnected())->toBeTrue();
});

// --- refresh token ---

it('refreshes an expired token before use', function () {
    Http::fake([
        'api.mercadopago.com/oauth/token' => Http::response([
            'access_token' => 'REFRESHED_TOKEN',
            'refresh_token' => 'REFRESHED_REFRESH',
            'user_id' => 999888,
            'public_key' => 'pub',
            'expires_in' => 3600,
        ]),
    ]);

    $tenant = Tenant::factory()->create([
        'mp_access_token' => 'OLD_TOKEN',
        'mp_refresh_token' => 'OLD_REFRESH',
        'mp_user_id' => '999888',
        'mp_token_expires_at' => now()->subMinute(),
        'mp_connected_at' => now()->subMonth(),
    ]);

    $token = app(MercadoPagoOAuthService::class)->accessTokenFor($tenant);

    expect($token)->toBe('REFRESHED_TOKEN');
    Http::assertSent(fn ($request) => $request['grant_type'] === 'refresh_token');
});

// --- PaymentService token selection ---

function mpAppointment(Tenant $tenant): Appointment
{
    $client = User::factory()->create();
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id]);

    return Appointment::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'status' => 'pending',
        'price' => 5000,
    ]);
}

function mpFakeOrder(): Order
{
    // Order real (o SDK tipa createMercadoPagoOrder(): Order). Deixamos
    // `transactions` sem inicializar de propósito — orderPaymentMethodField()
    // lida com isso via null-safe, e o teste só se importa com o token.
    $order = new Order;
    $order->id = 'order-xyz';
    $order->status = 'processed';
    $order->status_detail = 'accredited';

    return $order;
}

it('uses the tenant token when the tenant has a connected account', function () {
    $tenant = mpConnectedTenant();
    $appointment = mpAppointment($tenant);
    $payer = User::factory()->create();
    app()->instance('currentTenant', $tenant);

    $service = $this->partialMock(PaymentService::class, function ($mock) {
        $mock->shouldAllowMockingProtectedMethods()
            ->shouldReceive('createMercadoPagoOrder')
            ->once()
            ->andReturn(mpFakeOrder());
    });

    $service->createPix($appointment, $payer);

    expect(MercadoPagoConfig::getAccessToken())->toBe('TENANT_ACCESS_TOKEN');
});

it('falls back to the global token when the tenant is not connected', function () {
    $tenant = Tenant::factory()->create();
    $appointment = mpAppointment($tenant);
    $payer = User::factory()->create();
    app()->instance('currentTenant', $tenant);

    $service = $this->partialMock(PaymentService::class, function ($mock) {
        $mock->shouldAllowMockingProtectedMethods()
            ->shouldReceive('createMercadoPagoOrder')
            ->once()
            ->andReturn(mpFakeOrder());
    });

    $service->createPix($appointment, $payer);

    expect(MercadoPagoConfig::getAccessToken())->toBe('GLOBAL_TOKEN');
});

it('always uses the global token for subscriptions even after a tenant token was set', function () {
    $tenant = Tenant::factory()->create(['plan' => 'starter']);
    $payer = User::factory()->create();

    $subOrder = new Order;
    $subOrder->id = 'sub-order';
    $subOrder->status = 'pending';

    $service = $this->partialMock(SubscriptionService::class, function ($mock) use ($subOrder) {
        $mock->shouldAllowMockingProtectedMethods()
            ->shouldReceive('createMercadoPagoOrder')
            ->once()
            ->andReturn($subOrder);
    });

    // Simula o estado global do SDK "sujo" por um pagamento de tenant anterior
    MercadoPagoConfig::setAccessToken('LEFTOVER_TENANT_TOKEN');

    $service->createPixSubscription($tenant, $payer, 'pro');

    expect(MercadoPagoConfig::getAccessToken())->toBe('GLOBAL_TOKEN');
});
