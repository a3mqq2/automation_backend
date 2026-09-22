<?php

namespace App\Http\Requests\Client;

use App\Http\Requests\ListRequest;

class ConnectedPageListRequest extends ListRequest
{
    protected function sortableFields(): array
    {
        return ['name', 'connected_at', 'created_at'];
    }

    protected function defaultSort(): string
    {
        return 'name';
    }

    protected function defaultDirection(): string
    {
        return 'asc';
    }
}
