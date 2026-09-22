<?php

namespace App\Services\Auth;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Support\AuthenticatedSession;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class ClientLoginService
{
    public function __construct(
        private readonly OAuthStateStore $states,
        private readonly FacebookOAuthService $facebook,
        private readonly FacebookTokenExchanger $tokenExchanger,
    ) {
    }

    public function authorizationUrl(): string
    {
        return $this->facebook->authorizationUrl($this->states->issue());
    }

    public function completeLogin(?string $state, ?string $error): AuthenticatedSession
    {
        $stateIsValid = $this->states->consume($state);

        if ($error !== null && $error !== '') {
            throw new ApiException(ErrorCode::FacebookLoginFailed);
        }

        if (! $stateIsValid) {
            throw new ApiException(ErrorCode::InvalidOAuthState);
        }

        $facebookUser = $this->facebook->userFromCallback();
        $accessToken = $this->tokenExchanger->toLongLived($facebookUser->token, $facebookUser->expiresIn ?? null);
        $client = $this->storeClient($facebookUser, $accessToken);

        return new AuthenticatedSession($client, $client->createToken('client')->plainTextToken);
    }

    private function storeClient(SocialiteUser $facebookUser, FacebookAccessToken $accessToken): User
    {
        return DB::transaction(fn (): User => User::query()->updateOrCreate(
            ['fb_user_id' => (string) $facebookUser->getId()],
            [
                'name' => $facebookUser->getName() ?: (string) $facebookUser->getId(),
                'email' => $facebookUser->getEmail(),
                'avatar_url' => $this->avatarUrl($facebookUser),
                'fb_access_token' => $accessToken->token,
                'token_expires_at' => $accessToken->expiresAt,
                'last_login_at' => now(),
            ],
        ));
    }

    private function avatarUrl(SocialiteUser $facebookUser): ?string
    {
        $raw = method_exists($facebookUser, 'getRaw') ? $facebookUser->getRaw() : [];

        return data_get($raw, 'picture.data.url') ?? $facebookUser->getAvatar();
    }
}
