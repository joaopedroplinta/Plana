<?php

use App\Models\Appointment;
use App\Models\PackagePurchase;
use App\Models\Professional;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\ServicePackage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// --- Helpers ---

function apcOwner(Tenant $tenant): User
{
    Role::firstOrCreate(['name' => 'salon_owner', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('salon_owner');
    $tenant->users()->attach($user->id, ['role' => 'owner']);

    return $user;
}

function apcClient(Tenant $tenant): User
{
    Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('client');
    $tenant->users()->attach($user->id, ['role' => 'client']);

    return $user;
}

function apcFullWeekSchedule(Tenant $tenant, Professional $professional): void
{
    foreach (range(0, 6) as $day) {
        Schedule::factory()->create([
            'tenant_id' => $tenant->id,
            'professional_id' => $professional->id,
            'day_of_week' => $day,
            'start_time' => '08:00:00',
            'end_time' => '20:00:00',
        ]);
    }
}

/** @return array{Tenant, User, Professional, Service, ServicePackage} */
function apcSetup(): array
{
    $tenant = Tenant::factory()->create();
    $client = apcClient($tenant);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 60, 'price' => 8000]);
    apcFullWeekSchedule($tenant, $professional);

    $package = ServicePackage::factory()->create(['tenant_id' => $tenant->id]);
    $package->services()->sync([$service->id]);

    return [$tenant, $client, $professional, $service, $package];
}

// --- Consumo de sessão ---

it('consome sessao de pacote ativo e zera o preco do agendamento', function () {
    [$tenant, $client, $professional, $service, $package] = apcSetup();

    $purchase = PackagePurchase::factory()->active()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'service_package_id' => $package->id,
        'sessions_total' => 5,
        'sessions_used' => 0,
    ]);

    $response = $this->actingAs($client)->postJson("/api/v1/salao/{$tenant->slug}/appointments", [
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'starts_at' => now()->addDay()->setTime(10, 0)->toIso8601String(),
        'package_purchase_id' => $purchase->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.price', 0)
        ->assertJsonPath('data.package_purchase_id', $purchase->id);

    expect($purchase->fresh()->sessions_used)->toBe(1);
});

it('rejeita pacote que nao pertence ao cliente autenticado', function () {
    [$tenant, , $professional, $service, $package] = apcSetup();
    $otherClient = apcClient($tenant);
    $client = apcClient($tenant);

    $purchase = PackagePurchase::factory()->active()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $otherClient->id,
        'service_package_id' => $package->id,
    ]);

    $response = $this->actingAs($client)->postJson("/api/v1/salao/{$tenant->slug}/appointments", [
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'starts_at' => now()->addDay()->setTime(10, 0)->toIso8601String(),
        'package_purchase_id' => $purchase->id,
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['package_purchase_id']);
});

it('rejeita pacote de outro tenant', function () {
    [$tenant, $client, $professional, $service] = apcSetup();
    $tenantB = Tenant::factory()->create();
    $packageB = ServicePackage::factory()->create(['tenant_id' => $tenantB->id]);

    $purchase = PackagePurchase::factory()->active()->create([
        'tenant_id' => $tenantB->id,
        'client_id' => $client->id,
        'service_package_id' => $packageB->id,
    ]);

    $response = $this->actingAs($client)->postJson("/api/v1/salao/{$tenant->slug}/appointments", [
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'starts_at' => now()->addDay()->setTime(10, 0)->toIso8601String(),
        'package_purchase_id' => $purchase->id,
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['package_purchase_id']);
});

it('rejeita pacote expirado', function () {
    [$tenant, $client, $professional, $service, $package] = apcSetup();

    $purchase = PackagePurchase::factory()->active()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'service_package_id' => $package->id,
        'expires_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($client)->postJson("/api/v1/salao/{$tenant->slug}/appointments", [
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'starts_at' => now()->addDay()->setTime(10, 0)->toIso8601String(),
        'package_purchase_id' => $purchase->id,
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['package_purchase_id']);
});

