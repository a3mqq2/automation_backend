<?php

namespace Tests\Feature\Webhook;

use App\Enums\ActivityEventType;
use App\Models\ActivityLog;
use App\Models\AutomationRule;
use App\Models\BotFlow;
use App\Models\Conversation;
use App\Models\FacebookPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Webhook\Concerns\SendsMetaWebhooks;
use Tests\TestCase;

class MessagingAutomationTest extends TestCase
{
    use RefreshDatabase;
    use SendsMetaWebhooks;

    private FacebookPage $page;

    private ?array $graphError = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->page = FacebookPage::factory()->create(['page_id' => '6060', 'page_access_token' => 'page-token']);
        Http::fake(['graph.facebook.com/*' => fn () => $this->graphError === null
            ? Http::response(['recipient_id' => '7770001', 'message_id' => 'm_out'])
            : Http::response(['error' => $this->graphError], 400)]);
    }

    public function test_message_rule_sends_a_reply(): void
    {
        AutomationRule::factory()->forMessages()->create([
            'facebook_page_id' => $this->page->id,
            'keywords' => ['hours'],
            'response_text' => 'We are open 9 to 5.',
        ]);

        $this->postSignedWebhook($this->messagePayload('6060', $this->textMessage('What are your hours?')))->assertOk();

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/v23.0/6060/messages')
            && data_get($request->data(), 'recipient') === ['id' => '7770001']
            && data_get($request->data(), 'messaging_type') === 'RESPONSE'
            && data_get($request->data(), 'message') === ['text' => 'We are open 9 to 5.']);

        $this->assertSame(ActivityEventType::MessageReply, ActivityLog::query()->sole()->event_type);
        $this->assertNotNull(Conversation::query()->where('psid', '7770001')->sole()->last_message_at);
    }

    public function test_published_flows_take_priority_over_message_rules(): void
    {
        BotFlow::factory()->published()->create(['facebook_page_id' => $this->page->id]);
        AutomationRule::factory()->forMessages()->create([
            'facebook_page_id' => $this->page->id,
            'match_type' => 'any',
            'keywords' => [],
            'response_text' => 'Fallback',
        ]);

        $this->postSignedWebhook($this->messagePayload('6060', $this->textMessage('menu')));
        $this->postSignedWebhook($this->messagePayload('6060', $this->textMessage('something else')));

        $this->assertMessagesSent(2);
        Http::assertSent(fn (Request $request) => data_get($request->data(), 'message.text') === 'Welcome! How can we help?');
        Http::assertSent(fn (Request $request) => data_get($request->data(), 'message') === ['text' => 'Fallback']);
    }

    public function test_unpublished_flows_never_run(): void
    {
        BotFlow::factory()->create(['facebook_page_id' => $this->page->id]);

        $this->postSignedWebhook($this->messagePayload('6060', $this->textMessage('menu')))->assertOk();

        $this->assertMessagesSent(0);
    }

    public function test_echoes_and_duplicates_are_ignored(): void
    {
        AutomationRule::factory()->forMessages()->create(['facebook_page_id' => $this->page->id, 'match_type' => 'any', 'keywords' => []]);
        $message = $this->textMessage('hello');

        $this->postSignedWebhook($this->messagePayload('6060', $message));
        $this->postSignedWebhook($this->messagePayload('6060', $message));
        $this->postSignedWebhook($this->messagePayload('6060', [
            'sender' => ['id' => '6060'],
            'message' => ['mid' => 'm_echo', 'text' => 'hello', 'is_echo' => true],
        ]));

        $this->assertMessagesSent(1);
    }

    public function test_graph_failures_are_logged_as_failed(): void
    {
        $this->graphError = ['message' => 'Outside window', 'code' => 10];
        AutomationRule::factory()->forMessages()->create([
            'facebook_page_id' => $this->page->id,
            'match_type' => 'any',
            'keywords' => [],
            'response_text' => 'Hello',
        ]);

        $this->postSignedWebhook($this->messagePayload('6060', $this->textMessage('hi')))->assertOk();

        $log = ActivityLog::query()->sole();

        $this->assertSame('failed', $log->status->value);
        $this->assertSame(10, $log->payload['error']['code']);
    }
}
