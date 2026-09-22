<?php

namespace App\Http\Resources\Admin;

use App\Models\FacebookPage;
use App\Models\LicenseKey;
use Illuminate\Http\Request;

class ClientDetailResource extends ClientResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'fb_user_id' => $this->fb_user_id,
            'facebook_token_expires_at' => $this->token_expires_at?->toIso8601String(),
            'active_license_key' => $this->whenLoaded(
                'activeLicenseKey',
                fn () => $this->activeLicenseKey ? $this->licenseKeySummary($this->activeLicenseKey) : null,
            ),
            'pages' => $this->whenLoaded('facebookPages', fn () => $this->facebookPages->map(fn (FacebookPage $page) => [
                'id' => $page->id,
                'page_id' => $page->page_id,
                'name' => $page->name,
                'category' => $page->category,
                'picture_url' => $page->picture_url,
                'is_connected' => $page->is_connected,
                'connected_at' => $page->connected_at?->toIso8601String(),
            ])->values()),
            'license_keys_history' => $this->whenLoaded('licenseKeys', fn () => $this->licenseKeys
                ->map(fn (LicenseKey $licenseKey) => $this->licenseKeySummary($licenseKey))
                ->values()),
            'automation_rules_count' => $this->whenCounted('automationRules'),
            'bot_flows_count' => $this->whenCounted('botFlows'),
            'activity_logs_count' => $this->whenCounted('activityLogs'),
        ]);
    }

    private function licenseKeySummary(LicenseKey $licenseKey): array
    {
        return [
            'id' => $licenseKey->id,
            'key' => $licenseKey->key,
            'expires_at' => $licenseKey->expires_at?->toIso8601String(),
            'used_at' => $licenseKey->used_at?->toIso8601String(),
        ];
    }
}
