<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FacebookPageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'page_id' => (string) fake()->unique()->numerify('1#############'),
            'name' => fake()->company(),
            'category' => fake()->randomElement(['Shopping & retail', 'Restaurant', 'Education']),
            'picture_url' => fake()->imageUrl(),
            'page_access_token' => 'EAAP'.Str::random(60),
            'is_connected' => true,
            'connected_at' => now(),
        ];
    }

    public function disconnected(): static
    {
        return $this->state(fn () => [
            'is_connected' => false,
            'page_access_token' => null,
        ]);
    }
}
