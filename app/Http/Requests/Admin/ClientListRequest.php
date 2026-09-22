<?php

namespace App\Http\Requests\Admin;

use App\Enums\SubscriptionStatus;
use App\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

class ClientListRequest extends ListRequest
{
    protected function sortableFields(): array
    {
        return ['name', 'email', 'subscription_expires_at', 'connected_pages_count', 'last_login_at', 'created_at'];
    }

    protected function filterRules(): array
    {
        return [
            'subscription_status' => ['sometimes', 'nullable', Rule::enum(SubscriptionStatus::class)],
        ];
    }
}
