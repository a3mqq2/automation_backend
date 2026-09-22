<?php

namespace App\Support\BotFlows;

use App\Enums\CatalogSource;
use App\Enums\FlowConditionOperator;
use App\Enums\FlowInputExpectation;
use App\Enums\FlowNodeType;
use App\Enums\MatchType;

class FlowDefinitionValidator
{
    public const MAX_NODES = 100;

    public const MAX_TRIGGERS = 20;

    public const MAX_TEXT_LENGTH = 2000;

    public const MAX_QUICK_REPLIES = 13;

    public const MAX_BUTTONS = 3;

    public const MAX_CARDS = 10;

    public const MAX_CARD_TITLE_LENGTH = 80;

    public const MAX_LABEL_LENGTH = 20;

    public const MAX_URL_LENGTH = 2048;

    public const MAX_VARIABLE_RETRIES = 5;

    public const MAX_DELAY_SECONDS = 86400;

    public const MAX_HANDOFF_MINUTES = 10080;

    public const STRUCTURAL_CODES = [
        'no_nodes',
        'too_many_nodes',
        'invalid_node_id',
        'duplicate_node_id',
        'invalid_node_type',
        'dangling_edge',
        'invalid_handle',
        'duplicate_handle_edge',
        'terminal_node_has_edges',
    ];

    private const ID_PATTERN = '/^[A-Za-z0-9_-]{1,40}$/';

    private const VARIABLE_PATTERN = '/^[A-Za-z][A-Za-z0-9_]{0,39}$/';

    public function validate(array $definition): array
    {
        $definition = FlowDefinitionConverter::toCurrentVersion($definition);
        $issues = [];
        $nodes = $this->indexedNodes($definition, $issues);
        $issues = array_merge($issues, $this->entryIssues($definition, $nodes));

        foreach ($nodes as $nodeId => $node) {
            $issues = array_merge($issues, $this->nodeDataIssues($nodeId, $node));
        }

        $edges = $this->edgeIssues($definition, $nodes, $issues);
        $issues = array_merge($issues, $this->connectionIssues($nodes, $edges));
        $issues = array_merge($issues, $this->reachabilityIssues($definition, $nodes, $edges));
        $issues = array_merge($issues, $this->loopIssues($nodes, $edges));

        return $issues;
    }

    public function errors(array $definition): array
    {
        return array_values(array_filter(
            $this->validate($definition),
            fn (FlowIssue $issue) => $issue->severity->value === 'error',
        ));
    }

    public function passes(array $definition): bool
    {
        return $this->errors($definition) === [];
    }

    public function structuralErrors(array $definition): array
    {
        return array_values(array_filter(
            $this->errors($definition),
            fn (FlowIssue $issue) => in_array($issue->code, self::STRUCTURAL_CODES, true),
        ));
    }

    private function indexedNodes(array $definition, ?array &$issues): array
    {
        $issues = [];
        $rawNodes = array_values(array_filter((array) ($definition['nodes'] ?? []), 'is_array'));
        $nodes = [];

        if ($rawNodes === []) {
            $issues[] = FlowIssue::error('no_nodes');
        }

        if (count($rawNodes) > self::MAX_NODES) {
            $issues[] = FlowIssue::error('too_many_nodes', null, ['max' => self::MAX_NODES]);
        }

        foreach ($rawNodes as $rawNode) {
            $nodeId = (string) ($rawNode['id'] ?? '');

            if (preg_match(self::ID_PATTERN, $nodeId) !== 1) {
                $issues[] = FlowIssue::error('invalid_node_id', $nodeId !== '' ? $nodeId : null);

                continue;
            }

            if (isset($nodes[$nodeId])) {
                $issues[] = FlowIssue::error('duplicate_node_id', $nodeId);

                continue;
            }

            if (FlowNodeType::tryFrom((string) ($rawNode['type'] ?? '')) === null) {
                $issues[] = FlowIssue::error('invalid_node_type', $nodeId);

                continue;
            }

            $nodes[$nodeId] = FlowNode::fromArray($rawNode);
        }

        return $nodes;
    }

