<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_number' => 'INV-'.fake()->unique()->numerify('######'),
            'client_id' => Client::factory(),
            'status' => 'unpaid',
            'subtotal' => fake()->randomFloat(2, 10, 1000),
            'tax' => 0,
            'total' => fn (array $attributes): float => (float) $attributes['subtotal'],
            'currency_id' => Currency::factory(),
            'due_date' => now()->addDays(14),
            'paid_at' => null,
            'payment_method' => null,
        ];
    }
}
