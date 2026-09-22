<?php

namespace Tests\Feature\Webhook;

use App\Enums\ActivityEventType;
use App\Enums\ActivityStatus;
use App\Models\ActivityLog;
use App\Models\AutomationRule;
use App\Models\FacebookPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Webhook\Concerns\SendsMetaWebhooks;
use Tests\TestCase;

class CommentAutomationTest extends TestCase
{
    use RefreshDatabase;
    use SendsMetaWebhooks;

    private FacebookPage $page;

    private ?array $graphError = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->page = FacebookPage::factory()->create(['page_id' => '4040', 'page_access_token' => 'page-token']);
        Http::fake(['graph.facebook.com/*' => fn () => $this->graphError === null
            ? Http::response(['id' => 'reply_1'])
            : Http::response(['error' => $this->graphError], 403)]);
    }

    public function test_matching_comment_gets_a_public_and_a_private_reply(): void
    {
        $rule = AutomationRule::factory()->create([
            'facebook_page_id' => $this->page->id,
            'keywords' => ['السعر', 'price'],
            'response_text' => 'Sent you a message!',
            'private_reply_text' => 'Prices start at 10 USD.',
        ]);

        $this->postSignedWebhook($this->commentPayload('4040', ['comment_id' => '900_1', 'message' => 'كم السّعر؟']))->assertOk();

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && str_contains($request->url(), '/v23.0/900_1/comments')
            && str_contains($request->url(), 'access_token=page-token')
            && $request['message'] === 'Sent you a message!');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/v23.0/4040/messages')
            && $request['recipient'] === ['comment_id' => '900_1']
            && $request['message'] === ['text' => 'Prices start at 10 USD.']);

        $logs = ActivityLog::query()->orderBy('id')->get();

        $this->assertCount(2, $logs);
        $this->assertSame(ActivityEventType::CommentReply, $logs[0]->event_type);
        $this->assertSame(ActivityStatus::Success, $logs[0]->status);
        $this->assertSame($rule->id, $logs[0]->payload['rule_id']);
        $this->assertSame('كم السّعر؟', $logs[0]->payload['incoming_text']);
        $this->assertSame('Omar', $logs[0]->payload['sender_name']);
        $this->assertSame(ActivityEventType::PrivateReply, $logs[1]->event_type);
    }

    public function test_the_first_matching_rule_wins(): void
    {
        $first = AutomationRule::factory()->create(['facebook_page_id' => $this->page->id, 'keywords' => ['price'], 'response_text' => 'First']);
        AutomationRule::factory()->create(['facebook_page_id' => $this->page->id, 'keywords' => ['price'], 'response_text' => 'Second']);

        $this->postSignedWebhook($this->commentPayload('4040', ['message' => 'price?']));

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request['message'] === 'First');
        $this->assertSame($first->id, ActivityLog::query()->sole()->payload['rule_id']);
    }

    public function test_pages_own_comments_are_ignored(): void
    {
        AutomationRule::factory()->create(['facebook_page_id' => $this->page->id, 'match_type' => 'any', 'keywords' => []]);

        $this->postSignedWebhook($this->commentPayload('4040', ['from' => ['id' => '4040', 'name' => 'The Page']]));

        Http::assertNothingSent();
        $this->assertSame(0, ActivityLog::query()->count());
    }

    public function test_duplicate_deliveries_are_processed_once(): void
    {
        AutomationRule::factory()->create(['facebook_page_id' => $this->page->id, 'keywords' => ['price']]);
        $payload = $this->commentPayload('4040', ['comment_id' => '900_77', 'message' => 'price']);

        $this->postSignedWebhook($payload);
        $this->postSignedWebhook($payload);

        Http::assertSentCount(1);
    }

    public function test_non_matching_and_inactive_rules_do_nothing(): void
    {
        AutomationRule::factory()->create(['facebook_page_id' => $this->page->id, 'keywords' => ['delivery']]);
        AutomationRule::factory()->inactive()->create(['facebook_page_id' => $this->page->id, 'keywords' => ['price']]);
        AutomationRule::factory()->forMessages()->create(['facebook_page_id' => $this->page->id, 'keywords' => ['price']]);

        $this->postSignedWebhook($this->commentPayload('4040', ['message' => 'price please']));

        Http::assertNothingSent();
    }

    public function test_rules_of_other_pages_are_not_used(): void
    {
        AutomationRule::factory()->create(['keywords' => ['price']]);

        $this->postSignedWebhook($this->commentPayload('4040', ['message' => 'price']));

        Http::assertNothingSent();
    }

    public function test_graph_failures_are_logged_as_failed(): void
    {
        $this->graphError = ['message' => '(#10) Permission denied', 'code' => 10];
        AutomationRule::factory()->create(['facebook_page_id' => $this->page->id, 'keywords' => ['price']]);

        $this->postSignedWebhook($this->commentPayload('4040', ['message' => 'price']))->assertOk();

        $log = ActivityLog::query()->sole();

        $this->assertSame(ActivityStatus::Failed, $log->status);
        $this->assertSame(10, $log->payload['error']['code']);
        $this->assertSame('(#10) Permission denied', $log->payload['error']['message']);
    }

    public function test_disconnected_pages_are_ignored(): void
    {
        AutomationRule::factory()->create(['facebook_page_id' => $this->page->id, 'keywords' => ['price']]);
        $this->page->forceFill(['is_connected' => false])->save();

        $this->postSignedWebhook($this->commentPayload('4040', ['message' => 'price']))->assertOk();

        Http::assertNothingSent();
    }
}