    private function entryIssues(array $definition, array $nodes): array
    {
        $issues = [];
        $entry = (array) ($definition['entry'] ?? []);
        $triggers = array_values(array_filter((array) ($entry['triggers'] ?? []), 'is_string'));

        if ($triggers === []) {
            $issues[] = FlowIssue::error('no_triggers');
        }

        if (count($triggers) > self::MAX_TRIGGERS) {
            $issues[] = FlowIssue::error('too_many_triggers', null, ['max' => self::MAX_TRIGGERS]);
        }

        if (count(array_unique(array_map('mb_strtolower', $triggers))) !== count($triggers)) {
            $issues[] = FlowIssue::error('duplicate_triggers');
        }

        $matchType = MatchType::tryFrom((string) ($entry['match_type'] ?? ''));

        if ($matchType === null || $matchType === MatchType::Any) {
            $issues[] = FlowIssue::error('invalid_match_type');
        }

        if (! isset($nodes[(string) ($entry['start_node'] ?? '')])) {
            $issues[] = FlowIssue::error('missing_start_node');
        }

        return $issues;
    }

    private function nodeDataIssues(string $nodeId, FlowNode $node): array
    {
        return match ($node->type) {
            FlowNodeType::Message => $this->messageIssues($nodeId, $node),
            FlowNodeType::QuickReplies => $this->quickRepliesIssues($nodeId, $node),
            FlowNodeType::Buttons => $this->buttonsIssues($nodeId, $node),
            FlowNodeType::Cards => $this->cardsIssues($nodeId, $node),
            FlowNodeType::Catalog => $this->catalogIssues($nodeId, $node),
            FlowNodeType::Ask => $this->askIssues($nodeId, $node),
            FlowNodeType::Condition => $this->conditionIssues($nodeId, $node),
            FlowNodeType::Delay => $this->delayIssues($nodeId, $node),
            FlowNodeType::SetVariable => $this->setVariableIssues($nodeId, $node),
            FlowNodeType::Jump => $this->jumpIssues($nodeId, $node),
            FlowNodeType::Handoff => $this->handoffIssues($nodeId, $node),
            FlowNodeType::End => [],
        };
    }

    private function messageIssues(string $nodeId, FlowNode $node): array
    {
        $issues = $this->textIssues($nodeId, $node->text(), required: $node->imageUrl() === null);

        return array_merge($issues, $this->imageIssues($nodeId, $node->imageUrl()));
    }

    private function quickRepliesIssues(string $nodeId, FlowNode $node): array
    {
        $issues = array_merge(
            $this->textIssues($nodeId, $node->text(), required: true),
            $this->imageIssues($nodeId, $node->imageUrl()),
        );
        $options = $node->options();

        if ($options === []) {
            $issues[] = FlowIssue::error('no_options', $nodeId);
        }

        if (count($options) > self::MAX_QUICK_REPLIES) {
            $issues[] = FlowIssue::error('too_many_options', $nodeId, ['max' => self::MAX_QUICK_REPLIES]);
        }

        return array_merge($issues, $this->choiceIssues($nodeId, $options));
    }

    private function buttonsIssues(string $nodeId, FlowNode $node): array
    {
        $issues = $this->textIssues($nodeId, $node->text(), required: true);
        $buttons = $node->buttons();

        if ($buttons === []) {
            $issues[] = FlowIssue::error('no_buttons', $nodeId);
        }

        if (count($buttons) > self::MAX_BUTTONS) {
            $issues[] = FlowIssue::error('too_many_buttons', $nodeId, ['max' => self::MAX_BUTTONS]);
        }

        foreach ($buttons as $button) {
            if (($button['type'] ?? 'next') === 'url' && ! $this->isSecureUrl((string) ($button['url'] ?? ''))) {
                $issues[] = FlowIssue::error('invalid_button_url', $nodeId);
            }
        }

        return array_merge($issues, $this->choiceIssues($nodeId, $buttons));
    }

    private function cardsIssues(string $nodeId, FlowNode $node): array
    {
        $issues = [];
        $elements = $node->elements();

        if ($elements === []) {
            $issues[] = FlowIssue::error('no_cards', $nodeId);
        }

        if (count($elements) > self::MAX_CARDS) {
            $issues[] = FlowIssue::error('too_many_cards', $nodeId, ['max' => self::MAX_CARDS]);
        }

        foreach ($elements as $element) {
            $issues = array_merge($issues, $this->cardIssues($nodeId, $element));
        }

        if (count($node->options()) > self::MAX_QUICK_REPLIES) {
            $issues[] = FlowIssue::error('too_many_options', $nodeId, ['max' => self::MAX_QUICK_REPLIES]);
        }

        return array_merge($issues, $this->choiceIssues($nodeId, $this->connectableChoices($node), requireUniqueLabels: false));
    }

