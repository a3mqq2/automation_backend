<?php

namespace App\Services\Automation\Engine\Sinks;

use App\Models\FacebookPage;
use App\Services\Meta\MessengerSendApi;

class MessengerMessageSink implements MessageSink
{
    public function __construct(
        private readonly MessengerSendApi $messenger,
        private readonly FacebookPage $page,
        private readonly string $psid,
    ) {
    }

    public function sendText(string $text, array $quickReplies = []): void
    {
        $this->messenger->sendText($this->page, $this->psid, $text, $this->toQuickReplies($quickReplies));
    }

    public function sendImage(string $imageUrl, array $quickReplies = []): void
    {
        $this->messenger->sendImage($this->page, $this->psid, $imageUrl, $this->toQuickReplies($quickReplies));
    }

    public function sendButtons(string $text, array $buttons): void
    {
        $this->messenger->sendButtonTemplate($this->page, $this->psid, $text, array_map(
            fn (array $button) => ($button['type'] ?? 'next') === 'url'
                ? ['type' => 'web_url', 'title' => $button['label'], 'url' => $button['url']]
                : ['type' => 'postback', 'title' => $button['label'], 'payload' => $button['payload']],
            $buttons,
        ));
    }

    public function sendCards(array $cards, array $quickReplies = []): void
    {
        $this->messenger->sendCards($this->page, $this->psid, array_map(fn (array $card) => array_filter([
            'title' => $card['title'],
            'subtitle' => $card['subtitle'] ?: null,
            'image_url' => $card['image_url'] ?: null,
            'buttons' => array_map(
                fn (array $button) => $button['type'] === 'url'
                    ? ['type' => 'web_url', 'title' => $button['label'], 'url' => $button['url']]
                    : ['type' => 'postback', 'title' => $button['label'], 'payload' => $button['payload']],
                $card['buttons'],
            ) ?: null,
        ], fn ($value) => $value !== null), $cards), $this->toQuickReplies($quickReplies));
    }

    private function toQuickReplies(array $quickReplies): array
    {
        return array_map(fn (array $quickReply) => [
            'content_type' => 'text',
            'title' => $quickReply['label'],
            'payload' => $quickReply['payload'],
        ], $quickReplies);
    }
}
