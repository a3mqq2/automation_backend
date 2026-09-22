<?php

namespace Tests\Feature\Webhook;

use App\Jobs\HandleCommentEvent;
use App\Jobs\HandleMessagingEvent;
use App\Jobs\ProcessMetaWebhook;
use App\Models\FacebookPage;
use App\Services\Automation\Engine\WebhookPayloadDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Webhook\Concerns\SendsMetaWebhooks;
use Tests\TestCase;

class WebhookEndpointTest extends TestCase
{
    use RefreshDatabase;
    use SendsMetaWebhooks;

    public function test_verification_returns_the_challenge(): void
    {
        $this->get('/api/webhook?hub.mode=subscribe&hub.verify_token=test-verify-token&hub.challenge=1158201444')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
            ->assertSeeText('1158201444');
    }

    public function test_verification_rejects_a_wrong_token(): void
    {
        $this->getJson('/api/webhook?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=1158201444')
            ->assertForbidden()
            ->assertJsonPath('code', 'webhook.verification_failed');
    }

    public function test_verification_rejects_when_no_token_is_configured(): void
    {
        config(['meta.webhook_verify_token' => null]);

        $this->getJson('/api/webhook?hub.mode=subscribe&hub.verify_token=&hub.challenge=1')
            ->assertForbidden();
    }

    public function test_signed_event_is_acknowledged_and_queued_without_inline_processing(): void
    {
        Queue::fake();
        $payload = $this->commentPayload('123', []);

        $this->postSignedWebhook($payload)
            ->assertOk()
            ->assertSeeText('EVENT_RECEIVED');

        Queue::assertPushed(ProcessMetaWebhook::class, fn (ProcessMetaWebhook $job) => $job->payload === $payload);
        Queue::assertNotPushed(HandleCommentEvent::class);
    }

    public function test_non_page_objects_are_acknowledged_but_ignored(): void
    {
        Queue::fake();

        $this->postSignedWebhook(['object' => 'instagram', 'entry' => []])->assertOk();

        Queue::assertNothingPushed();
    }

    public function test_missing_or_invalid_signatures_are_rejected(): void
    {
        Queue::fake();

        $this->postJson('/api/webhook', ['object' => 'page'])
            ->assertForbidden()
            ->assertJsonPath('code', 'webhook.invalid_signature');

        $this->withHeader('X-Hub-Signature-256', 'sha256='.str_repeat('0', 64))
            ->postJson('/api/webhook', ['object' => 'page'])
            ->assertForbidden();

        $this->withHeader('X-Hub-Signature-256', 'sha1=abc')
            ->postJson('/api/webhook', ['object' => 'page'])
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_signature_is_rejected_when_the_app_secret_is_missing(): void
    {
        $payload = ['object' => 'page', 'entry' => []];
        $signature = 'sha256='.hash_hmac('sha256', json_encode($payload), '');
        config(['services.facebook.client_secret' => null]);

        $this->withHeader('X-Hub-Signature-256', $signature)
            ->postJson('/api/webhook', $payload)
            ->assertForbidden();
    }

    public function test_processing_job_dispatches_events_only_for_connected_pages(): void
    {
        Bus::fake([HandleCommentEvent::class, HandleMessagingEvent::class]);
        $connected = FacebookPage::factory()->create(['page_id' => '1001']);
        FacebookPage::factory()->disconnected()->create(['page_id' => '1002']);

        $payload = [
            'object' => 'page',
            'entry' => [
                [
                    'id' => '1001',
                    'changes' => [
                        ['field' => 'feed', 'value' => ['item' => 'comment', 'verb' => 'add', 'comment_id' => 'c1', 'message' => 'hi']],
                        ['field' => 'feed', 'value' => ['item' => 'comment', 'verb' => 'edited', 'comment_id' => 'c2']],
                        ['field' => 'feed', 'value' => ['item' => 'reaction', 'verb' => 'add']],
                    ],
                    'messaging' => [
                        ['sender' => ['id' => 'u1'], 'message' => ['mid' => 'm1', 'text' => 'hello']],
                        ['sender' => ['id' => '1001'], 'message' => ['mid' => 'm2', 'text' => 'echo', 'is_echo' => true]],
                        ['sender' => ['id' => 'u1'], 'postback' => ['payload' => 'GET_STARTED', 'title' => 'Get Started']],
                        ['sender' => ['id' => 'u1'], 'delivery' => ['mids' => ['m1']]],
                    ],
                ],
                ['id' => '1002', 'messaging' => [['sender' => ['id' => 'u2'], 'message' => ['mid' => 'm3', 'text' => 'hi']]]],
                ['id' => '9999', 'messaging' => [['sender' => ['id' => 'u3'], 'message' => ['mid' => 'm4', 'text' => 'hi']]]],
            ],
        ];

        (new ProcessMetaWebhook($payload))->handle(app(WebhookPayloadDispatcher::class));

        Bus::assertDispatchedTimes(HandleCommentEvent::class, 1);
        Bus::assertDispatched(HandleCommentEvent::class, fn (HandleCommentEvent $job) => $job->facebookPageId === $connected->id
            && $job->comment['comment_id'] === 'c1');
        Bus::assertDispatchedTimes(HandleMessagingEvent::class, 2);
        Bus::assertNotDispatched(HandleMessagingEvent::class, fn (HandleMessagingEvent $job) => $job->facebookPageId !== $connected->id);
    }
}
