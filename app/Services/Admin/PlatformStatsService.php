<?php

namespace App\Services\Admin;

use App\Enums\ActivityStatus;
use App\Enums\SubscriptionStatus;
use App\Models\ActivityLog;
use App\Models\FacebookPage;
use App\Models\LicenseKey;
use App\Models\User;

class PlatformStatsService
{
    public function summary(): array
    {
        return [
            'clients' => [
                'total' => User::query()->count(),
                'active_subscriptions' => User::query()->withSubscriptionStatus(SubscriptionStatus::Active)->count(),
                'expired_subscriptions' => User::query()->withSubscriptionStatus(SubscriptionStatus::Expired)->count(),
                'without_subscription' => User::query()->withSubscriptionStatus(SubscriptionStatus::None)->count(),
            ],
            'pages' => [
                'connected' => FacebookPage::query()->connected()->count(),
                'total' => FacebookPage::query()->count(),
            ],
            'automation' => [
                'replies_total' => ActivityLog::query()->automatedReplies()->count(),
                'replies_last_30_days' => ActivityLog::query()->automatedReplies()->where('created_at', '>=', now()->subDays(30))->count(),
                'failed_total' => ActivityLog::query()->where('status', ActivityStatus::Failed->value)->count(),
            ],
            'license_keys' => [
                'issued' => LicenseKey::query()->count(),
                'used' => LicenseKey::query()->used()->count(),
                'remaining' => LicenseKey::query()->available()->count(),
                'expired_unused' => LicenseKey::query()->expiredUnused()->count(),
            ],
        ];
    }
}
