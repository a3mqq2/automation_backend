<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordVerifier
{
    private static ?string $timingGuardHash = null;

    public function verify(?string $storedHash, string $password): bool
    {
        if ($storedHash === null || $storedHash === '') {
            Hash::check($password, $this->timingGuardHash());

            return false;
        }

        return Hash::check($password, $storedHash);
    }

    private function timingGuardHash(): string
    {
        return self::$timingGuardHash ??= Hash::make(Str::random(32));
    }
}
