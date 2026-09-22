<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConnectedPageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'page_id' => $this->page_id,
            'name' => $this->name,
            'category' => $this->category,
            'picture_url' => $this->picture_url,
            'is_connected' => $this->is_connected,
            'connected_at' => $this->connected_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'automation_rules_count' => $this->whenCounted('automationRules'),
            'active_automation_rules_count' => $this->whenCounted('activeAutomationRules'),
            'bot_flows_count' => $this->whenCounted('botFlows'),
            'conversations_count' => $this->whenCounted('conversations'),
            'activity_logs_count' => $this->whenCounted('activityLogs'),
        ];
    }
}
