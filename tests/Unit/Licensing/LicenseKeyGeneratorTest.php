<?php

namespace Tests\Unit\Licensing;

use App\Services\Licensing\LicenseKeyGenerator;
use App\Services\Licensing\LicenseKeyNormalizer;
use PHPUnit\Framework\TestCase;

class LicenseKeyGeneratorTest extends TestCase
{
    public function test_generated_keys_follow_the_readable_format(): void
    {
        $key = (new LicenseKeyGenerator())->generate();

        $this->assertMatchesRegularExpression('/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{5}(-[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{5}){3}$/', $key);
    }

    public function test_generated_keys_never_contain_ambiguous_characters(): void
    {
        $generator = new LicenseKeyGenerator();

        for ($iteration = 0; $iteration < 200; $iteration++) {
            $this->assertDoesNotMatchRegularExpression('/[01OI]/', $generator->generate());
        }
    }

    public function test_generated_keys_are_unique_across_many_samples(): void
    {
        $generator = new LicenseKeyGenerator();
        $keys = [];

        for ($iteration = 0; $iteration < 2000; $iteration++) {
            $keys[] = $generator->generate();
        }

        $this->assertCount(2000, array_unique($keys));
    }

    public function test_normalizer_accepts_lowercase_spaces_and_missing_dashes(): void
    {
        $normalizer = new LicenseKeyNormalizer();

        $this->assertSame('ABCDE-FGHJK-LMNPQ-RSTUV', $normalizer->normalize(' abcdefghjklmnpqrstuv '));
        $this->assertSame('ABCDE-FGHJK-LMNPQ-RSTUV', $normalizer->normalize('abcde fghjk_lmnpq.rstuv'));
        $this->assertSame('ABCDE-FGHJK-LMNPQ-RSTUV', $normalizer->normalize('ABCDE-FGHJK-LMNPQ-RSTUV'));
    }

    public function test_normalizer_leaves_wrong_length_input_compact(): void
    {
        $this->assertSame('ABC', (new LicenseKeyNormalizer())->normalize('a-b-c'));
    }
}
