<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class OAuthStateStore
{
    private const CACHE_PREFIX = 'oauth_state:facebook:';

    public function issue(): string
    {
        $state = Str::random(40);

        Cache::put(
            self::CACHE_PREFIX.$state,
            true,
            now()->addMinutes((int) config('meta.oauth_state_ttl_minutes', 10)),
        );

        return $state;
    }

    public function consume(?string $state): bool
    {
        if ($state === null || $state === '') {
            return false;
        }

        return Cache::pull(self::CACHE_PREFIX.$state) === true;
    }
}
