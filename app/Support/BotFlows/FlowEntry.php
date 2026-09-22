<?php

namespace App\Support\BotFlows;

use App\Enums\MatchType;

final readonly class FlowEntry
{
    public const DEFAULT_LOCALE = 'ar';

    public function __construct(
        public array $triggers,
        public MatchType $matchType,
        public string $startNodeId,
        public string $locale = self::DEFAULT_LOCALE,
    ) {
    }

    public static function fromArray(array $entry): self
    {
        return new self(
            triggers: array_values(array_filter(
                (array) ($entry['triggers'] ?? []),
                fn ($trigger) => is_string($trigger) && trim($trigger) !== '',
            )),
            matchType: MatchType::tryFrom((string) ($entry['match_type'] ?? '')) ?? MatchType::Exact,
            startNodeId: (string) ($entry['start_node'] ?? ''),
            locale: in_array($entry['locale'] ?? null, (array) config('app.supported_locales'), true)
                ? (string) $entry['locale']
                : self::DEFAULT_LOCALE,
        );
    }

    public function toArray(): array
    {
        return [
            'triggers' => $this->triggers,
            'match_type' => $this->matchType->value,
            'start_node' => $this->startNodeId,
            'locale' => $this->locale,
        ];
    }
}
