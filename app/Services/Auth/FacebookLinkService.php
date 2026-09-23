<?php

namespace App\Services\Auth;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FacebookLinkService
{
    public function __construct(
        private readonly FacebookLinkStateStore $states,
        private readonly FacebookOAuthService $facebook,
        private readonly FacebookTokenExchanger $tokenExchanger,
    ) {
    }

    public function authorizationUrl(User $client): string
    {
        return $this->facebook->authorizationUrl($this->states->issue($client));
    }

    public function link(User $client, ?string $state, ?string $error): User
    {
        $stateIsValid = $this->states->consume($state, $client);

        if ($error !== null && $error !== '') {
            throw new ApiException(ErrorCode::FacebookLinkFailed);
        }

        if (! $stateIsValid) {
            throw new ApiException(ErrorCode::InvalidOAuthState);
        }

        $facebookUser = $this->facebook->userFromCallback(ErrorCode::FacebookLinkFailed);
        $accessToken = $this->tokenExchanger->toLongLived($facebookUser->token, $facebookUser->expiresIn ?? null);
        $facebookUserId = (string) $facebookUser->getId();

        return DB::transaction(function () use ($client, $facebookUser, $accessToken, $facebookUserId): User {
            $this->ensureNotLinkedToAnotherClient($client, $facebookUserId);

            $client->fill([
                'fb_user_id' => $facebookUserId,
                'fb_access_token' => $accessToken->token,
                'token_expires_at' => $accessToken->expiresAt,
            ]);

            if ($client->avatar_url === null) {
                $client->avatar_url = $this->facebook->avatarUrlOf($facebookUser);
            }

            $client->save();

            return $client;
        });
    }

    private function ensureNotLinkedToAnotherClient(User $client, string $facebookUserId): void
    {
        $linkedElsewhere = User::query()
            ->where('fb_user_id', $facebookUserId)
            ->whereKeyNot($client->getKey())
            ->lockForUpdate()
            ->exists();

        if ($linkedElsewhere) {
            throw new ApiException(ErrorCode::FacebookAccountAlreadyLinked);
        }
    }
}
