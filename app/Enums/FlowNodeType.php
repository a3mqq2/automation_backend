<?php

namespace App\Enums;

enum FlowNodeType: string
{
    case Message = 'message';
    case QuickReplies = 'quick_replies';
    case Buttons = 'buttons';
    case Cards = 'cards';
    case Catalog = 'catalog';
    case Ask = 'ask';
    case Condition = 'condition';
    case Delay = 'delay';
    case SetVariable = 'set_variable';
    case Jump = 'jump';
    case Handoff = 'handoff';
    case End = 'end';

    public const FALLBACK_HANDLE = 'fallback';

    public const NEXT_HANDLE = 'next';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Jump, self::Handoff, self::End], true);
    }

    public function waitsForUser(): bool
    {
        return in_array($this, [self::QuickReplies, self::Buttons, self::Cards, self::Catalog, self::Ask, self::Delay], true);
    }

    public function staticHandles(): array
    {
        return match ($this) {
            self::Message, self::Delay, self::SetVariable => [self::NEXT_HANDLE],
            self::Ask => ['success', 'failure'],
            self::Condition => ['true', 'false'],
            self::QuickReplies, self::Buttons, self::Cards => [self::FALLBACK_HANDLE],
            self::Catalog => ['selected', 'empty', self::FALLBACK_HANDLE],
            self::Jump, self::Handoff, self::End => [],
        };
    }

    public function requiredHandles(): array
    {
        return match ($this) {
            self::Message, self::Delay, self::SetVariable => [self::NEXT_HANDLE],
            self::Ask => ['success'],
            self::Catalog => ['selected'],
            self::Condition => ['true', 'false'],
            default => [],
        };
    }
}