    private function catalogIssues(string $nodeId, FlowNode $node): array
    {
        $issues = $this->textIssues($nodeId, $node->text(), required: true);

        if (CatalogSource::tryFrom((string) $node->get('source', '')) === null) {
            $issues[] = FlowIssue::error('invalid_catalog_source', $nodeId);
        }

        $limit = $node->get('limit', self::MAX_CARDS);

        if (! is_int($limit) || $limit < 1 || $limit > self::MAX_CARDS) {
            $issues[] = FlowIssue::error('invalid_catalog_limit', $nodeId, ['max' => self::MAX_CARDS]);
        }

        $selectLabel = trim((string) $node->get('select_label', ''));

        if ($selectLabel !== '' && mb_strlen($selectLabel) > self::MAX_LABEL_LENGTH) {
            $issues[] = FlowIssue::error('invalid_choice_label', $nodeId, ['max' => self::MAX_LABEL_LENGTH]);
        }

        return $issues;
    }

    private function cardIssues(string $nodeId, array $element): array
    {
        $issues = [];
        $title = trim((string) ($element['title'] ?? ''));
        $subtitle = (string) ($element['subtitle'] ?? '');
        $imageUrl = (string) ($element['image_url'] ?? '');
        $buttons = array_values(array_filter((array) ($element['buttons'] ?? []), 'is_array'));

        if ($title === '' || mb_strlen($title) > self::MAX_CARD_TITLE_LENGTH) {
            $issues[] = FlowIssue::error('invalid_card_title', $nodeId, ['max' => self::MAX_CARD_TITLE_LENGTH]);
        }

        if (mb_strlen($subtitle) > self::MAX_CARD_TITLE_LENGTH) {
            $issues[] = FlowIssue::error('invalid_card_subtitle', $nodeId, ['max' => self::MAX_CARD_TITLE_LENGTH]);
        }

        if ($imageUrl !== '' && ! $this->isSecureUrl($imageUrl)) {
            $issues[] = FlowIssue::error('invalid_image_url', $nodeId);
        }

        if (count($buttons) > self::MAX_BUTTONS) {
            $issues[] = FlowIssue::error('too_many_buttons', $nodeId, ['max' => self::MAX_BUTTONS]);
        }

        foreach ($buttons as $button) {
            if (($button['type'] ?? 'next') === 'url' && ! $this->isSecureUrl((string) ($button['url'] ?? ''))) {
                $issues[] = FlowIssue::error('invalid_button_url', $nodeId);
            }
        }

        return $issues;
    }

    private function connectableChoices(FlowNode $node): array
    {
        return match ($node->type) {
            FlowNodeType::QuickReplies => $node->options(),
            FlowNodeType::Buttons => array_values(array_filter(
                $node->buttons(),
                fn (array $button) => ($button['type'] ?? 'next') !== 'url',
            )),
            FlowNodeType::Cards => $node->postbackChoices(),
            default => [],
        };
    }

    private function choiceIssues(string $nodeId, array $choices, bool $requireUniqueLabels = true): array
    {
        $issues = [];
        $ids = [];

        foreach ($choices as $choice) {
            $id = (string) ($choice['id'] ?? '');
            $label = trim((string) ($choice['label'] ?? ''));

            if (preg_match(self::ID_PATTERN, $id) !== 1 || in_array($id, $ids, true)) {
                $issues[] = FlowIssue::error('invalid_choice_id', $nodeId);
            }

            $ids[] = $id;

            if ($label === '' || mb_strlen($label) > self::MAX_LABEL_LENGTH) {
                $issues[] = FlowIssue::error('invalid_choice_label', $nodeId, ['max' => self::MAX_LABEL_LENGTH]);
            }
        }

        $labels = array_map(fn (array $choice) => mb_strtolower(trim((string) ($choice['label'] ?? ''))), $choices);

        if ($requireUniqueLabels && count(array_unique($labels)) !== count($labels)) {
            $issues[] = FlowIssue::error('duplicate_choice_labels', $nodeId);
        }

        return $issues;
    }

