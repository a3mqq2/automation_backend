<?php

namespace Database\Factories;

use App\Models\FacebookPage;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'facebook_page_id' => FacebookPage::factory(),
            'name' => fake()->randomElement(['إلكترونيات', 'أثاث', 'ملابس', 'مستلزمات المنزل']),
            'image_url' => 'https://cdn.example.com/'.fake()->uuid().'.jpg',
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
