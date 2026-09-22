<?php

namespace App\Services\Auth;

use Carbon\CarbonImmutable;

final readonly class FacebookAccessToken
{
    public function __construct(
        public string $token,
        public ?CarbonImmutable $expiresAt,
    ) {
    }

    public static function fromLifetime(string $token, mixed $expiresInSeconds): self
    {
        $seconds = is_numeric($expiresInSeconds) ? (int) $expiresInSeconds : 0;

        return new self($token, $seconds > 0 ? CarbonImmutable::now()->addSeconds($seconds) : null);
    }
}
