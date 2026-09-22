<?php

namespace App\Support\BotFlows;

use App\Enums\FlowNodeType;

final readonly class FlowEdge
{
    public function __construct(
        public string $id,
        public string $source,
        public string $sourceHandle,
        public string $target,
    ) {
    }

    public static function fromArray(array $edge): ?self
    {
        $source = (string) ($edge['source'] ?? '');
        $target = (string) ($edge['target'] ?? '');

        if ($source === '' || $target === '') {
            return null;
        }

        $sourceHandle = (string) ($edge['source_handle'] ?? FlowNodeType::NEXT_HANDLE);

        return new self(
            id: (string) ($edge['id'] ?? $source.'-'.$sourceHandle.'-'.$target),
            source: $source,
            sourceHandle: $sourceHandle === '' ? FlowNodeType::NEXT_HANDLE : $sourceHandle,
            target: $target,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'source_handle' => $this->sourceHandle,
            'target' => $this->target,
        ];
    }
}
