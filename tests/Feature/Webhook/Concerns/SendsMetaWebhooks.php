<?php

namespace Tests\Feature\Webhook\Concerns;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

trait SendsMetaWebhooks
{
    protected function postSignedWebhook(array $payload): TestResponse
    {
        $signature = 'sha256='.hash_hmac('sha256', json_encode($payload), 'test-app-secret');

        return $this->withHeader('X-Hub-Signature-256', $signature)->postJson('/api/webhook', $payload);
    }

    protected function sentMessages(): Collection
    {
        return Http::recorded(fn (Request $request) => $request->method() === 'POST'
            && str_contains($request->url(), '/messages'))
            ->map(fn (array $pair) => $pair[0]);
    }

    protected function assertMessagesSent(int $count): void
    {
        $this->assertCount($count, $this->sentMessages());
    }

    protected function commentPayload(string $pageId, array $value): array
    {
        return [
            'object' => 'page',
            'entry' => [[
                'id' => $pageId,
                'time' => 1758200000,
                'changes' => [[
                    'field' => 'feed',
                    'value' => array_merge([
                        'item' => 'comment',
                        'verb' => 'add',
                        'post_id' => $pageId.'_900',
                        'comment_id' => '900_'.random_int(1000, 999999),
                        'from' => ['id' => '5550001', 'name' => 'Omar'],
                        'message' => 'How much is the price?',
                    ], $value),
                ]],
            ]],
        ];
    }

    protected function messagePayload(string $pageId, array $event): array
    {
        return [
            'object' => 'page',
            'entry' => [[
                'id' => $pageId,
                'time' => 1758200000,
                'messaging' => [array_merge([
                    'sender' => ['id' => '7770001'],
                    'recipient' => ['id' => $pageId],
                    'timestamp' => 1758200000123,
                ], $event)],
            ]],
        ];
    }

    protected function textMessage(string $text, ?string $quickReplyPayload = null): array
    {
        $message = ['mid' => 'm_'.random_int(100000, 999999999), 'text' => $text];

        if ($quickReplyPayload !== null) {
            $message['quick_reply'] = ['payload' => $quickReplyPayload];
        }

        return ['message' => $message];
    }
}
