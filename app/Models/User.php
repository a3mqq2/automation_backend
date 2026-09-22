<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['fb_user_id', 'name', 'email', 'avatar_url', 'fb_access_token', 'token_expires_at', 'last_login_at'])]
#[Hidden(['fb_access_token'])]
class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;

    protected function casts(): array
    {
        return [
            'fb_access_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'subscription_expires_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function facebookPages(): HasMany
    {
        return $this->hasMany(FacebookPage::class);
    }

    public function connectedPages(): HasMany
    {
        return $this->facebookPages()->where('is_connected', true);
    }

    public function activeLicenseKey(): BelongsTo
    {
        return $this->belongsTo(LicenseKey::class, 'active_license_key_id');
    }

    public function licenseKeys(): HasMany
    {
        return $this->hasMany(LicenseKey::class, 'used_by');
    }

    public function automationRules(): HasManyThrough
    {
        return $this->hasManyThrough(AutomationRule::class, FacebookPage::class);
    }

    public function botFlows(): HasManyThrough
    {
        return $this->hasManyThrough(BotFlow::class, FacebookPage::class);
    }

    public function activityLogs(): HasManyThrough
    {
        return $this->hasManyThrough(ActivityLog::class, FacebookPage::class);
    }

    public function subscriptionStatus(): SubscriptionStatus
    {
        if ($this->subscription_expires_at === null) {
            return SubscriptionStatus::None;
        }

        return $this->subscription_expires_at->isFuture()
            ? SubscriptionStatus::Active
            : SubscriptionStatus::Expired;
    }

    public function hasActiveSubscription(): bool
    {
        return $this->subscriptionStatus() === SubscriptionStatus::Active;
    }

    public function hasValidFacebookToken(): bool
    {
        return $this->fb_access_token !== null
            && ($this->token_expires_at === null || $this->token_expires_at->isFuture());
    }

    #[Scope]
    protected function withSubscriptionStatus(Builder $query, SubscriptionStatus $status): void
    {
        match ($status) {
            SubscriptionStatus::Active => $query->where('subscription_expires_at', '>', now()),
            SubscriptionStatus::Expired => $query->where('subscription_expires_at', '<=', now()),
            SubscriptionStatus::None => $query->whereNull('subscription_expires_at'),
        };
    }
}
