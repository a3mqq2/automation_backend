<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Request;

class BotFlowDetailResource extends BotFlowResource
{
    public function __construct($resource, private readonly array $issues = [])
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'issues' => $this->issues,
        ]);
    }
}
