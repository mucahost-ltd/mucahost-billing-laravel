<?php

namespace Database\Factories;

use App\Models\ProductPricing;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductPricing> */
class ProductPricingFactory extends Factory
{
    protected $model = ProductPricing::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['product_id' => ProductFactory::new(), 'currency_id' => CurrencyFactory::new(), 'billing_cycle' => 'monthly', 'price' => '10.10', 'setup_fee' => '2.20'];
    }
}
