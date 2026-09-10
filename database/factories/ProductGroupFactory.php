<?php

namespace Database\Factories;

use App\Models\ProductGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductGroup> */
class ProductGroupFactory extends Factory
{
    protected $model = ProductGroup::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['name' => fake()->words(2, true), 'slug' => fake()->unique()->slug(), 'sort_order' => 0];
    }
}
