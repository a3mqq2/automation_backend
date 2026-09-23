<?php

namespace Database\Factories;

use App\Models\LicenseKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'fb_user_id' => (string) fake()->unique()->numerify('10##########'),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'avatar_url' => fake()->imageUrl(),
            'fb_access_token' => 'EAA'.Str::random(60),
            'token_expires_at' => now()->addDays(60),
            'last_login_at' => now(),
        ];
    }

    public function subscribed(?\DateTimeInterface $expiresAt = null): static
    {
        return $this->afterCreating(function (User $user) use ($expiresAt): void {
            $licenseKey = LicenseKey::factory()->usedBy($user)->create([
                'expires_at' => $expiresAt ?? now()->addMonth(),
            ]);

            $user->forceFill([
                'active_license_key_id' => $licenseKey->id,
                'subscription_expires_at' => $licenseKey->expires_at,
            ])->save();
        });
    }

    public function withExpiredSubscription(): static
    {
        return $this->afterCreating(function (User $user): void {
            $licenseKey = LicenseKey::factory()->usedBy($user)->create([
                'expires_at' => now()->subDay(),
            ]);

            $user->forceFill([
                'active_license_key_id' => $licenseKey->id,
                'subscription_expires_at' => $licenseKey->expires_at,
            ])->save();
        });
    }

    public function withPassword(string $password = 'password'): static
    {
        return $this->state(fn () => ['password' => $password]);
    }

    public function withoutFacebook(): static
    {
        return $this->state(fn () => [
            'fb_user_id' => null,
            'avatar_url' => null,
            'fb_access_token' => null,
            'token_expires_at' => null,
        ]);
    }

    public function withExpiredFacebookToken(): static
    {
        return $this->state(fn () => [
            'token_expires_at' => now()->subDay(),
        ]);
    }
}
