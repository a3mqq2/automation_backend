<?php

namespace App\Support\BotFlows;

use App\Enums\FlowIssueSeverity;

final readonly class FlowIssue
{
    public function __construct(
        public FlowIssueSeverity $severity,
        public string $code,
        public ?string $nodeId = null,
        public array $params = [],
    ) {
    }

    public static function error(string $code, ?string $nodeId = null, array $params = []): self
    {
        return new self(FlowIssueSeverity::Error, $code, $nodeId, $params);
    }

    public static function warning(string $code, ?string $nodeId = null, array $params = []): self
    {
        return new self(FlowIssueSeverity::Warning, $code, $nodeId, $params);
    }

    public function message(): string
    {
        return __('flow.'.$this->code, $this->params);
    }

    public function toArray(): array
    {
        return [
            'severity' => $this->severity->value,
            'code' => $this->code,
            'node_id' => $this->nodeId,
            'message' => $this->message(),
        ];
    }
}
