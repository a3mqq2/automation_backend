<?php

namespace App\Services\Auth;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Support\AuthenticatedSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        return DB::transaction(function () use ($facebookUser, $accessToken): User {
            $facebookUserId = (string) $facebookUser->getId();
            $client = User::query()->where('fb_user_id', $facebookUserId)->first()
                ?? new User(['fb_user_id' => $facebookUserId]);

            $client->fill([
                'fb_access_token' => $accessToken->token,
                'token_expires_at' => $accessToken->expiresAt,
                'last_login_at' => now(),
            ]);

            if (! $client->hasPassword()) {
                $client->fill([
                    'name' => $facebookUser->getName() ?: $facebookUserId,
                    'email' => $this->claimableEmail($client, $facebookUser->getEmail()),
                    'avatar_url' => $this->facebook->avatarUrlOf($facebookUser),
                ]);
            } elseif ($client->avatar_url === null) {
                $client->avatar_url = $this->facebook->avatarUrlOf($facebookUser);
            }

            $client->save();

            return $client;
        });
    }

    private function claimableEmail(User $client, ?string $email): ?string
    {
        if ($email === null || $email === '') {
            return $client->email;
        }

        $email = Str::lower(trim($email));

        $takenByAnotherClient = User::query()
            ->where('email', $email)
            ->when($client->exists, fn (Builder $query) => $query->whereKeyNot($client->getKey()))
            ->exists();

        return $takenByAnotherClient ? $client->email : $email;
    }
}
