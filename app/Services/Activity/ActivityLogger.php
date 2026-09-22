<?php

namespace App\Services\Activity;

use App\Enums\ActivityEventType;
use App\Enums\ActivityStatus;
use App\Models\ActivityLog;
use App\Models\FacebookPage;

class ActivityLogger
{
    public function record(FacebookPage $page, ActivityEventType $eventType, ActivityStatus $status, array $payload): ActivityLog
    {
        return ActivityLog::query()->create([
            'facebook_page_id' => $page->id,
            'event_type' => $eventType,
            'status' => $status,
            'payload' => $payload,
        ]);
    }
}
