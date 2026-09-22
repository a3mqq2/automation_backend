<?php

namespace Database\Factories;

use App\Models\FacebookPage;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'facebook_page_id' => FacebookPage::factory(),
            'name' => fake()->words(2, true),
            'sku' => strtoupper(fake()->bothify('SKU-####')),
            'price' => fake()->randomFloat(2, 50, 12000),
            'currency' => 'LYD',
            'description' => fake()->sentence(),
            'image_url' => 'https://cdn.example.com/'.fake()->uuid().'.jpg',
            'product_url' => 'https://store.example.com/'.fake()->slug(),
            'in_stock' => true,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => ['in_stock' => false]);
    }
}
