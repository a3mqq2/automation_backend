<?php

namespace Database\Factories;

use App\Models\BotFlow;
use App\Models\FacebookPage;
use App\Support\BotFlows\FlowDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

class BotFlowFactory extends Factory
{
    public function definition(): array
    {
        return [
            'facebook_page_id' => FacebookPage::factory(),
            'name' => fake()->words(2, true),
            'flow_json' => self::sampleDefinition(),
            'is_active' => true,
        ];
    }

    public static function sampleDefinition(): array
    {
        return [
            'version' => FlowDefinition::CURRENT_VERSION,
            'entry' => [
                'triggers' => ['menu', 'القائمة'],
                'match_type' => 'exact',
                'start_node' => 'welcome',
            ],
            'nodes' => [
                [
                    'id' => 'welcome',
                    'type' => 'quick_replies',
                    'position' => ['x' => 0, 'y' => 0],
                    'data' => [
                        'text' => 'Welcome! How can we help?',
                        'options' => [
                            ['id' => 'o1', 'label' => 'Prices'],
                            ['id' => 'o2', 'label' => 'Location'],
                        ],
                    ],
                ],
                [
                    'id' => 'prices',
                    'type' => 'message',
                    'position' => ['x' => 0, 'y' => 200],
                    'data' => ['text' => 'Our prices start from 10 USD.'],
                ],
                [
                    'id' => 'location',
                    'type' => 'message',
                    'position' => ['x' => 320, 'y' => 200],
                    'data' => ['text' => 'We are located downtown.'],
                ],
                [
                    'id' => 'done',
                    'type' => 'end',
                    'position' => ['x' => 160, 'y' => 400],
                    'data' => [],
                ],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'welcome', 'source_handle' => 'o1', 'target' => 'prices'],
                ['id' => 'e2', 'source' => 'welcome', 'source_handle' => 'o2', 'target' => 'location'],
                ['id' => 'e3', 'source' => 'prices', 'source_handle' => 'next', 'target' => 'done'],
                ['id' => 'e4', 'source' => 'location', 'source_handle' => 'next', 'target' => 'done'],
            ],
        ];
    }

    public function published(): static
    {
        return $this->afterCreating(function (BotFlow $flow): void {
            $version = $flow->versions()->create([
                'version' => ((int) $flow->versions()->max('version')) + 1,
                'definition' => $flow->draftDefinition()->toArray(),
                'published_at' => now(),
            ]);

            $flow->forceFill(['published_version_id' => $version->id])->save();
        });
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
