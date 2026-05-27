<?php

namespace Database\Factories;

use App\Models\BlockedDate;
use App\Models\Professional;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BlockedDate>
 */
class BlockedDateFactory extends Factory
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
            'professional_id' => Professional::factory(),
            'date' => $this->faker->dateTimeBetween('now', '+3 months')->format('Y-m-d'),
            'reason' => $this->faker->optional()->randomElement(['Férias', 'Feriado', 'Evento', 'Indisponível']),
        ];
    }
}
