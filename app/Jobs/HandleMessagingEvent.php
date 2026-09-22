<?php

namespace App\Jobs;

use App\Models\FacebookPage;
use App\Services\Automation\Engine\MessagingAutomationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class HandleMessagingEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly int $facebookPageId,
        public readonly array $event,
    ) {
    }

    public function handle(MessagingAutomationService $automation): void
    {
        $page = FacebookPage::query()->connected()->find($this->facebookPageId);

        if ($page !== null && filled($page->page_access_token)) {
            $automation->handle($page, $this->event);
        }
    }
}
