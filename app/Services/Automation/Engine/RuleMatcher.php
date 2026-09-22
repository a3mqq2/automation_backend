<?php

namespace App\Services\Automation\Engine;

use App\Enums\TriggerType;
use App\Models\AutomationRule;
use App\Models\FacebookPage;

class RuleMatcher
{
    public function __construct(private readonly KeywordMatcher $keywords)
    {
    }

    public function firstMatch(FacebookPage $page, TriggerType $triggerType, string $text): ?AutomationRule
    {
        return $page->automationRules()
            ->active()
            ->forTrigger($triggerType)
            ->orderBy('id')
            ->get()
            ->first(fn (AutomationRule $rule) => $this->keywords->matches($text, $rule->keywords ?? [], $rule->match_type));
    }
}
