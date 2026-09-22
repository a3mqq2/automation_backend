<?php

namespace App\Services\Automation\Engine\Sinks;

class TranscriptMessageSink implements MessageSink
{
    private array $messages = [];

    public function sendText(string $text, array $quickReplies = []): void
    {
        $this->messages[] = array_filter([
            'type' => 'text',
            'text' => $text,
            'quick_replies' => $this->labels($quickReplies),
        ]);
    }

    public function sendImage(string $imageUrl, array $quickReplies = []): void
    {
        $this->messages[] = array_filter([
            'type' => 'image',
            'image_url' => $imageUrl,
            'quick_replies' => $this->labels($quickReplies),
        ]);
    }

    public function sendButtons(string $text, array $buttons): void
    {
        $this->messages[] = [
            'type' => 'buttons',
            'text' => $text,
            'buttons' => array_map(fn (array $button) => $button['label'], $buttons),
        ];
    }

    public function sendCards(array $cards, array $quickReplies = []): void
    {
        $this->messages[] = array_filter([
            'type' => 'cards',
            'cards' => array_map(fn (array $card) => array_filter([
                'title' => $card['title'],
                'subtitle' => $card['subtitle'],
                'image_url' => $card['image_url'],
                'buttons' => array_map(fn (array $button) => $button['label'], $card['buttons']),
            ]), $cards),
            'quick_replies' => $this->labels($quickReplies),
        ]);
    }

    public function messages(): array
    {
        return $this->messages;
    }

    private function labels(array $quickReplies): array
    {
        return array_map(fn (array $quickReply) => $quickReply['label'], $quickReplies);
    }
}
