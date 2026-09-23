<?php

namespace App\Services\Auth;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Support\AuthenticatedSession;
use Illuminate\Support\Str;

class ClientPasswordLoginService
{
    public function __construct(private readonly PasswordVerifier $passwords)
    {
    }

    public function login(string $email, string $password): AuthenticatedSession
    {
        $client = User::query()->where('email', Str::lower(trim($email)))->first();

        if ($client === null || ! $this->passwords->verify($client->password, $password)) {
            throw new ApiException(ErrorCode::InvalidCredentials);
        }

        $client->forceFill(['last_login_at' => now()])->save();

        return new AuthenticatedSession($client, $client->createToken('client')->plainTextToken);
    }
}
