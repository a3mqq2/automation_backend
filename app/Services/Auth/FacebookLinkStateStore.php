<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class FacebookLinkStateStore
{
    private const CACHE_PREFIX = 'oauth_state:facebook_link:';

    public function issue(User $client): string
    {
        $state = Str::random(40);

        Cache::put(
            self::CACHE_PREFIX.$state,
            (int) $client->getKey(),
            now()->addMinutes((int) config('meta.oauth_state_ttl_minutes', 10)),
        );

        return $state;
    }

    public function consume(?string $state, User $client): bool
    {
        if ($state === null || $state === '') {
            return false;
        }

        return Cache::pull(self::CACHE_PREFIX.$state) === (int) $client->getKey();
    }
}