    private function askIssues(string $nodeId, FlowNode $node): array
    {
        $issues = $this->textIssues($nodeId, $node->text(), required: true);

        if (preg_match(self::VARIABLE_PATTERN, (string) $node->get('variable')) !== 1) {
            $issues[] = FlowIssue::error('invalid_variable_name', $nodeId);
        }

        if (FlowInputExpectation::tryFrom((string) $node->get('expects', 'text')) === null) {
            $issues[] = FlowIssue::error('invalid_expectation', $nodeId);
        }

        $retries = $node->get('retries', 1);

        if (! is_int($retries) || $retries < 0 || $retries > self::MAX_VARIABLE_RETRIES) {
            $issues[] = FlowIssue::error('invalid_retries', $nodeId, ['max' => self::MAX_VARIABLE_RETRIES]);
        }

        return $issues;
    }

    private function conditionIssues(string $nodeId, FlowNode $node): array
    {
        $issues = [];

        if (preg_match(self::VARIABLE_PATTERN, (string) $node->get('variable')) !== 1) {
            $issues[] = FlowIssue::error('invalid_variable_name', $nodeId);
        }

        $operator = FlowConditionOperator::tryFrom((string) $node->get('operator', ''));

        if ($operator === null) {
            $issues[] = FlowIssue::error('invalid_operator', $nodeId);
        } elseif ($operator->needsValue() && trim((string) $node->get('value', '')) === '') {
            $issues[] = FlowIssue::error('missing_condition_value', $nodeId);
        }

        return $issues;
    }

    private function delayIssues(string $nodeId, FlowNode $node): array
    {
        $seconds = $node->get('seconds');

        if (! is_int($seconds) || $seconds < 1 || $seconds > self::MAX_DELAY_SECONDS) {
            return [FlowIssue::error('invalid_delay', $nodeId, ['max' => self::MAX_DELAY_SECONDS])];
        }

        return [];
    }

    private function setVariableIssues(string $nodeId, FlowNode $node): array
    {
        $issues = [];

        if (preg_match(self::VARIABLE_PATTERN, (string) $node->get('variable')) !== 1) {
            $issues[] = FlowIssue::error('invalid_variable_name', $nodeId);
        }

        if (mb_strlen((string) $node->get('value', '')) > 500) {
            $issues[] = FlowIssue::error('invalid_variable_value', $nodeId, ['max' => 500]);
        }

        return $issues;
    }

    private function jumpIssues(string $nodeId, FlowNode $node): array
    {
        $target = $node->get('bot_flow_id');

        if (! is_int($target) && ! ctype_digit((string) $target)) {
            return [FlowIssue::error('invalid_jump_target', $nodeId)];
        }

        return [];
    }

    private function handoffIssues(string $nodeId, FlowNode $node): array
    {
        $issues = $this->textIssues($nodeId, $node->text(), required: false);
        $minutes = $node->get('pause_minutes', 1440);

        if (! is_int($minutes) || $minutes < 1 || $minutes > self::MAX_HANDOFF_MINUTES) {
            $issues[] = FlowIssue::error('invalid_handoff_pause', $nodeId, ['max' => self::MAX_HANDOFF_MINUTES]);
        }

        return $issues;
    }

    private function textIssues(string $nodeId, string $text, bool $required): array
    {
        if ($required && trim($text) === '') {
            return [FlowIssue::error('missing_text', $nodeId)];
        }

        if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
            return [FlowIssue::error('text_too_long', $nodeId, ['max' => self::MAX_TEXT_LENGTH])];
        }

