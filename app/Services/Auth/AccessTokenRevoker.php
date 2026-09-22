<?php

namespace App\Services\Auth;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\PersonalAccessToken;

class AccessTokenRevoker
{
    public function revokeCurrent(Authenticatable $account): void
    {
        $token = $account->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }
}
