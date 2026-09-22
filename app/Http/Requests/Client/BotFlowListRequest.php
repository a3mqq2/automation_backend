<?php

namespace App\Http\Requests\Client;

use App\Http\Requests\ListRequest;

class BotFlowListRequest extends ListRequest
{
    protected function sortableFields(): array
    {
        return ['name', 'is_active', 'created_at', 'updated_at'];
    }

    protected function filterRules(): array
    {
        return [
            'facebook_page_id' => ['sometimes', 'nullable', 'integer'],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}