        return [];
    }

    private function imageIssues(string $nodeId, ?string $imageUrl): array
    {
        if ($imageUrl !== null && (! $this->isSecureUrl($imageUrl) || mb_strlen($imageUrl) > self::MAX_URL_LENGTH)) {
            return [FlowIssue::error('invalid_image_url', $nodeId)];
        }

        return [];
    }

    private function isSecureUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false && str_starts_with($url, 'https://');
    }

    private function edgeIssues(array $definition, array $nodes, array &$issues): array
    {
        $edges = [];
        $seenHandles = [];

        foreach (array_filter((array) ($definition['edges'] ?? []), 'is_array') as $rawEdge) {
            $edge = FlowEdge::fromArray($rawEdge);

            if ($edge === null || ! isset($nodes[$edge->source]) || ! isset($nodes[$edge->target])) {
                $issues[] = FlowIssue::error('dangling_edge', $edge?->source);

                continue;
            }

            $sourceNode = $nodes[$edge->source];

            if (! in_array($edge->sourceHandle, $this->availableHandles($sourceNode), true)) {
                $issues[] = FlowIssue::error('invalid_handle', $edge->source, ['handle' => $edge->sourceHandle]);

                continue;
            }

            $handleKey = $edge->source.':'.$edge->sourceHandle;

            if (isset($seenHandles[$handleKey])) {
                $issues[] = FlowIssue::error('duplicate_handle_edge', $edge->source, ['handle' => $edge->sourceHandle]);

                continue;
            }

            $seenHandles[$handleKey] = true;
            $edges[] = $edge;
        }

        return $edges;
    }

    private function availableHandles(FlowNode $node): array
    {
        return array_merge($node->type->staticHandles(), array_map(
            fn (array $choice) => (string) ($choice['id'] ?? ''),
            $this->connectableChoices($node),
        ));
    }

    private function connectionIssues(array $nodes, array $edges): array
    {
        $issues = [];
        $connected = [];

        foreach ($edges as $edge) {
            $connected[$edge->source][] = $edge->sourceHandle;
        }

        foreach ($nodes as $nodeId => $node) {
            $handles = $connected[$nodeId] ?? [];

            foreach ($node->type->requiredHandles() as $handle) {
                if (! in_array($handle, $handles, true)) {
                    $issues[] = FlowIssue::error('unconnected_handle', $nodeId, ['handle' => $handle]);
                }
            }

            {
                foreach ($this->connectableChoices($node) as $choice) {
                    if (! in_array((string) ($choice['id'] ?? ''), $handles, true)) {
                        $issues[] = FlowIssue::error('unconnected_choice', $nodeId, ['label' => (string) ($choice['label'] ?? '')]);
                    }
                }
            }

            if ($node->type->isTerminal() && $handles !== []) {
                $issues[] = FlowIssue::error('terminal_node_has_edges', $nodeId);
            }
        }

        return $issues;
    }

    private function reachabilityIssues(array $definition, array $nodes, array $edges): array
    {
        $startNodeId = (string) data_get($definition, 'entry.start_node');

        if (! isset($nodes[$startNodeId])) {
            return [];
        }

        $reachable = [$startNodeId => true];
        $queue = [$startNodeId];

        while ($queue !== []) {
            $current = array_shift($queue);

            foreach ($edges as $edge) {
                if ($edge->source === $current && ! isset($reachable[$edge->target])) {
                    $reachable[$edge->target] = true;
                    $queue[] = $edge->target;
                }
            }
        }

        return array_values(array_map(
            fn (string $nodeId) => FlowIssue::warning('unreachable_node', $nodeId),
            array_values(array_diff(array_keys($nodes), array_keys($reachable))),
        ));
    }

    private function loopIssues(array $nodes, array $edges): array
    {
        $graph = [];

        foreach ($edges as $edge) {
            if (! $nodes[$edge->source]->type->waitsForUser()) {
                $graph[$edge->source][] = $edge->target;
            }
        }

        $state = [];
        $issues = [];

        foreach (array_keys($nodes) as $nodeId) {
            $this->detectCycle($nodeId, $graph, $state, $issues);
        }

        return $issues;
    }

    private function detectCycle(string $nodeId, array $graph, array &$state, array &$issues): void
    {
        $status = $state[$nodeId] ?? 'unvisited';

        if ($status === 'visiting') {
            $issues[] = FlowIssue::error('instant_loop', $nodeId);

            return;
        }

        if ($status === 'visited') {
            return;
        }

        $state[$nodeId] = 'visiting';

        foreach ($graph[$nodeId] ?? [] as $target) {
            $this->detectCycle($target, $graph, $state, $issues);
        }

        $state[$nodeId] = 'visited';
    }
}
