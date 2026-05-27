<?php

namespace Database\Factories;

use App\Models\Professional;
use App\Models\Schedule;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startHour = $this->faker->numberBetween(7, 11);

        return [
            'id' => (string) Str::uuid(),
            'tenant_id' => Tenant::factory(),
            'professional_id' => Professional::factory(),
            'day_of_week' => $this->faker->numberBetween(0, 6),
            'start_time' => sprintf('%02d:00:00', $startHour),
            'end_time' => sprintf('%02d:00:00', $startHour + 8),
        ];
    }
}
