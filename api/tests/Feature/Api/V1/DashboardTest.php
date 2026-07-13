<?php

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// --- Helpers ---

function dashOwner(Tenant $tenant): User
{
    Role::firstOrCreate(['name' => 'salon_owner', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('salon_owner');
    $tenant->users()->attach($user->id, ['role' => 'owner']);

    return $user;
}

function dashStaff(Tenant $tenant): User
{
    Role::firstOrCreate(['name' => 'salon_staff', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('salon_staff');
    $tenant->users()->attach($user->id, ['role' => 'staff']);

    return $user;
}

function dashClient(Tenant $tenant): User
{
    Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('client');
    $tenant->users()->attach($user->id, ['role' => 'client']);

    return $user;
}

// --- Testes ---

it('salon_owner acessa dashboard e retorna estrutura completa', function () {
    $tenant = Tenant::factory()->create();
    $owner = dashOwner($tenant);

    $response = $this->actingAs($owner)->getJson("/api/v1/negocio/{$tenant->slug}/dashboard");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'summary' => [
                    'total_appointments',
                    'completed_appointments',
                    'appointments_today',
                    'total_clients',
                    'total_revenue',
                    'revenue_this_month',
                ],
                'appointments_by_status',
                'revenue_by_day',
                'top_services',
                'appointments_by_professional',
            ],
            'period',
        ]);
});

it('salon_staff acessa dashboard normalmente', function () {
    $tenant = Tenant::factory()->create();
    $staff = dashStaff($tenant);

    $this->actingAs($staff)
        ->getJson("/api/v1/negocio/{$tenant->slug}/dashboard")
        ->assertOk();
});

it('client recebe 403', function () {
    $tenant = Tenant::factory()->create();
    $client = dashClient($tenant);

    $this->actingAs($client)
        ->getJson("/api/v1/negocio/{$tenant->slug}/dashboard")
        ->assertForbidden();
});

it('requisicao sem autenticacao recebe 401', function () {
    $tenant = Tenant::factory()->create();

    $this->getJson("/api/v1/negocio/{$tenant->slug}/dashboard")
        ->assertUnauthorized();
});

it('period e limitado a 90 dias', function () {
    $tenant = Tenant::factory()->create();
    $owner = dashOwner($tenant);

    $response = $this->actingAs($owner)
        ->getJson("/api/v1/negocio/{$tenant->slug}/dashboard?period=200");

    $response->assertOk()
        ->assertJsonPath('period', 90);
});

it('receita soma apenas pagamentos aprovados', function () {
    $tenant = Tenant::factory()->create();
    $owner = dashOwner($tenant);
    $service = Service::factory()->create(['tenant_id' => $tenant->id]);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $clientUser = dashClient($tenant);

    $appointment = Appointment::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $clientUser->id,
        'professional_id' => $professional->id,
        'service_id' => $service->id,
        'status' => 'completed',
    ]);

    Payment::factory()->create([
        'tenant_id' => $tenant->id,
        'appointment_id' => $appointment->id,
        'amount' => 5000,
        'status' => 'approved',
        'paid_at' => now(),
    ]);

    Payment::factory()->create([
        'tenant_id' => $tenant->id,
        'appointment_id' => $appointment->id,
        'amount' => 9999,
        'status' => 'pending',
        'paid_at' => null,
    ]);

    $response = $this->actingAs($owner)
        ->getJson("/api/v1/negocio/{$tenant->slug}/dashboard");

    $response->assertOk()
        ->assertJsonPath('data.summary.total_revenue', 5000);
});
