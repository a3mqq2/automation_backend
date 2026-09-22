<?php

namespace App\Models;

use App\Enums\LicenseKeyStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['key', 'expires_at', 'note'])]
class LicenseKey extends Model
{
    use HasFactory;

    protected $attributes = [
        'is_used' => false,
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_used' => 'boolean',
            'used_at' => 'datetime',
        ];
    }

    public function usedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isAvailable(): bool
    {
        return ! $this->is_used && ! $this->isExpired();
    }

    public function status(): LicenseKeyStatus
    {
        return match (true) {
            $this->is_used => LicenseKeyStatus::Used,
            $this->isExpired() => LicenseKeyStatus::Expired,
            default => LicenseKeyStatus::Available,
        };
    }

    public function maskedKey(): string
    {
        $groups = explode('-', $this->key);
        $visibleGroup = array_pop($groups);

        return implode('-', array_map(fn (string $group) => str_repeat('*', strlen($group)), $groups))
            .($groups === [] ? '' : '-')
            .$visibleGroup;
    }

    #[Scope]
    protected function used(Builder $query): void
    {
        $query->where('is_used', true);
    }

    #[Scope]
    protected function available(Builder $query): void
    {
        $query->where('is_used', false)->where('expires_at', '>', now());
    }

    #[Scope]
    protected function expiredUnused(Builder $query): void
    {
        $query->where('is_used', false)->where('expires_at', '<=', now());
    }
}
