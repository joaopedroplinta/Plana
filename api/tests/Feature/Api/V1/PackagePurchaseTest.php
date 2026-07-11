<?php

use App\Enums\PackagePurchaseStatus;
use App\Models\PackagePurchase;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServicePackage;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// --- Helpers ---

function ppOwner(Tenant $tenant): User
{
    Role::firstOrCreate(['name' => 'salon_owner', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('salon_owner');
    $tenant->users()->attach($user->id, ['role' => 'owner']);

    return $user;
}

function ppClient(Tenant $tenant): User
{
    Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('client');
    $tenant->users()->attach($user->id, ['role' => 'client']);

    return $user;
}

// --- Comprar pacote ---

it('cliente compra pacote via pix', function () {
    $tenant = Tenant::factory()->create();
    $client = ppClient($tenant);
    $package = ServicePackage::factory()->create(['tenant_id' => $tenant->id, 'sessions' => 5, 'price' => 20000, 'valid_days' => 90]);

    $fakePayment = Payment::factory()->pix()->make(['tenant_id' => $tenant->id, 'amount' => 20000]);

    $this->mock(PaymentService::class, fn ($mock) => $mock
        ->shouldReceive('createPixForPackagePurchase')
        ->once()
        ->andReturn($fakePayment));

    $response = $this->actingAs($client)
        ->postJson("/api/v1/salao/{$tenant->slug}/packages/{$package->id}/purchase", [
            'method' => 'pix',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.sessions_total', 5)
        ->assertJsonPath('data.sessions_remaining', 5)
        ->assertJsonPath('data.price_paid', 20000);

    $this->assertDatabaseHas('package_purchases', [
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'service_package_id' => $package->id,
        'sessions_total' => 5,
        'sessions_used' => 0,
        'price_paid' => 20000,
        'status' => 'pending',
    ]);
});

it('cliente compra pacote via cartao de credito', function () {
    $tenant = Tenant::factory()->create();
    $client = ppClient($tenant);
    $package = ServicePackage::factory()->create(['tenant_id' => $tenant->id, 'price' => 15000]);

    $fakePayment = Payment::factory()->creditCard()->make(['tenant_id' => $tenant->id, 'amount' => 15000]);

    $this->mock(PaymentService::class, fn ($mock) => $mock
        ->shouldReceive('createCheckoutProForPackagePurchase')
        ->once()
        ->andReturn($fakePayment));

    $response = $this->actingAs($client)
        ->postJson("/api/v1/salao/{$tenant->slug}/packages/{$package->id}/purchase", [
            'method' => 'credit_card',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'pending');
});

it('pacote copia sessions e price no momento da compra mesmo que o admin edite depois', function () {
    $tenant = Tenant::factory()->create();
    $client = ppClient($tenant);
    $package = ServicePackage::factory()->create(['tenant_id' => $tenant->id, 'sessions' => 10, 'price' => 50000]);

    $fakePayment = Payment::factory()->pix()->make(['tenant_id' => $tenant->id]);
    $this->mock(PaymentService::class, fn ($mock) => $mock
        ->shouldReceive('createPixForPackagePurchase')->once()->andReturn($fakePayment));

    $this->actingAs($client)
        ->postJson("/api/v1/salao/{$tenant->slug}/packages/{$package->id}/purchase", ['method' => 'pix'])
        ->assertCreated();

    $package->update(['sessions' => 2, 'price' => 1000]);

    $purchase = PackagePurchase::where('service_package_id', $package->id)->first();
    expect($purchase->sessions_total)->toBe(10)
        ->and($purchase->price_paid)->toBe(50000);
});

// --- Webhook: ativação da compra ---

it('webhook aprova compra via pix (external_id) e ativa o pacote', function () {
    $tenant = Tenant::factory()->create();
    $client = ppClient($tenant);
    $package = ServicePackage::factory()->create(['tenant_id' => $tenant->id, 'valid_days' => 60]);

    $payment = Payment::factory()->pix()->create([
        'tenant_id' => $tenant->id,
        'appointment_id' => null,
        'external_id' => '555000111',
        'status' => 'pending',
    ]);

    $purchase = PackagePurchase::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'service_package_id' => $package->id,
        'sessions_total' => 5,
        'status' => 'pending',
        'payment_id' => $payment->id,
    ]);

    $this->partialMock(PaymentService::class, function ($mock) use ($purchase) {
        $mock->shouldAllowMockingProtectedMethods()
            ->shouldReceive('fetchPayment')
            ->once()
            ->andReturn((object) [
                'status' => 'approved',
                'external_reference' => "package_purchase_{$purchase->id}",
            ]);
    });

    $this->postJson('/api/v1/payments/webhook', [
        'type' => 'payment',
        'data' => ['id' => '555000111'],
    ])->assertOk();

    $fresh = $purchase->fresh();
    expect($fresh->status)->toBe(PackagePurchaseStatus::Active)
        ->and($fresh->purchased_at)->not->toBeNull()
        ->and($fresh->expires_at->diffInDays($fresh->purchased_at->copy()->addDays(60)))->toBeLessThan(1)
        ->and($payment->fresh()->status)->toBe('approved');
});

it('webhook aprova compra via checkout pro (external_reference) e ativa o pacote', function () {
    $tenant = Tenant::factory()->create();
    $client = ppClient($tenant);
    $package = ServicePackage::factory()->create(['tenant_id' => $tenant->id, 'valid_days' => 30]);

    $payment = Payment::factory()->creditCard()->create([
        'tenant_id' => $tenant->id,
        'appointment_id' => null,
        'external_id' => null,
        'preference_id' => 'pref-pkg-1',
        'status' => 'pending',
    ]);

    $purchase = PackagePurchase::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'service_package_id' => $package->id,
        'status' => 'pending',
        'payment_id' => $payment->id,
    ]);

    $this->partialMock(PaymentService::class, function ($mock) use ($purchase) {
        $mock->shouldAllowMockingProtectedMethods()
            ->shouldReceive('fetchPayment')
            ->once()
            ->andReturn((object) [
                'status' => 'approved',
                'external_reference' => "package_purchase_{$purchase->id}",
            ]);
    });

    $this->postJson('/api/v1/payments/webhook', [
        'type' => 'payment',
        'data' => ['id' => '999888777'],
    ])->assertOk();

    expect($purchase->fresh()->status)->toBe(PackagePurchaseStatus::Active)
        ->and($payment->fresh()->external_id)->toBe('999888777')
        ->and($payment->fresh()->status)->toBe('approved');
});

it('webhook nao reativa compra ja ativa', function () {
    $tenant = Tenant::factory()->create();
    $client = ppClient($tenant);
    $package = ServicePackage::factory()->create(['tenant_id' => $tenant->id]);

    $payment = Payment::factory()->pix()->create([
        'tenant_id' => $tenant->id,
        'appointment_id' => null,
        'external_id' => '444555',
        'status' => 'approved',
        'paid_at' => now()->subDay(),
    ]);

    $purchase = PackagePurchase::factory()->active()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'service_package_id' => $package->id,
        'payment_id' => $payment->id,
    ]);
    $originalPurchasedAt = $purchase->purchased_at;

    $this->partialMock(PaymentService::class, function ($mock) {
        $mock->shouldAllowMockingProtectedMethods()
            ->shouldReceive('fetchPayment')
            ->once()
            ->andReturn((object) ['status' => 'approved', 'external_reference' => null]);
    });

    $this->postJson('/api/v1/payments/webhook', [
        'type' => 'payment',
        'data' => ['id' => '444555'],
    ])->assertOk();

    expect($purchase->fresh()->purchased_at->equalTo($originalPurchasedAt))->toBeTrue();
});

