<?php

namespace App\Services\Meta;

use App\Models\FacebookPage;
use Carbon\CarbonImmutable;

class PageCommentReader
{
    public function __construct(private readonly MetaGraphClient $graph)
    {
    }

    public function commentsSince(FacebookPage $page, CarbonImmutable $since): array
    {
        $feed = $this->graph->get("{$page->page_id}/feed", (string) $page->page_access_token, [
            'fields' => 'id,comments.limit('.$this->commentsLimit().').order(reverse_chronological){id,created_time,message,from}',
            'limit' => $this->postsLimit(),
        ]);

        $comments = [];

        foreach ($feed['data'] ?? [] as $post) {
            foreach ($post['comments']['data'] ?? [] as $comment) {
                $event = $this->toWebhookEvent($post, $comment, $since);

                if ($event !== null) {
                    $comments[] = $event;
                }
            }
        }

        return $comments;
    }

    private function toWebhookEvent(array $post, array $comment, CarbonImmutable $since): ?array
    {
        $commentId = (string) ($comment['id'] ?? '');
        $authorId = (string) data_get($comment, 'from.id', '');
        $createdTime = $comment['created_time'] ?? null;

        if ($commentId === '' || $authorId === '' || ! is_string($createdTime)) {
            return null;
        }

        if (CarbonImmutable::parse($createdTime)->lessThanOrEqualTo($since)) {
            return null;
        }

        return [
            'item' => 'comment',
            'verb' => 'add',
            'post_id' => (string) ($post['id'] ?? ''),
            'comment_id' => $commentId,
            'from' => ['id' => $authorId, 'name' => data_get($comment, 'from.name')],
            'message' => is_string($comment['message'] ?? null) ? $comment['message'] : '',
            'created_time' => $createdTime,
        ];
    }

    private function postsLimit(): int
    {
        return max(1, (int) config('meta.comment_polling.posts_limit', 10));
    }

    private function commentsLimit(): int
    {
        return max(1, (int) config('meta.comment_polling.comments_limit', 25));
    }
}
