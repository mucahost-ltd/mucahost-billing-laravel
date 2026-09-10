<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['product_group_id' => ProductGroupFactory::new(), 'name' => 'Starter Hosting', 'slug' => fake()->unique()->slug(), 'type' => 'shared_hosting', 'module' => 'enhance', 'module_settings' => ['plan_id' => 7], 'is_active' => true];
    }
}
