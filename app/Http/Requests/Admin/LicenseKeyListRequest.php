<?php

namespace App\Http\Requests\Admin;

use App\Enums\LicenseKeyStatus;
use App\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

class LicenseKeyListRequest extends ListRequest
{
    protected function sortableFields(): array
    {
        return ['created_at', 'expires_at', 'used_at', 'key', 'is_used'];
    }

    protected function filterRules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', Rule::enum(LicenseKeyStatus::class)],
        ];
    }
}
