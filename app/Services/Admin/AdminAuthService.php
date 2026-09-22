<?php

namespace App\Services\Admin;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Models\Admin;
use App\Support\AuthenticatedSession;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminAuthService
{
    private static ?string $timingGuardHash = null;

    public function login(string $email, string $password): AuthenticatedSession
    {
        $admin = Admin::query()->where('email', Str::lower(trim($email)))->first();

        if ($admin === null) {
            Hash::check($password, $this->timingGuardHash());

            throw new ApiException(ErrorCode::InvalidCredentials);
        }

        if (! Hash::check($password, $admin->password)) {
            throw new ApiException(ErrorCode::InvalidCredentials);
        }

        $admin->forceFill(['last_login_at' => now()])->save();

        return new AuthenticatedSession($admin, $admin->createToken('admin')->plainTextToken);
    }

    private function timingGuardHash(): string
    {
        return self::$timingGuardHash ??= Hash::make(Str::random(32));
    }
}
