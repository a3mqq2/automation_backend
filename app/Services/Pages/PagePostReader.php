<?php

namespace App\Services\Pages;

use App\Models\ProductPost;
use App\Models\User;
use App\Services\Meta\MetaGraphClient;

class PagePostReader
{
    private const POST_FIELDS = 'id,created_time,message,permalink_url,full_picture';

    private const POST_LIMIT = 25;

    public function __construct(
        private readonly MetaGraphClient $graph,
        private readonly ConnectedPageQueryService $pages,
    ) {
    }

    public function recent(User $user, string $pageId): array
    {
        $page = $this->pages->detail($user, $pageId);
        $posts = $this->graph->get("{$page->page_id}/posts", (string) $page->page_access_token, [
            'fields' => self::POST_FIELDS,
            'limit' => self::POST_LIMIT,
        ])['data'] ?? [];

        $linkedProducts = ProductPost::query()
            ->whereIn('post_id', array_map(fn (array $post) => (string) ($post['id'] ?? ''), $posts))
            ->with('product:id,name')
            ->get()
            ->keyBy('post_id');

        return array_map(function (array $post) use ($linkedProducts) {
            $link = $linkedProducts->get((string) ($post['id'] ?? ''));

            return [
                'post_id' => (string) ($post['id'] ?? ''),
                'message' => $post['message'] ?? null,
                'picture_url' => $post['full_picture'] ?? null,
                'permalink_url' => $post['permalink_url'] ?? null,
                'created_time' => $post['created_time'] ?? null,
                'linked_product' => $link?->product === null ? null : [
                    'id' => $link->product->id,
                    'name' => $link->product->name,
                ],
            ];
        }, $posts);
    }
}
