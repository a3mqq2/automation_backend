<?php

namespace App\Enums;

enum FlowConditionOperator: string
{
    case IsSet = 'is_set';
    case IsNotSet = 'is_not_set';
    case Equals = 'equals';
    case NotEquals = 'not_equals';
    case Contains = 'contains';
    case GreaterThan = 'greater_than';
    case LessThan = 'less_than';

    public function needsValue(): bool
    {
        return ! in_array($this, [self::IsSet, self::IsNotSet], true);
    }
}
