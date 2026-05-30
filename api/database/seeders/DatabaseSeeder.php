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

        // Roles Spatie
        $ownerRole = Role::firstOrCreate(['name' => 'salon_owner', 'guard_name' => 'web']);
        $superRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'salon_staff', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);

        // Owner do tenant de exemplo
        [$owner, $ownerCreated] = [
            User::firstOrCreate(
                ['email' => 'owner@salao-demo.com.br'],
                ['name' => 'João da Silva', 'password' => bcrypt('password')]
            ),
            false,
        ];

        if (! $owner->hasRole('salon_owner')) {
            $owner->assignRole($ownerRole);
        }

        // Associa owner ao tenant via pivot
        $tenant->users()->syncWithoutDetaching([
            $owner->id => ['role' => 'owner'],
        ]);

        // Super admin da plataforma
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@agendei.com'],
            ['name' => 'Super Admin', 'password' => bcrypt('password')]
        );

        if (! $superAdmin->hasRole('super_admin')) {
            $superAdmin->assignRole($superRole);
        }

        // Seed de catálogo apenas se o tenant foi recém-criado
        if (Service::where('tenant_id', $tenant->id)->doesntExist()) {
            Service::factory(8)->create(['tenant_id' => $tenant->id]);

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

            $serviceIds = Service::where('tenant_id', $tenant->id)->pluck('id')->toArray();
            ServicePackage::factory(2)->create(['tenant_id' => $tenant->id])->each(function (ServicePackage $pkg) use ($serviceIds) {
                $selected = array_slice($serviceIds, 0, min(3, count($serviceIds)));
                foreach ($selected as $sid) {
                    PackageService::create(['package_id' => $pkg->id, 'service_id' => $sid]);
                }
            });
        }
    }
}
