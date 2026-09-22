<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->subscriptionStatus()->value,
            'expires_at' => $this->subscription_expires_at?->toIso8601String(),
            'activated_at' => $this->whenLoaded('activeLicenseKey', fn () => $this->activeLicenseKey?->used_at?->toIso8601String()),
            'license_key' => $this->whenLoaded('activeLicenseKey', fn () => $this->activeLicenseKey?->maskedKey()),
        ];
    }
}
