<?php

namespace Database\Factories;

use App\Enums\MatchType;
use App\Enums\TriggerType;
use App\Models\FacebookPage;
use Illuminate\Database\Eloquent\Factories\Factory;

class AutomationRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'facebook_page_id' => FacebookPage::factory(),
            'name' => fake()->words(3, true),
            'trigger_type' => TriggerType::Comment,
            'match_type' => MatchType::Contains,
            'keywords' => ['price', 'السعر'],
            'response_text' => fake()->sentence(),
            'private_reply_text' => null,
            'is_active' => true,
        ];
    }

    public function forMessages(): static
    {
        return $this->state(fn () => [
            'trigger_type' => TriggerType::Message,
            'private_reply_text' => null,
        ]);
    }

    public function withPrivateReply(): static
    {
        return $this->state(fn () => [
            'trigger_type' => TriggerType::Comment,
            'private_reply_text' => fake()->sentence(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