it('rejeita pacote ainda pendente (nao ativo)', function () {
    [$tenant, $client, $professional, $service, $package] = apcSetup();

    $purchase = PackagePurchase::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'service_package_id' => $package->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($client)->postJson("/api/v1/salao/{$tenant->slug}/appointments", [
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'starts_at' => now()->addDay()->setTime(10, 0)->toIso8601String(),
        'package_purchase_id' => $purchase->id,
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['package_purchase_id']);
});

it('rejeita pacote sem sessoes disponiveis', function () {
    [$tenant, $client, $professional, $service, $package] = apcSetup();

    $purchase = PackagePurchase::factory()->active()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'service_package_id' => $package->id,
        'sessions_total' => 3,
        'sessions_used' => 3,
    ]);

    $response = $this->actingAs($client)->postJson("/api/v1/salao/{$tenant->slug}/appointments", [
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'starts_at' => now()->addDay()->setTime(10, 0)->toIso8601String(),
        'package_purchase_id' => $purchase->id,
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['package_purchase_id']);
});

it('rejeita quando o servico do agendamento nao esta incluido no pacote', function () {
    [$tenant, $client, $professional, , $package] = apcSetup();
    $otherService = Service::factory()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 60]);

    $purchase = PackagePurchase::factory()->active()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'service_package_id' => $package->id,
    ]);

    $response = $this->actingAs($client)->postJson("/api/v1/salao/{$tenant->slug}/appointments", [
        'professional_id' => $professional->id,
        'service_id' => $otherService->id,
        'starts_at' => now()->addDay()->setTime(10, 0)->toIso8601String(),
        'package_purchase_id' => $purchase->id,
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['package_purchase_id']);
});

it('esgota as sessoes do pacote apos a ultima reserva valida', function () {
    [$tenant, $client, $professional, $service, $package] = apcSetup();

    $purchase = PackagePurchase::factory()->active()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'service_package_id' => $package->id,
        'sessions_total' => 2,
        'sessions_used' => 1,
    ]);

    // Última sessão disponível: sucesso.
    $this->actingAs($client)->postJson("/api/v1/salao/{$tenant->slug}/appointments", [
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'starts_at' => now()->addDay()->setTime(10, 0)->toIso8601String(),
        'package_purchase_id' => $purchase->id,
    ])->assertCreated();

    expect($purchase->fresh()->sessions_used)->toBe(2);

    // Sem sessões restantes: falha (o lockForUpdate no controller garante
    // que isso vale mesmo com duas requisições concorrentes).
    $this->actingAs($client)->postJson("/api/v1/salao/{$tenant->slug}/appointments", [
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'starts_at' => now()->addDay()->setTime(11, 0)->toIso8601String(),
        'package_purchase_id' => $purchase->id,
    ])->assertUnprocessable()->assertJsonValidationErrors(['package_purchase_id']);
});

// --- Devolução de sessão no cancelamento ---

it('devolve a sessao do pacote ao cancelar o agendamento', function () {
    [$tenant, $client, $professional, $service, $package] = apcSetup();

    $purchase = PackagePurchase::factory()->active()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'service_package_id' => $package->id,
        'sessions_total' => 5,
        'sessions_used' => 1,
    ]);

    $appointment = Appointment::factory()->pending()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'package_purchase_id' => $purchase->id,
        'price' => 0,
    ]);

    $this->actingAs($client)
        ->patchJson("/api/v1/salao/{$tenant->slug}/appointments/{$appointment->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect($purchase->fresh()->sessions_used)->toBe(0);
});

it('nao permite sessions_used negativo ao cancelar agendamento com pacote ja sem sessoes usadas', function () {
    [$tenant, $client, $professional, $service, $package] = apcSetup();

    $purchase = PackagePurchase::factory()->active()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'service_package_id' => $package->id,
        'sessions_total' => 5,
        'sessions_used' => 0,
    ]);

    $appointment = Appointment::factory()->pending()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'package_purchase_id' => $purchase->id,
        'price' => 0,
    ]);

    $this->actingAs($client)
        ->patchJson("/api/v1/salao/{$tenant->slug}/appointments/{$appointment->id}/cancel")
        ->assertOk();

    expect($purchase->fresh()->sessions_used)->toBe(0);
});

it('nao devolve sessao quando agendamento sem pacote e cancelado', function () {
    [$tenant, $client, $professional, $service] = apcSetup();

    $appointment = Appointment::factory()->pending()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'price' => 8000,
    ]);

    $this->actingAs($client)
        ->patchJson("/api/v1/salao/{$tenant->slug}/appointments/{$appointment->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
});
