<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

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

        // Usuário de teste genérico
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
