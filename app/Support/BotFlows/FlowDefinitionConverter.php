<?php

namespace App\Support\BotFlows;

use App\Enums\FlowNodeType;

final class FlowDefinitionConverter
{
    private const HORIZONTAL_SPACING = 320;

    private const VERTICAL_SPACING = 200;

    public static function toCurrentVersion(array $definition): array
    {
        return self::isLegacy($definition) ? self::fromLegacySteps($definition) : $definition;
    }

    public static function isLegacy(array $definition): bool
    {
        if (isset($definition['nodes']) || isset($definition['entry'])) {
            return false;
        }

        return isset($definition['steps']) || isset($definition['start_step']);
    }

    private static function fromLegacySteps(array $definition): array
    {
        $steps = array_values(array_filter((array) ($definition['steps'] ?? []), 'is_array'));
        $nodes = [];
        $edges = [];

        foreach ($steps as $index => $step) {
            $stepId = (string) ($step['id'] ?? '');

            if ($stepId === '') {
                continue;
            }

            $options = array_values(array_filter((array) ($step['options'] ?? []), 'is_array'));
            $nodes[] = [
                'id' => $stepId,
                'type' => ($options === [] ? FlowNodeType::Message : FlowNodeType::QuickReplies)->value,
                'position' => ['x' => 0.0, 'y' => (float) ($index * self::VERTICAL_SPACING)],
                'data' => array_filter([
                    'text' => (string) ($step['text'] ?? ''),
                    'image_url' => $step['image_url'] ?? null,
                    'options' => $options === [] ? null : array_map(
                        fn (array $option, int $optionIndex) => [
                            'id' => 'o'.($optionIndex + 1),
                            'label' => (string) ($option['label'] ?? ''),
                        ],
                        $options,
                        array_keys($options),
                    ),
                ], fn ($value) => $value !== null),
            ];

            foreach ($options as $optionIndex => $option) {
                $target = (string) ($option['next_step'] ?? '');

                if ($target !== '') {
                    $edges[] = [
                        'id' => $stepId.'-o'.($optionIndex + 1),
                        'source' => $stepId,
                        'source_handle' => 'o'.($optionIndex + 1),
                        'target' => $target,
                    ];
                }
            }
        }

        return [
            'version' => FlowDefinition::CURRENT_VERSION,
            'entry' => [
                'triggers' => array_values((array) ($definition['triggers'] ?? [])),
                'match_type' => (string) ($definition['match_type'] ?? 'exact'),
                'start_node' => (string) ($definition['start_step'] ?? ''),
            ],
            'nodes' => self::layout($nodes, $edges),
            'edges' => $edges,
        ];
    }

    private static function layout(array $nodes, array $edges): array
    {
        $depths = [];
        $children = [];

        foreach ($edges as $edge) {
            $children[$edge['source']][] = $edge['target'];
        }

        foreach ($nodes as $node) {
            $depths[$node['id']] = 0;
        }

        foreach ($nodes as $node) {
            foreach ($children[$node['id']] ?? [] as $child) {
                if (isset($depths[$child])) {
                    $depths[$child] = max($depths[$child], $depths[$node['id']] + 1);
                }
            }
        }

        $columnCounters = [];

        return array_map(function (array $node) use ($depths, &$columnCounters) {
            $depth = $depths[$node['id']] ?? 0;
            $column = $columnCounters[$depth] ?? 0;
            $columnCounters[$depth] = $column + 1;

            $node['position'] = [
                'x' => (float) ($column * self::HORIZONTAL_SPACING),
                'y' => (float) ($depth * self::VERTICAL_SPACING),
            ];

            return $node;
        }, $nodes);
    }
}
