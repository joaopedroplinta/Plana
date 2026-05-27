<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    /**
     * Realistic salon name prefixes for pt_BR.
     *
     * @var list<string>
     */
    private array $salonPrefixes = [
        'Salão', 'Studio', 'Espaço', 'Barbearia', 'Clínica de Beleza',
        'Ateliê', 'Beauty Studio', 'Hair Lab',
    ];

    /**
     * Realistic salon name suffixes for pt_BR.
     *
     * @var list<string>
     */
    private array $salonSuffixes = [
        'Elegance', 'Glamour', 'Charme', 'Arte', 'Bela', 'Morana',
        'Serenity', 'Luxe', 'Prestige', 'Gold', 'Prime',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $prefix = $this->faker->randomElement($this->salonPrefixes);
        $suffix = $this->faker->randomElement($this->salonSuffixes);
        $name = "{$prefix} {$suffix}";
        $slug = Str::slug($name).'-'.$this->faker->unique()->numberBetween(1, 9999);

        return [
            'id' => (string) Str::uuid(),
            'name' => $name,
            'slug' => $slug,
            'plan' => $this->faker->randomElement(['starter', 'pro', 'enterprise']),
            'settings' => [],
            'active' => true,
            'trial_ends_at' => null,
        ];
    }

    /**
     * State for an inactive tenant.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }

    /**
     * State for a tenant on trial.
     */
    public function onTrial(): static
    {
        return $this->state(fn (array $attributes) => [
            'trial_ends_at' => now()->addDays(14),
        ]);
    }
}
