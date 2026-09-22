<?php

namespace App\Services\Automation\Engine;

use Illuminate\Support\Facades\Cache;

class EventDeduplicator
{
    private const RETENTION_DAYS = 2;

    public function isFirstOccurrence(string $eventKey): bool
    {
        return Cache::add('meta:event:'.$eventKey, true, now()->addDays(self::RETENTION_DAYS));
    }
}
