<?php

namespace Database\Seeders;

use App\Models\PackageService;
use App\Models\Professional;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\ServicePackage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Tenant de exemplo
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'salao-demo'],
            [
                'name' => 'Salão Demo',
                'plan' => 'pro',
                'settings' => [],
                'active' => true,
            ]
        );

        // Owner do tenant de exemplo
        $owner = User::factory()->create([
            'name' => 'João da Silva',
            'email' => 'owner@salao-demo.com.br',
        ]);

        // Associa owner ao tenant via pivot
        $tenant->users()->syncWithoutDetaching([
            $owner->id => ['role' => 'owner'],
        ]);

        // 8 serviços realistas para o tenant demo
        Service::factory(8)->create(['tenant_id' => $tenant->id]);

        // 3 profissionais com agenda seg–sáb (days 1–6), 09:00–18:00
        $professionals = Professional::factory(3)->create(['tenant_id' => $tenant->id]);
        foreach ($professionals as $pro) {
            foreach (range(1, 6) as $day) {
                Schedule::factory()->create([
                    'tenant_id' => $tenant->id,
                    'professional_id' => $pro->id,
                    'day_of_week' => $day,
                    'start_time' => '09:00',
                    'end_time' => '18:00',
                ]);
            }
        }

        // 2 pacotes associados a serviços do tenant
        $serviceIds = Service::where('tenant_id', $tenant->id)->pluck('id')->toArray();
        ServicePackage::factory(2)->create(['tenant_id' => $tenant->id])->each(function (ServicePackage $pkg) use ($serviceIds) {
            $selected = array_slice($serviceIds, 0, min(3, count($serviceIds)));
            foreach ($selected as $sid) {
                PackageService::create(['package_id' => $pkg->id, 'service_id' => $sid]);
            }
        });

        // Roles Spatie
        $ownerRole  = Role::firstOrCreate(['name' => 'salon_owner', 'guard_name' => 'web']);
        $superRole  = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'salon_staff', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);

        $owner->assignRole($ownerRole);

        // Super admin da plataforma
        $superAdmin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@agendei.com',
        ]);
        $superAdmin->assignRole($superRole);
    }
}
