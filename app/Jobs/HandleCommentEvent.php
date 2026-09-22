<?php

namespace App\Jobs;

use App\Models\FacebookPage;
use App\Services\Automation\Engine\CommentAutomationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class HandleCommentEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly int $facebookPageId,
        public readonly array $comment,
    ) {
    }

    public function handle(CommentAutomationService $automation): void
    {
        $page = FacebookPage::query()->connected()->find($this->facebookPageId);

        if ($page !== null && filled($page->page_access_token)) {
            $automation->handle($page, $this->comment);
        }
    }
}
