<?php

namespace App\Services\Licensing;

class LicenseKeyGenerator
{
    public const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public const GROUP_COUNT = 4;

    public const GROUP_LENGTH = 5;

    public const SEPARATOR = '-';

    public function generate(): string
    {
        $groups = [];

        for ($group = 0; $group < self::GROUP_COUNT; $group++) {
            $groups[] = $this->randomGroup();
        }

        return implode(self::SEPARATOR, $groups);
    }

    private function randomGroup(): string
    {
        $lastIndex = strlen(self::ALPHABET) - 1;
        $characters = '';

        for ($position = 0; $position < self::GROUP_LENGTH; $position++) {
            $characters .= self::ALPHABET[random_int(0, $lastIndex)];
        }

        return $characters;
    }
}
