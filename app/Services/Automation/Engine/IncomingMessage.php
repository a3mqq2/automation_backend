<?php

namespace App\Services\Automation\Engine;

final readonly class IncomingMessage
{
    public function __construct(
        public string $psid,
        public string $text,
        public ?string $payload,
    ) {
    }

    public static function fromMessagingEvent(array $event): self
    {
        $payload = data_get($event, 'message.quick_reply.payload') ?? data_get($event, 'postback.payload');
        $text = data_get($event, 'message.text') ?? data_get($event, 'postback.title') ?? '';

        return new self(
            psid: (string) data_get($event, 'sender.id', ''),
            text: is_string($text) ? $text : '',
            payload: is_string($payload) ? $payload : null,
        );
    }
}
