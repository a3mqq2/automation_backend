<?php

namespace Tests\Feature\Webhook;

use App\Models\BotFlow;
use App\Models\Conversation;
use App\Models\FacebookPage;
use App\Services\Automation\BotFlowPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Webhook\Concerns\SendsMetaWebhooks;
use Tests\TestCase;

class FlowCardsTest extends TestCase
{
    use RefreshDatabase;
    use SendsMetaWebhooks;

    private FacebookPage $page;

    protected function setUp(): void
    {
        parent::setUp();

        $this->page = FacebookPage::factory()->create(['page_id' => '6060', 'page_access_token' => 'page-token']);
        Http::fake([
            'graph.facebook.com/v23.0/7770001*' => Http::response(['first_name' => 'Aisha', 'last_name' => 'Altiri']),
            'graph.facebook.com/*' => Http::response(['message_id' => 'm_out']),
        ]);
    }

    public function test_a_cards_node_sends_a_generic_template_carousel(): void
    {
        $flow = $this->publish($this->catalogDefinition());

        $this->postSignedWebhook($this->messagePayload('6060', $this->textMessage('PlayStation 5')))->assertOk();

        Http::assertSent(function (Request $request) use ($flow) {
            $payload = data_get($request->data(), 'message.attachment.payload');

            return $payload !== null
                && $payload['template_type'] === 'generic'
                && $payload['elements'] === [
                    [
                        'title' => 'PlayStation 5 Pro',
                        'subtitle' => 'السعر = 11300 د.ل',
                        'image_url' => 'https://cdn.example.com/ps5-pro.jpg',
                        'buttons' => [
                            ['type' => 'web_url', 'title' => 'AG Store', 'url' => 'https://store.example.com/ps5-pro'],
                            ['type' => 'postback', 'title' => 'اطلبها الان', 'payload' => "FLOW:{$flow->id}:catalog:order_pro"],
                        ],
                    ],
                    [
                        'title' => 'PlayStation 5 Slim',
                        'subtitle' => 'السعر = 7590 د.ل',
                        'image_url' => 'https://cdn.example.com/ps5-slim.jpg',
                        'buttons' => [
                            ['type' => 'postback', 'title' => 'اطلبها الان', 'payload' => "FLOW:{$flow->id}:catalog:order_slim"],
                        ],
                    ],
                ]
                && data_get($request->data(), 'message.quick_replies') === [
                    ['content_type' => 'text', 'title' => 'العاب PS5', 'payload' => "FLOW:{$flow->id}:catalog:games"],
                ];
        });

        $conversation = Conversation::query()->sole();

        $this->assertSame('catalog', $conversation->current_node_id);
        $this->assertSame('choice', $conversation->awaitingKind());
    }

    public function test_pressing_a_card_button_continues_the_flow(): void
    {
        $flow = $this->publish($this->catalogDefinition());
        $this->postSignedWebhook($this->messagePayload('6060', $this->textMessage('PlayStation 5')));

        $this->postSignedWebhook($this->messagePayload('6060', [
            'postback' => ['mid' => 'pb_1', 'title' => 'اطلبها الان', 'payload' => "FLOW:{$flow->id}:catalog:order_pro"],
        ]));

        Http::assertSent(fn (Request $request) => data_get($request->data(), 'message.text') === 'ممتاز! أرسل لنا عنوانك لإتمام طلب PlayStation 5 Pro.');
    }

    public function test_typing_a_card_button_label_also_continues(): void
    {
        $flow = $this->publish($this->catalogDefinition());
        $this->postSignedWebhook($this->messagePayload('6060', $this->textMessage('PlayStation 5')));

        $this->postSignedWebhook($this->messagePayload('6060', $this->textMessage('العاب PS5')));

        Http::assertSent(fn (Request $request) => data_get($request->data(), 'message.text') === 'هذه أحدث الألعاب لدينا.');
    }

    public function test_card_text_is_personalised_with_the_customer_name(): void
    {
        $definition = $this->catalogDefinition();
        array_unshift($definition['nodes'], [
            'id' => 'intro',
            'type' => 'message',
            'position' => ['x' => 0, 'y' => -200],
            'data' => ['text' => 'هل أنت جاهز للحصريات {{first_name}}؟'],
        ]);
        $definition['entry']['start_node'] = 'intro';
        $definition['edges'][] = ['id' => 'e_intro', 'source' => 'intro', 'source_handle' => 'next', 'target' => 'catalog'];
        $this->publish($definition);

        $this->postSignedWebhook($this->messagePayload('6060', $this->textMessage('PlayStation 5')));

        Http::assertSent(fn (Request $request) => data_get($request->data(), 'message.text') === 'هل أنت جاهز للحصريات Aisha؟');
        $this->assertSame('Aisha', Conversation::query()->sole()->variables['first_name']);
    }

