<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_url' => $this->avatar_url,
            'has_password' => $this->hasPassword(),
            'fb_user_id' => $this->fb_user_id,
            'facebook_linked' => $this->hasLinkedFacebook(),
            'facebook_token_expires_at' => $this->token_expires_at?->toIso8601String(),
            'facebook_token_valid' => $this->hasValidFacebookToken(),
            'subscription' => new SubscriptionResource($this->resource),
            'connected_pages_count' => $this->whenCounted('connectedPages'),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
