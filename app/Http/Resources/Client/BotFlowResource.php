<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BotFlowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'facebook_page' => new PageSummaryResource($this->whenLoaded('facebookPage')),
            'is_active' => $this->is_active,
            'flow_json' => $this->flow_json,
            'nodes_count' => count($this->flow_json['nodes'] ?? []),
            'is_published' => $this->isPublished(),
            'has_unpublished_changes' => $this->hasUnpublishedChanges(),
            'published_version' => $this->whenLoaded('publishedVersion', fn () => $this->publishedVersion === null ? null : [
                'id' => $this->publishedVersion->id,
                'version' => $this->publishedVersion->version,
                'published_at' => $this->publishedVersion->published_at?->toIso8601String(),
            ]),
            'issues' => $this->when(isset($this->issues), fn () => $this->issues),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
