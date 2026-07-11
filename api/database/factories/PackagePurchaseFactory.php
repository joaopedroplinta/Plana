<?php

namespace Database\Factories;

use App\Models\PackagePurchase;
use App\Models\ServicePackage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PackagePurchase>
 */
class PackagePurchaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'tenant_id' => Tenant::factory(),
            'client_id' => User::factory(),
            'service_package_id' => ServicePackage::factory(),
            'sessions_total' => 5,
            'sessions_used' => 0,
            'price_paid' => $this->faker->numberBetween(10000, 80000),
            'status' => 'pending',
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => 'active',
            'purchased_at' => now(),
            'expires_at' => now()->addDays(90),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => 'expired',
            'purchased_at' => now()->subDays(200),
            'expires_at' => now()->subDay(),
        ]);
    }

    public function exhausted(): static
    {
        return $this->state(fn (array $attributes) => [
            'sessions_used' => $attributes['sessions_total'] ?? 5,
        ]);
    }
}
