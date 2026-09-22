<?php

namespace Database\Factories;

use App\Models\FacebookPage;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConversationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'facebook_page_id' => FacebookPage::factory(),
            'psid' => (string) fake()->unique()->numerify('2###############'),
            'bot_flow_id' => null,
            'bot_flow_version_id' => null,
            'current_node_id' => null,
            'awaiting' => null,
            'variables' => null,
            'last_message_at' => now(),
        ];
    }

    public function awaitingChoice(int $flowId, int $versionId, string $nodeId): static
    {
        return $this->state(fn () => [
            'bot_flow_id' => $flowId,
            'bot_flow_version_id' => $versionId,
            'current_node_id' => $nodeId,
            'awaiting' => ['kind' => 'choice'],
        ]);
    }

    public function awaitingInput(int $flowId, int $versionId, string $nodeId, string $variable, string $expects = 'text', int $retriesLeft = 1): static
    {
        return $this->state(fn () => [
            'bot_flow_id' => $flowId,
            'bot_flow_version_id' => $versionId,
            'current_node_id' => $nodeId,
            'awaiting' => [
                'kind' => 'input',
                'variable' => $variable,
                'expects' => $expects,
                'retries_left' => $retriesLeft,
            ],
        ]);
    }
}
