<?php

namespace App\Services\Automation\Engine;

use App\Jobs\HandleCommentEvent;
use App\Jobs\HandleMessagingEvent;
use App\Models\FacebookPage;

class WebhookPayloadDispatcher
{
    public function dispatch(array $payload): void
    {
        if (($payload['object'] ?? null) !== 'page') {
            return;
        }

        foreach ($this->arrayItems($payload['entry'] ?? null) as $entry) {
            $page = $this->connectedPage((string) ($entry['id'] ?? ''));

            if ($page === null) {
                continue;
            }

            foreach ($this->arrayItems($entry['changes'] ?? null) as $change) {
                if ($this->isNewComment($change)) {
                    HandleCommentEvent::dispatch($page->id, $change['value']);
                }
            }

            foreach ($this->arrayItems($entry['messaging'] ?? null) as $event) {
                if ($this->isInboundMessage($event)) {
                    HandleMessagingEvent::dispatch($page->id, $event);
                }
            }
        }
    }

    private function connectedPage(string $pageId): ?FacebookPage
    {
        if ($pageId === '') {
            return null;
        }

        return FacebookPage::query()->where('page_id', $pageId)->connected()->first();
    }

    private function isNewComment(array $change): bool
    {
        return ($change['field'] ?? null) === 'feed'
            && data_get($change, 'value.item') === 'comment'
            && data_get($change, 'value.verb') === 'add'
            && is_array($change['value'] ?? null)
            && filled(data_get($change, 'value.comment_id'));
    }

    private function isInboundMessage(array $event): bool
    {
        if (is_array($event['message'] ?? null)) {
            return data_get($event, 'message.is_echo') !== true;
        }

        return is_array($event['postback'] ?? null);
    }

    private function arrayItems(mixed $items): array
    {
        return is_array($items) ? array_filter($items, 'is_array') : [];
    }
}
