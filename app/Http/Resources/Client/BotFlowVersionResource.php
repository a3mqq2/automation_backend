<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BotFlowVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'version' => $this->version,
            'nodes_count' => count($this->definition['nodes'] ?? []),
            'is_current' => $this->id === $this->botFlow?->published_version_id,
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
