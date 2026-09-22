<?php

namespace App\Services\Automation\Engine;

class TextInterpolator
{
    private const PLACEHOLDER_PATTERN = '/\{\{\s*([A-Za-z][A-Za-z0-9_.]{0,63})\s*\}\}/';

    public function render(string $text, array $variables): string
    {
        return (string) preg_replace_callback(
            self::PLACEHOLDER_PATTERN,
            fn (array $matches) => is_scalar($variables[$matches[1]] ?? null) ? (string) $variables[$matches[1]] : '',
            $text,
        );
    }
}
