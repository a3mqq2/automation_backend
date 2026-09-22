<?php

namespace App\Http\Requests\Client;

use App\Enums\TriggerType;
use App\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

class AutomationRuleListRequest extends ListRequest
{
    protected function sortableFields(): array
    {
        return ['name', 'trigger_type', 'match_type', 'is_active', 'created_at', 'updated_at'];
    }

    protected function filterRules(): array
    {
        return [
            'facebook_page_id' => ['sometimes', 'nullable', 'integer'],
            'trigger_type' => ['sometimes', 'nullable', Rule::enum(TriggerType::class)],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}
