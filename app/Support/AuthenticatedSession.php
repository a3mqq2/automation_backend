<?php

namespace App\Support;

use Illuminate\Foundation\Auth\User as Authenticatable;

final readonly class AuthenticatedSession
{
    public function __construct(
        public Authenticatable $account,
        public string $token,
    ) {
    }
}
