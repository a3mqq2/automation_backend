<?php

namespace App\Services\Licensing;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Models\LicenseKey;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LicenseActivationService
{
    public function __construct(private readonly LicenseKeyNormalizer $normalizer)
    {
    }

    public function activate(User $user, string $rawKey): User
    {
        $key = $this->normalizer->normalize($rawKey);

        return DB::transaction(function () use ($user, $key): User {
            $licenseKey = LicenseKey::query()->where('key', $key)->lockForUpdate()->first();
            $client = User::query()->lockForUpdate()->findOrFail($user->id);

            $this->ensureActivatable($licenseKey, $client);

            $licenseKey->forceFill([
                'is_used' => true,
                'used_by' => $client->id,
                'used_at' => now(),
            ])->save();

            $client->forceFill([
                'active_license_key_id' => $licenseKey->id,
                'subscription_expires_at' => $licenseKey->expires_at,
            ])->save();

            return $client->load('activeLicenseKey');
        });
    }

    private function ensureActivatable(?LicenseKey $licenseKey, User $client): void
    {
        if ($licenseKey === null) {
            throw new ApiException(ErrorCode::LicenseKeyInvalid);
        }

        if ($licenseKey->is_used) {
            throw new ApiException(ErrorCode::LicenseKeyAlreadyUsed);
        }

        if ($licenseKey->isExpired()) {
            throw new ApiException(ErrorCode::LicenseKeyExpired);
        }

        if ($client->hasActiveSubscription() && $licenseKey->expires_at->lte($client->subscription_expires_at)) {
            throw new ApiException(ErrorCode::LicenseKeyDoesNotExtend);
        }
    }
}
