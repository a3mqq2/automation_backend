<?php

namespace Database\Factories;

use App\Models\Admin;
use App\Models\User;
use App\Services\Licensing\LicenseKeyGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

class LicenseKeyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'key' => (new LicenseKeyGenerator())->generate(),
            'expires_at' => now()->addMonth(),
            'note' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function ($licenseKey): void {
            $licenseKey->created_by ??= Admin::factory()->create()->id;
        });
    }

    public function usedBy(User $user): static
    {
        return $this->afterMaking(function ($licenseKey) use ($user): void {
            $licenseKey->is_used = true;
            $licenseKey->used_by = $user->id;
            $licenseKey->used_at = now();
        });
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subDay(),
        ]);
    }
}
