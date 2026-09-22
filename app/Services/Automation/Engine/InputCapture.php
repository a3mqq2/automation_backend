<?php

namespace App\Services\Automation\Engine;

use App\Enums\FlowInputExpectation;

class InputCapture
{
    public function __construct(private readonly TextNormalizer $normalizer)
    {
    }

    public function capture(string $text, FlowInputExpectation $expects): ?string
    {
        $value = trim($text);

        if ($value === '') {
            return null;
        }

        return match ($expects) {
            FlowInputExpectation::Text => $value,
            FlowInputExpectation::Number => $this->number($value),
            FlowInputExpectation::Email => filter_var($value, FILTER_VALIDATE_EMAIL) === false ? null : $value,
            FlowInputExpectation::Phone => $this->phone($value),
        };
    }

    private function number(string $value): ?string
    {
        $normalized = $this->normalizer->normalize($value);

        return is_numeric($normalized) ? $normalized : null;
    }

    private function phone(string $value): ?string
    {
        $digits = preg_replace('/[^0-9+]/', '', $this->normalizer->normalize($value)) ?? '';
        $length = strlen(ltrim($digits, '+'));

        return $length >= 7 && $length <= 15 ? $digits : null;
    }
}
