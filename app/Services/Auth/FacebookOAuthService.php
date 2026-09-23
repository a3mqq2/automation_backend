<?php

namespace App\Services\Auth;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class FacebookOAuthService
{
    private const PROFILE_FIELDS = ['name', 'email', 'picture.width(512)'];

    public function authorizationUrl(string $state): string
    {
        return $this->provider()
            ->scopes(config('meta.login_scopes'))
            ->with(['state' => $state])
            ->redirect()
            ->getTargetUrl();
    }

    public function userFromCallback(ErrorCode $failure = ErrorCode::FacebookLoginFailed): SocialiteUser
    {
        try {
            return $this->provider()->fields(self::PROFILE_FIELDS)->user();
        } catch (Throwable $exception) {
            throw new ApiException($failure, previous: $exception);
        }
    }

    public function avatarUrlOf(SocialiteUser $facebookUser): ?string
    {
        $raw = method_exists($facebookUser, 'getRaw') ? $facebookUser->getRaw() : [];

        return data_get($raw, 'picture.data.url') ?? $facebookUser->getAvatar();
    }

    private function provider(): Provider
    {
        return Socialite::driver('facebook')
            ->stateless()
            ->usingGraphVersion((string) config('meta.graph_version'))
            ->redirectUrl((string) config('services.facebook.redirect'));
    }
}
