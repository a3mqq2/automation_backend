<?php

namespace App\Services\Admin;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Models\Admin;
use App\Services\Auth\PasswordVerifier;
use App\Support\AuthenticatedSession;
use Illuminate\Support\Str;

class AdminAuthService
{
    public function __construct(private readonly PasswordVerifier $passwords)
    {
    }

    public function login(string $email, string $password): AuthenticatedSession
    {
        $admin = Admin::query()->where('email', Str::lower(trim($email)))->first();

        if ($admin === null || ! $this->passwords->verify($admin->password, $password)) {
            throw new ApiException(ErrorCode::InvalidCredentials);
        }

        $admin->forceFill(['last_login_at' => now()])->save();

        return new AuthenticatedSession($admin, $admin->createToken('admin')->plainTextToken);
    }
}
