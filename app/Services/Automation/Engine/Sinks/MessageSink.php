<?php

namespace App\Services\Automation\Engine\Sinks;

interface MessageSink
{
    public function sendText(string $text, array $quickReplies = []): void;

    public function sendImage(string $imageUrl, array $quickReplies = []): void;

    public function sendButtons(string $text, array $buttons): void;

    public function sendCards(array $cards, array $quickReplies = []): void;
}
