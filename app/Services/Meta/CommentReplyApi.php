<?php

namespace App\Services\Meta;

use App\Models\FacebookPage;

class CommentReplyApi
{
    public function __construct(private readonly MetaGraphClient $graph)
    {
    }

    public function reply(FacebookPage $page, string $commentId, string $message): array
    {
        return $this->graph->post("{$commentId}/comments", (string) $page->page_access_token, [
            'message' => $message,
        ]);
    }
}
