<?php

namespace App\Jobs;

use App\Services\Automation\Engine\WebhookPayloadDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessMetaWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly array $payload)
    {
    }

    public function handle(WebhookPayloadDispatcher $dispatcher): void
    {
        $dispatcher->dispatch($this->payload);
    }
}
