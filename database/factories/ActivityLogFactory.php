<?php

namespace Database\Factories;

use App\Enums\ActivityEventType;
use App\Enums\ActivityStatus;
use App\Models\FacebookPage;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActivityLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'facebook_page_id' => FacebookPage::factory(),
            'event_type' => ActivityEventType::CommentReply,
            'status' => ActivityStatus::Success,
            'payload' => [
                'comment_id' => fake()->numerify('#########_#########'),
                'incoming_text' => fake()->sentence(),
                'response_text' => fake()->sentence(),
            ],
        ];
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => ActivityStatus::Failed,
        ]);
    }
}
