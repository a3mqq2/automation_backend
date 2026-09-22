<?php

namespace App\Services\Automation\Engine;

use App\Support\BotFlows\FlowNode;

final readonly class FlowResolution
{
    private function __construct(
        public ?FlowNode $node,
        public ?string $retryPrompt,
        public bool $handled,
    ) {
    }

    public static function startAt(?FlowNode $node): self
    {
        return new self($node, null, $node !== null);
    }

    public static function retry(string $prompt): self
    {
        return new self(null, $prompt, true);
    }

    public static function handled(): self
    {
        return new self(null, null, true);
    }

    public static function none(): self
    {
        return new self(null, null, false);
    }
}
