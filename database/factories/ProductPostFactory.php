<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductPostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'post_id' => fake()->unique()->numerify('############_#########'),
        ];
    }
}
