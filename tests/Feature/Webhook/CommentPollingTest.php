<?php

namespace Tests\Feature\Webhook;

use App\Jobs\HandleCommentEvent;
use App\Models\ActivityLog;
use App\Models\AutomationRule;
use App\Models\FacebookPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CommentPollingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['meta.comment_polling.enabled' => true]);
    }

    public function test_first_run_only_sets_the_watermark(): void
    {
        Bus::fake();
        $page = $this->pageWithCommentRule();
        Http::fake();

        $this->artisan('comments:poll')->assertSuccessful();

        Http::assertNothingSent();
        Bus::assertNothingDispatched();
        $this->assertNotNull($page->refresh()->comments_polled_at);
    }

    public function test_new_comments_are_dispatched_to_the_automation_engine(): void
    {
        Bus::fake();
        $page = $this->pageWithCommentRule(polledAt: now()->subMinutes(5));
        $this->fakeFeed([
            $this->comment('900_1', 'بكم السعر؟', now()->subMinute()),
            $this->comment('900_2', 'قديم', now()->subMinutes(30)),
        ]);

        $this->artisan('comments:poll')->assertSuccessful();

        Bus::assertDispatchedTimes(HandleCommentEvent::class, 1);
        Bus::assertDispatched(HandleCommentEvent::class, fn (HandleCommentEvent $job) => $job->facebookPageId === $page->id
            && $job->comment['comment_id'] === '900_1'
            && $job->comment['message'] === 'بكم السعر؟'
            && $job->comment['from']['id'] === '5550001'
            && $job->comment['post_id'] === '103_900');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/103673872192331/feed')
            && str_contains(urldecode($request->url()), 'comments.limit(25).order(reverse_chronological){id,created_time,message,from}')
            && str_contains(urldecode($request->url()), 'limit=10'));
    }

    public function test_polling_runs_the_full_automation_chain(): void
    {
        $page = $this->pageWithCommentRule(polledAt: now()->subMinutes(5));
        Http::fake([
            'graph.facebook.com/v23.0/*/feed*' => Http::response(['data' => [[
                'id' => '103_900',
                'comments' => ['data' => [$this->comment('900_7', 'كم السعر؟', now()->subMinute())]],
            ]]]),
            'graph.facebook.com/v23.0/900_7/comments*' => Http::response(['id' => 'reply_1']),
        ]);

        $this->artisan('comments:poll')->assertSuccessful();

        $log = ActivityLog::query()->sole();

        $this->assertSame('comment_reply', $log->event_type->value);
        $this->assertSame('success', $log->status->value);
        $this->assertSame('900_7', $log->payload['comment_id']);
        $this->assertSame($page->id, $log->facebook_page_id);
    }

    public function test_the_same_comment_is_never_answered_twice(): void
    {
        $this->pageWithCommentRule(polledAt: now()->subMinutes(5));
        Http::fake([
            'graph.facebook.com/v23.0/*/feed*' => Http::response(['data' => [[
                'id' => '103_900',
                'comments' => ['data' => [$this->comment('900_9', 'السعر', now()->subSeconds(10))]],
            ]]]),
            'graph.facebook.com/v23.0/900_9/comments*' => Http::response(['id' => 'reply_1']),
        ]);

        $this->artisan('comments:poll');
        $this->artisan('comments:poll');

        $this->assertSame(1, ActivityLog::query()->count());
    }

    public function test_pages_without_active_comment_rules_are_skipped(): void
    {
        Bus::fake();
        FacebookPage::factory()->create();
        $inactiveRulePage = FacebookPage::factory()->create();
        AutomationRule::factory()->inactive()->create(['facebook_page_id' => $inactiveRulePage->id]);
        $messageRulePage = FacebookPage::factory()->create();
        AutomationRule::factory()->forMessages()->create(['facebook_page_id' => $messageRulePage->id]);
        Http::fake();

        $this->artisan('comments:poll')->expectsOutputToContain('No connected page has an active comment rule.');

        Http::assertNothingSent();
    }

    public function test_disconnected_pages_are_skipped(): void
    {
        $page = FacebookPage::factory()->disconnected()->create();
        AutomationRule::factory()->create(['facebook_page_id' => $page->id]);
        Http::fake();

        $this->artisan('comments:poll')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_a_graph_failure_on_one_page_does_not_stop_the_others(): void
    {
        Bus::fake();
        $failing = $this->pageWithCommentRule(pageId: '111', polledAt: now()->subMinutes(5));
        $working = $this->pageWithCommentRule(pageId: '222', polledAt: now()->subMinutes(5));
        Http::fake([
            'graph.facebook.com/v23.0/111/feed*' => Http::response(['error' => ['message' => 'Invalid token', 'code' => 190]], 400),
            'graph.facebook.com/v23.0/222/feed*' => Http::response(['data' => [[
                'id' => '222_900',
                'comments' => ['data' => [$this->comment('900_3', 'السعر', now()->subSeconds(30))]],
            ]]]),
        ]);

        $this->artisan('comments:poll')->assertSuccessful();

        Bus::assertDispatchedTimes(HandleCommentEvent::class, 1);
        Bus::assertDispatched(HandleCommentEvent::class, fn (HandleCommentEvent $job) => $job->facebookPageId === $working->id);
        $this->assertNull($failing->refresh()->comments_polled_at?->isToday() ? null : true);
    }

    public function test_comments_without_an_author_are_ignored(): void
    {
        Bus::fake();
        $this->pageWithCommentRule(polledAt: now()->subMinutes(5));
        Http::fake(['graph.facebook.com/v23.0/*/feed*' => Http::response(['data' => [[
            'id' => '103_900',
            'comments' => ['data' => [[
                'id' => '900_4',
                'message' => 'السعر',
                'created_time' => now()->subMinute()->toIso8601String(),
            ]]],
        ]]])]);

        $this->artisan('comments:poll')->assertSuccessful();

        Bus::assertNothingDispatched();
    }

    public function test_polling_is_disabled_by_default(): void
    {
        config(['meta.comment_polling.enabled' => false]);
        $this->pageWithCommentRule(polledAt: now()->subMinutes(5));
        Http::fake();

        $this->artisan('comments:poll')->expectsOutputToContain('Comment polling is disabled.');
        Http::assertNothingSent();

        $this->artisan('comments:poll', ['--force' => true])->assertSuccessful();
    }

    private function pageWithCommentRule(string $pageId = '103673872192331', $polledAt = null): FacebookPage
    {
        $page = FacebookPage::factory()->create([
            'page_id' => $pageId,
            'page_access_token' => 'page-token',
            'comments_polled_at' => $polledAt,
        ]);

        AutomationRule::factory()->create([
            'facebook_page_id' => $page->id,
            'keywords' => ['السعر'],
            'response_text' => 'أرسلنا لك التفاصيل',
            'private_reply_text' => null,
        ]);

        return $page;
    }

    private function comment(string $id, string $message, $createdAt): array
    {
        return [
            'id' => $id,
            'created_time' => $createdAt->toIso8601String(),
            'message' => $message,
            'from' => ['id' => '5550001', 'name' => 'Omar'],
        ];
    }

    private function fakeFeed(array $comments): void
    {
        Http::fake(['graph.facebook.com/v23.0/*/feed*' => Http::response([
            'data' => [['id' => '103_900', 'comments' => ['data' => $comments]]],
        ])]);
    }
}
