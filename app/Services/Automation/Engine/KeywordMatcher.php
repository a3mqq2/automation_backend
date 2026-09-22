<?php

namespace App\Services\Automation\Engine;

use App\Enums\MatchType;

class KeywordMatcher
{
    public function __construct(private readonly TextNormalizer $normalizer)
    {
    }

    public function matches(string $text, array $keywords, MatchType $matchType): bool
    {
        if ($matchType === MatchType::Any) {
            return true;
        }

        $normalizedText = $this->normalizer->normalize($text);

        if ($normalizedText === '') {
            return false;
        }

        foreach ($keywords as $keyword) {
            $normalizedKeyword = is_string($keyword) ? $this->normalizer->normalize($keyword) : '';

            if ($normalizedKeyword !== '' && $this->keywordMatches($normalizedText, $normalizedKeyword, $matchType)) {
                return true;
            }
        }

        return false;
    }

    public function sameText(string $first, string $second): bool
    {
        $normalizedFirst = $this->normalizer->normalize($first);

        return $normalizedFirst !== '' && $normalizedFirst === $this->normalizer->normalize($second);
    }

    private function keywordMatches(string $text, string $keyword, MatchType $matchType): bool
    {
        return match ($matchType) {
            MatchType::Exact => $text === $keyword,
            MatchType::Contains => str_contains($text, $keyword),
            MatchType::StartsWith => str_starts_with($text, $keyword),
            MatchType::Any => true,
        };
    }
}
