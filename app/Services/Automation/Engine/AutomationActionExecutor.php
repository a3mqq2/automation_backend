<?php

namespace App\Services\Automation\Engine;

use App\Enums\ActivityEventType;
use App\Enums\ActivityStatus;
use App\Exceptions\MetaGraphException;
use App\Models\FacebookPage;
use App\Services\Activity\ActivityLogger;

class AutomationActionExecutor
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    public function run(FacebookPage $page, ActivityEventType $eventType, array $payload, callable $action): bool
    {
        try {
            $action();
        } catch (MetaGraphException $exception) {
            $exception->report();

            $this->activityLogger->record($page, $eventType, ActivityStatus::Failed, array_merge($payload, [
                'error' => [
                    'code' => $exception->graphErrorCode(),
                    'message' => $exception->graphMessage(),
                ],
            ]));

            return false;
        }

        $this->activityLogger->record($page, $eventType, ActivityStatus::Success, $payload);

        return true;
    }
}
