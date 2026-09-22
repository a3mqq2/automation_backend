<?php

namespace Tests\Unit\Automation;

use App\Services\Automation\Engine\TextNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TextNormalizerTest extends TestCase
{
    #[DataProvider('equivalentTexts')]
    public function test_equivalent_texts_normalize_identically(string $first, string $second): void
    {
        $normalizer = new TextNormalizer();

        $this->assertSame($normalizer->normalize($first), $normalizer->normalize($second));
    }

    public static function equivalentTexts(): array
    {
        return [
            'hamza forms of alef' => ['أسعار', 'اسعار'],
            'alef below' => ['إعلان', 'اعلان'],
            'alef madda' => ['آخر', 'اخر'],
            'alef wasla' => ['ٱلسعر', 'السعر'],
            'alef maqsura' => ['مستشفى', 'مستشفي'],
            'taa marbuta' => ['مدرسة', 'مدرسه'],
            'diacritics' => ['السَّعْرُ', 'السعر'],
            'tatweel' => ['السـعـر', 'السعر'],
            'arabic indic digits' => ['٠١٢٣٤٥٦٧٨٩', '0123456789'],
            'persian digits' => ['۱۲۳', '123'],
            'latin case' => ['PRICE', 'price'],
            'whitespace' => ["  how   much\n is it ", 'how much is it'],
        ];
    }

    public function test_it_returns_an_empty_string_for_blank_input(): void
    {
        $this->assertSame('', (new TextNormalizer())->normalize("  \n\t "));
    }
}
