<?php

namespace App\Support\BotFlows;

final readonly class FlowDefinition
{
    public const CURRENT_VERSION = 2;

    public function __construct(
        public FlowEntry $entry,
        public array $nodes,
        public array $edges,
    ) {
    }

    public static function fromArray(array $definition): self
    {
        $definition = FlowDefinitionConverter::toCurrentVersion($definition);
        $nodes = [];

        foreach ((array) ($definition['nodes'] ?? []) as $node) {
            $flowNode = is_array($node) ? FlowNode::fromArray($node) : null;

            if ($flowNode !== null) {
                $nodes[$flowNode->id] = $flowNode;
            }
        }

        $edges = [];

        foreach ((array) ($definition['edges'] ?? []) as $edge) {
            $flowEdge = is_array($edge) ? FlowEdge::fromArray($edge) : null;

            if ($flowEdge !== null) {
                $edges[] = $flowEdge;
            }
        }

        return new self(
            entry: FlowEntry::fromArray((array) ($definition['entry'] ?? [])),
            nodes: $nodes,
            edges: $edges,
        );
    }

    public function node(?string $nodeId): ?FlowNode
    {
        return $nodeId === null ? null : ($this->nodes[$nodeId] ?? null);
    }

    public function startNode(): ?FlowNode
    {
        return $this->node($this->entry->startNodeId);
    }

    public function targetOf(string $nodeId, string $handle): ?FlowNode
    {
        foreach ($this->edges as $edge) {
            if ($edge->source === $nodeId && $edge->sourceHandle === $handle) {
                return $this->node($edge->target);
            }
        }

        return null;
    }

    public function outgoingHandles(string $nodeId): array
    {
        $handles = [];

        foreach ($this->edges as $edge) {
            if ($edge->source === $nodeId) {
                $handles[] = $edge->sourceHandle;
            }
        }

        return $handles;
    }

    public function toArray(): array
    {
        return [
            'version' => self::CURRENT_VERSION,
            'entry' => $this->entry->toArray(),
            'nodes' => array_values(array_map(fn (FlowNode $node) => $node->toArray(), $this->nodes)),
            'edges' => array_values(array_map(fn (FlowEdge $edge) => $edge->toArray(), $this->edges)),
        ];
    }
}
