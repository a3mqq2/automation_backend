<?php

namespace App\Http\Requests\Client;

use App\Http\Requests\ListRequest;

class CatalogListRequest extends ListRequest
{
    protected function sortableFields(): array
    {
        return ['name', 'sort_order', 'is_active', 'created_at', 'updated_at'];
    }

    protected function defaultSort(): string
    {
        return 'sort_order';
    }

    protected function defaultDirection(): string
    {
        return 'asc';
    }

    protected function filterRules(): array
    {
        return [
            'facebook_page_id' => ['sometimes', 'nullable', 'integer'],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}
