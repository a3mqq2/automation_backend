<?php

namespace App\Services\Automation\Engine;

use App\Exceptions\MetaGraphException;
use App\Jobs\HandleCommentEvent;
use App\Models\FacebookPage;
use App\Services\Meta\PageCommentReader;
use Carbon\CarbonImmutable;

class CommentPollingService
{
    public function __construct(private readonly PageCommentReader $comments)
    {
    }

    public function isEnabled(): bool
    {
        return (bool) config('meta.comment_polling.enabled');
    }

    public function pollAllPages(): array
    {
        $results = [];

        foreach ($this->pollablePages() as $page) {
            $results[] = $this->pollPage($page);
        }

        return $results;
    }

    public function pollPage(FacebookPage $page): array
    {
        $startedAt = CarbonImmutable::now();

        if ($page->comments_polled_at === null) {
            $this->markPolled($page, $startedAt);

            return ['page' => $page->name, 'dispatched' => 0, 'error' => null, 'first_run' => true];
        }

        try {
            $comments = $this->comments->commentsSince($page, $this->watermark($page));
        } catch (MetaGraphException $exception) {
            $exception->report();

            return ['page' => $page->name, 'dispatched' => 0, 'error' => $exception->graphMessage(), 'first_run' => false];
        }

        foreach ($comments as $comment) {
            HandleCommentEvent::dispatch($page->id, $comment);
        }

        $this->markPolled($page, $startedAt);

        return ['page' => $page->name, 'dispatched' => count($comments), 'error' => null, 'first_run' => false];
    }

    private function pollablePages(): iterable
    {
        return FacebookPage::query()
            ->connected()
            ->withActiveCommentRules()
            ->whereNotNull('page_access_token')
            ->orderBy('id')
            ->get();
    }

    private function watermark(FacebookPage $page): CarbonImmutable
    {
        $overlap = (int) config('meta.comment_polling.overlap_seconds', 60);
        $maxLookback = (int) config('meta.comment_polling.max_lookback_minutes', 60);

        return CarbonImmutable::parse($page->comments_polled_at)
            ->subSeconds($overlap)
            ->max(CarbonImmutable::now()->subMinutes($maxLookback));
    }

    private function markPolled(FacebookPage $page, CarbonImmutable $polledAt): void
    {
        $page->forceFill(['comments_polled_at' => $polledAt])->save();
    }
}
