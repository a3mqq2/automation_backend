<?php

namespace App\Http\Resources\Activity;

use App\Http\Resources\Client\PageSummaryResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_type' => $this->event_type->value,
            'status' => $this->status->value,
            'payload' => $this->payload ?? [],
            'facebook_page' => new PageSummaryResource($this->whenLoaded('facebookPage')),
            'client' => $this->when($this->hasLoadedClient(), fn () => $this->facebookPage->user ? [
                'id' => $this->facebookPage->user->id,
                'name' => $this->facebookPage->user->name,
                'email' => $this->facebookPage->user->email,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function hasLoadedClient(): bool
    {
        return $this->relationLoaded('facebookPage')
            && $this->facebookPage !== null
            && $this->facebookPage->relationLoaded('user');
    }
}
