<?php

namespace App\Services\Demo;

use App\Support\BotFlows\FlowDefinition;

final class FlowBlueprint
{
    private const COLUMN_WIDTH = 340;

    private const ROW_HEIGHT = 240;

    private array $nodes = [];

    private array $edges = [];

    public function __construct(
        private readonly array $triggers,
        private readonly string $startNode,
        private readonly string $matchType = 'exact',
    ) {
    }

    public function node(string $id, string $type, float $column, float $row, array $data = []): self
    {
        $this->nodes[] = [
            'id' => $id,
            'type' => $type,
            'position' => ['x' => $column * self::COLUMN_WIDTH, 'y' => $row * self::ROW_HEIGHT],
            'data' => $data,
        ];

        return $this;
    }

    public function edge(string $source, string $handle, string $target): self
    {
        $this->edges[] = [
            'id' => 'e'.(count($this->edges) + 1),
            'source' => $source,
            'source_handle' => $handle,
            'target' => $target,
        ];

        return $this;
    }

    public function toArray(): array
    {
        return [
            'version' => FlowDefinition::CURRENT_VERSION,
            'entry' => [
                'triggers' => $this->triggers,
                'match_type' => $this->matchType,
                'start_node' => $this->startNode,
                'locale' => 'ar',
            ],
            'nodes' => $this->nodes,
            'edges' => $this->edges,
        ];
    }
}
