<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AvailablePageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'page_id' => $this->page->pageId,
            'name' => $this->page->name,
            'category' => $this->page->category,
            'picture_url' => $this->page->pictureUrl,
            'tasks' => $this->page->tasks,
            'can_connect' => $this->page->canConnect(),
            'is_connected' => $this->isConnected,
            'connected_by_another_account' => $this->connectedByAnotherAccount,
            'facebook_page_id' => $this->facebookPageId,
        ];
    }
}
