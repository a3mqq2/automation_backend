<?php

namespace App\Services\Pages;

use App\Exceptions\MetaGraphException;
use App\Services\Meta\MetaGraphClient;

class PageWebhookSubscriber
{
    public function __construct(private readonly MetaGraphClient $graph)
    {
    }

    public function subscribe(string $pageId, string $pageAccessToken): void
    {
        $this->graph->post("{$pageId}/subscribed_apps", $pageAccessToken, [
            'subscribed_fields' => implode(',', config('meta.page_subscribed_fields')),
        ]);
    }

    public function unsubscribe(string $pageId, ?string $pageAccessToken): void
    {
        if ($pageAccessToken === null || $pageAccessToken === '') {
            return;
        }

        try {
            $this->graph->delete("{$pageId}/subscribed_apps", $pageAccessToken);
        } catch (MetaGraphException $exception) {
            report($exception);
        }
    }
}
