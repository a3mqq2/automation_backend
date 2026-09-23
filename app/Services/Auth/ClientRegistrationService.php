<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Support\AuthenticatedSession;

class ClientRegistrationService
{
    public function register(string $name, string $email, string $password): AuthenticatedSession
    {
        $client = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'last_login_at' => now(),
        ]);

        return new AuthenticatedSession($client, $client->createToken('client')->plainTextToken);
    }
}
