<?php

namespace App\Services\Auth;

use App\Exceptions\MetaGraphException;
use App\Services\Meta\MetaGraphClient;

class FacebookTokenExchanger
{
    public function __construct(private readonly MetaGraphClient $graph)
    {
    }

    public function toLongLived(string $shortLivedToken, mixed $shortLivedExpiresIn): FacebookAccessToken
    {
        try {
            $response = $this->graph->get('oauth/access_token', null, [
                'grant_type' => 'fb_exchange_token',
                'client_id' => config('services.facebook.client_id'),
                'client_secret' => config('services.facebook.client_secret'),
                'fb_exchange_token' => $shortLivedToken,
            ]);
        } catch (MetaGraphException $exception) {
            report($exception);

            return FacebookAccessToken::fromLifetime($shortLivedToken, $shortLivedExpiresIn);
        }

        if (! is_string($response['access_token'] ?? null) || $response['access_token'] === '') {
            return FacebookAccessToken::fromLifetime($shortLivedToken, $shortLivedExpiresIn);
        }

        return FacebookAccessToken::fromLifetime($response['access_token'], $response['expires_in'] ?? null);
    }
}
