<?php

namespace App\Services\Licensing;

class LicenseKeyNormalizer
{
    public function normalize(string $input): string
    {
        $compact = preg_replace('/[^A-Z0-9]/', '', strtoupper($input)) ?? '';
        $expectedLength = LicenseKeyGenerator::GROUP_COUNT * LicenseKeyGenerator::GROUP_LENGTH;

        if (strlen($compact) !== $expectedLength) {
            return $compact;
        }

        return implode(LicenseKeyGenerator::SEPARATOR, str_split($compact, LicenseKeyGenerator::GROUP_LENGTH));
    }
}
