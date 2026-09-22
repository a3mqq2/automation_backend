<?php

namespace Tests\Feature\Webhook;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Webhook\Concerns\SendsMetaWebhooks;
use Tests\TestCase;

class WebhookPayloadLoggingTest extends TestCase
{
    use RefreshDatabase;
    use SendsMetaWebhooks;

    public function test_payloads_are_logged_when_the_option_is_enabled(): void
    {
        config(['meta.log_webhook_payloads' => true]);
        Queue::fake();
        Log::spy();
        $payload = $this->commentPayload('123', []);

        $this->postSignedWebhook($payload)->assertOk();

        Log::shouldHaveReceived('info')->once()->withArgs(
            fn (string $message, array $context) => $message === 'Meta webhook payload received'
                && $context['payload'] === $payload,
        );
    }

    public function test_unknown_objects_are_logged_too(): void
    {
        config(['meta.log_webhook_payloads' => true]);
        Queue::fake();
        Log::spy();

        $this->postSignedWebhook(['object' => 'instagram', 'entry' => []])->assertOk();

        Log::shouldHaveReceived('info')->once();
        Queue::assertNothingPushed();
    }

    public function test_nothing_is_logged_by_default(): void
    {
        config(['meta.log_webhook_payloads' => false]);
        Queue::fake();
        Log::spy();

        $this->postSignedWebhook($this->commentPayload('123', []))->assertOk();

        Log::shouldNotHaveReceived('info');
    }
}
