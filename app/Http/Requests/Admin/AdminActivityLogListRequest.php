<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Client\ActivityLogListRequest;

class AdminActivityLogListRequest extends ActivityLogListRequest
{
    protected function filterRules(): array
    {
        return array_merge(parent::filterRules(), [
            'user_id' => ['sometimes', 'nullable', 'integer'],
        ]);
    }
}
