<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
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
            'appointment_id' => Appointment::factory(),
            'amount' => fake()->numberBetween(2000, 30000),
            'method' => fake()->randomElement(['pix', 'credit_card']),
            'external_id' => fake()->numerify('##########'),
            'status' => 'pending',
        ];
    }

    public function approved(): static
    {
        return $this->state(['status' => 'approved', 'paid_at' => now()]);
    }

    public function pix(): static
    {
        return $this->state([
            'method' => 'pix',
            'external_id' => fake()->numerify('##########'),
            'pix_qr_code' => '00020126580014br.gov.bcb.pix0136'.fake()->uuid(),
            'pix_qr_code_base64' => base64_encode('fake-qr-image'),
        ]);
    }

    public function creditCard(): static
    {
        return $this->state([
            'method' => 'credit_card',
            'preference_id' => fake()->uuid(),
            'external_id' => null,
        ]);
    }
}
