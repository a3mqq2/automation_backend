<?php

namespace App\Enums;

enum MatchType: string
{
    case Exact = 'exact';
    case Contains = 'contains';
    case StartsWith = 'starts_with';
    case Any = 'any';

    public function requiresKeywords(): bool
    {
        return $this !== self::Any;
    }
}