    public function test_the_profile_is_fetched_once_per_customer(): void
    {
        $this->publish($this->catalogDefinition());

        $this->postSignedWebhook($this->messagePayload('6060', $this->textMessage('PlayStation 5')));
        $this->postSignedWebhook($this->messagePayload('6060', $this->textMessage('العاب PS5')));

        $profileCalls = Http::recorded(fn (Request $request) => $request->method() === 'GET'
            && str_contains($request->url(), '/v23.0/7770001'));

        $this->assertCount(1, $profileCalls);
    }

    public function test_card_limits_are_validated(): void
    {
        $client = $this->actingAsClient();
        $page = FacebookPage::factory()->for($client)->create();
        $definition = $this->catalogDefinition();
        $definition['nodes'][0]['data']['elements'][0]['title'] = str_repeat('a', 81);
        $definition['nodes'][0]['data']['elements'][0]['buttons'][] = ['id' => 'x1', 'label' => 'ثالث', 'type' => 'next'];
        $definition['nodes'][0]['data']['elements'][0]['buttons'][] = ['id' => 'x2', 'label' => 'رابع', 'type' => 'next'];

        $flowId = $this->postJson('/api/bot-flows', [
            'facebook_page_id' => $page->id,
            'name' => 'كتالوج',
            'flow_json' => $definition,
        ])->assertCreated()->json('data.id');

        $codes = array_column($this->getJson("/api/bot-flows/{$flowId}")->json('data.issues'), 'code');

        $this->assertContains('invalid_card_title', $codes);
        $this->assertContains('too_many_buttons', $codes);
        $this->assertContains('unconnected_choice', $codes);
    }

    private function publish(array $definition): BotFlow
    {
        $flow = BotFlow::factory()->create([
            'facebook_page_id' => $this->page->id,
            'name' => 'كتالوج المنتجات',
            'flow_json' => $definition,
        ]);

        app(BotFlowPublisher::class)->publish($flow);

        return $flow->refresh();
    }

    private function catalogDefinition(): array
    {
        return [
            'version' => 2,
            'entry' => ['triggers' => ['PlayStation 5'], 'match_type' => 'exact', 'start_node' => 'catalog'],
            'nodes' => [
                [
                    'id' => 'catalog',
                    'type' => 'cards',
                    'position' => ['x' => 0, 'y' => 0],
                    'data' => [
                        'elements' => [
                            [
                                'id' => 'c1',
                                'title' => 'PlayStation 5 Pro',
                                'subtitle' => 'السعر = 11300 د.ل',
                                'image_url' => 'https://cdn.example.com/ps5-pro.jpg',
                                'buttons' => [
                                    ['id' => 'store_pro', 'label' => 'AG Store', 'type' => 'url', 'url' => 'https://store.example.com/ps5-pro'],
                                    ['id' => 'order_pro', 'label' => 'اطلبها الان', 'type' => 'next'],
                                ],
                            ],
                            [
                                'id' => 'c2',
                                'title' => 'PlayStation 5 Slim',
                                'subtitle' => 'السعر = 7590 د.ل',
                                'image_url' => 'https://cdn.example.com/ps5-slim.jpg',
                                'buttons' => [
                                    ['id' => 'order_slim', 'label' => 'اطلبها الان', 'type' => 'next'],
                                ],
                            ],
                        ],
                        'options' => [
                            ['id' => 'games', 'label' => 'العاب PS5'],
                        ],
                    ],
                ],
                [
                    'id' => 'order_pro_step',
                    'type' => 'message',
                    'position' => ['x' => 0, 'y' => 240],
                    'data' => ['text' => 'ممتاز! أرسل لنا عنوانك لإتمام طلب PlayStation 5 Pro.'],
                ],
                [
                    'id' => 'order_slim_step',
                    'type' => 'message',
                    'position' => ['x' => 320, 'y' => 240],
                    'data' => ['text' => 'ممتاز! أرسل لنا عنوانك لإتمام طلب PlayStation 5 Slim.'],
                ],
                [
                    'id' => 'games_step',
                    'type' => 'message',
                    'position' => ['x' => 640, 'y' => 240],
                    'data' => ['text' => 'هذه أحدث الألعاب لدينا.'],
                ],
                ['id' => 'done', 'type' => 'end', 'position' => ['x' => 320, 'y' => 480], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'catalog', 'source_handle' => 'order_pro', 'target' => 'order_pro_step'],
                ['id' => 'e2', 'source' => 'catalog', 'source_handle' => 'order_slim', 'target' => 'order_slim_step'],
                ['id' => 'e3', 'source' => 'catalog', 'source_handle' => 'games', 'target' => 'games_step'],
                ['id' => 'e4', 'source' => 'order_pro_step', 'source_handle' => 'next', 'target' => 'done'],
                ['id' => 'e5', 'source' => 'order_slim_step', 'source_handle' => 'next', 'target' => 'done'],
                ['id' => 'e6', 'source' => 'games_step', 'source_handle' => 'next', 'target' => 'done'],
            ],
        ];
    }
}
