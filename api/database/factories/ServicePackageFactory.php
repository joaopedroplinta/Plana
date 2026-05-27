<?php

namespace Database\Factories;

use App\Models\ServicePackage;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ServicePackage>
 */
class ServicePackageFactory extends Factory
{
    /** @var list<string> */
    private array $packageNames = [
        'Pacote Noiva', 'Pacote Verão', 'Pacote Relaxamento', 'Pacote Beleza Completa',
        'Pacote Executiva', 'Pacote Fim de Semana', 'Pacote Aniversário',
    ];

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
            'name' => $this->faker->randomElement($this->packageNames),
            'description' => $this->faker->optional()->sentence(),
            'price' => $this->faker->numberBetween(10000, 80000),
            'sessions' => $this->faker->numberBetween(3, 10),
            'valid_days' => $this->faker->randomElement([30, 60, 90, 180]),
        ];
    }
}
