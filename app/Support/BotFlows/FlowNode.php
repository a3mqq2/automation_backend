<?php

namespace App\Support\BotFlows;

use App\Enums\FlowNodeType;

final readonly class FlowNode
{
    public function __construct(
        public string $id,
        public FlowNodeType $type,
        public array $position,
        public array $data,
    ) {
    }

    public static function fromArray(array $node): ?self
    {
        $type = FlowNodeType::tryFrom((string) ($node['type'] ?? ''));
        $id = (string) ($node['id'] ?? '');

        if ($type === null || $id === '') {
            return null;
        }

        return new self(
            id: $id,
            type: $type,
            position: [
                'x' => (float) data_get($node, 'position.x', 0),
                'y' => (float) data_get($node, 'position.y', 0),
            ],
            data: is_array($node['data'] ?? null) ? $node['data'] : [],
        );
    }

    public function text(): string
    {
        return (string) ($this->data['text'] ?? '');
    }

    public function imageUrl(): ?string
    {
        $imageUrl = $this->data['image_url'] ?? null;

        return is_string($imageUrl) && $imageUrl !== '' ? $imageUrl : null;
    }

    public function options(): array
    {
        return array_values(array_filter(
            (array) ($this->data['options'] ?? []),
            fn ($option) => is_array($option),
        ));
    }

    public function buttons(): array
    {
        return array_values(array_filter(
            (array) ($this->data['buttons'] ?? []),
            fn ($button) => is_array($button),
        ));
    }

    public function elements(): array
    {
        return array_values(array_filter(
            (array) ($this->data['elements'] ?? []),
            fn ($element) => is_array($element),
        ));
    }

    public function elementButtons(): array
    {
        $buttons = [];

        foreach ($this->elements() as $element) {
            foreach ((array) ($element['buttons'] ?? []) as $button) {
                if (is_array($button)) {
                    $buttons[] = $button;
                }
            }
        }

        return $buttons;
    }

    public function postbackChoices(): array
    {
        $choices = array_filter(
            $this->type === FlowNodeType::Cards ? $this->elementButtons() : $this->buttons(),
            fn (array $button) => ($button['type'] ?? 'next') !== 'url',
        );

        return array_values(array_merge($choices, $this->options()));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'position' => $this->position,
            'data' => $this->data,
        ];
    }
}