// --- Listar / visualizar compras ---

it('cliente lista apenas as proprias compras de pacote', function () {
    $tenant = Tenant::factory()->create();
    $clientA = ppClient($tenant);
    $clientB = ppClient($tenant);
    $package = ServicePackage::factory()->create(['tenant_id' => $tenant->id]);

    PackagePurchase::factory(2)->create(['tenant_id' => $tenant->id, 'client_id' => $clientA->id, 'service_package_id' => $package->id]);
    PackagePurchase::factory(3)->create(['tenant_id' => $tenant->id, 'client_id' => $clientB->id, 'service_package_id' => $package->id]);

    $response = $this->actingAs($clientA)->getJson("/api/v1/salao/{$tenant->slug}/package-purchases");

    $response->assertOk()->assertJsonCount(2, 'data');
});

it('inclui os servicos do pacote na listagem de compras (usado pelo booking para oferecer pagar com pacote)', function () {
    $tenant = Tenant::factory()->create();
    $client = ppClient($tenant);
    $package = ServicePackage::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id]);
    $package->services()->sync([$service->id]);

    PackagePurchase::factory()->active()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'service_package_id' => $package->id,
    ]);

    $response = $this->actingAs($client)->getJson("/api/v1/salao/{$tenant->slug}/package-purchases");

    $response->assertOk()
        ->assertJsonStructure(['data' => [['service_package' => ['services']]]])
        ->assertJsonPath('data.0.service_package.services.0.id', $service->id);
});

it('owner lista todas as compras de pacote do tenant', function () {
    $tenant = Tenant::factory()->create();
    $owner = ppOwner($tenant);
    $client = ppClient($tenant);
    $package = ServicePackage::factory()->create(['tenant_id' => $tenant->id]);

    PackagePurchase::factory(4)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'service_package_id' => $package->id]);

    $response = $this->actingAs($owner)->getJson("/api/v1/salao/{$tenant->slug}/package-purchases");

    $response->assertOk()->assertJsonCount(4, 'data');
});

it('nao vaza compras de pacote entre tenants', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $ownerA = ppOwner($tenantA);
    $clientB = ppClient($tenantB);
    $packageB = ServicePackage::factory()->create(['tenant_id' => $tenantB->id]);

    PackagePurchase::factory(3)->create(['tenant_id' => $tenantB->id, 'client_id' => $clientB->id, 'service_package_id' => $packageB->id]);

    $response = $this->actingAs($ownerA)->getJson("/api/v1/salao/{$tenantA->slug}/package-purchases");

    $response->assertOk()->assertJsonCount(0, 'data');
});

it('cliente nao pode ver compra de pacote de outro cliente', function () {
    $tenant = Tenant::factory()->create();
    $clientA = ppClient($tenant);
    $clientB = ppClient($tenant);
    $package = ServicePackage::factory()->create(['tenant_id' => $tenant->id]);

    $purchase = PackagePurchase::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $clientB->id, 'service_package_id' => $package->id]);

    $this->actingAs($clientA)
        ->getJson("/api/v1/salao/{$tenant->slug}/package-purchases/{$purchase->id}")
        ->assertForbidden();
});
