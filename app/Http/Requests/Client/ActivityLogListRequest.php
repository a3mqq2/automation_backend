<?php

namespace App\Http\Requests\Client;

use App\Enums\ActivityEventType;
use App\Enums\ActivityStatus;
use App\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

class ActivityLogListRequest extends ListRequest
{
    protected function sortableFields(): array
    {
        return ['created_at', 'event_type', 'status'];
    }

    protected function filterRules(): array
    {
        return [
            'facebook_page_id' => ['sometimes', 'nullable', 'integer'],
            'event_type' => ['sometimes', 'nullable', Rule::enum(ActivityEventType::class)],
            'status' => ['sometimes', 'nullable', Rule::enum(ActivityStatus::class)],
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:date_from'],
        ];
    }
}
