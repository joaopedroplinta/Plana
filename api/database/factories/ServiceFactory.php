<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /** @var list<string> */
    private array $serviceNames = [
        'Corte Feminino', 'Corte Masculino', 'Coloração', 'Mechas', 'Escova',
        'Progressiva', 'Hidratação', 'Manicure', 'Pedicure', 'Sobrancelha',
        'Depilação Perna Inteira', 'Massagem Relaxante', 'Limpeza de Pele',
        'Design de Sobrancelha', 'Penteado', 'Luzes', 'Balayage',
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
            'name' => $this->faker->randomElement($this->serviceNames),
            'description' => $this->faker->optional()->sentence(),
            'price' => $this->faker->numberBetween(2000, 30000),
            'duration_minutes' => $this->faker->randomElement([30, 45, 60, 90, 120]),
            'image_url' => null,
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['active' => false]);
    }
}
