<?php

namespace App\Services\Automation\Engine;

class TextNormalizer
{
    private const ARABIC_MARKS_PATTERN = '/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u';

    private const LETTER_VARIANTS = [
        'أ' => 'ا',
        'إ' => 'ا',
        'آ' => 'ا',
        'ٱ' => 'ا',
        'ى' => 'ي',
        'ة' => 'ه',
    ];

    private const DIGITS = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    public function normalize(string $text): string
    {
        $withoutMarks = preg_replace(self::ARABIC_MARKS_PATTERN, '', $text) ?? $text;
        $unified = strtr(mb_strtolower($withoutMarks), self::LETTER_VARIANTS + self::DIGITS);

        return trim(preg_replace('/\s+/u', ' ', $unified) ?? $unified);
    }
}
