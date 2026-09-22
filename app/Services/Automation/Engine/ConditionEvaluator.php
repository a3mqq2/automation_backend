<?php

namespace App\Services\Automation\Engine;

use App\Enums\FlowConditionOperator;
use App\Support\BotFlows\FlowNode;

class ConditionEvaluator
{
    public function __construct(private readonly KeywordMatcher $keywords)
    {
    }

    public function evaluate(FlowNode $node, FlowSession $session): bool
    {
        $operator = FlowConditionOperator::tryFrom((string) $node->get('operator', ''));
        $actual = $session->variable((string) $node->get('variable', ''));
        $expected = (string) $node->get('value', '');

        return match ($operator) {
            FlowConditionOperator::IsSet => $actual !== null && $actual !== '',
            FlowConditionOperator::IsNotSet => $actual === null || $actual === '',
            FlowConditionOperator::Equals => $actual !== null && $this->keywords->sameText($actual, $expected),
            FlowConditionOperator::NotEquals => $actual === null || ! $this->keywords->sameText($actual, $expected),
            FlowConditionOperator::Contains => $actual !== null && str_contains(mb_strtolower($actual), mb_strtolower($expected)),
            FlowConditionOperator::GreaterThan => is_numeric($actual) && is_numeric($expected) && (float) $actual > (float) $expected,
            FlowConditionOperator::LessThan => is_numeric($actual) && is_numeric($expected) && (float) $actual < (float) $expected,
            null => false,
        };
    }
}
