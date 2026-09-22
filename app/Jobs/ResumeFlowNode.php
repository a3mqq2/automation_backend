<?php

namespace App\Jobs;

use App\Models\BotFlow;
use App\Models\BotFlowVersion;
use App\Models\Conversation;
use App\Models\FacebookPage;
use App\Services\Automation\Engine\FlowRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ResumeFlowNode implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly int $facebookPageId,
        public readonly string $psid,
        public readonly int $botFlowId,
        public readonly int $botFlowVersionId,
        public readonly string $nodeId,
    ) {
    }

    public function handle(FlowRunner $runner): void
    {
        $page = FacebookPage::query()->connected()->find($this->facebookPageId);
        $flow = BotFlow::query()->active()->find($this->botFlowId);
        $version = BotFlowVersion::query()->where('bot_flow_id', $this->botFlowId)->find($this->botFlowVersionId);
        $conversation = Conversation::query()
            ->where('facebook_page_id', $this->facebookPageId)
            ->where('psid', $this->psid)
            ->first();

        if ($page === null || $flow === null || $version === null || $conversation === null) {
            return;
        }

        if ($conversation->isAutomationPaused() || blank($page->page_access_token)) {
            return;
        }

        $runner->resumeAt($page, $conversation, $flow, $version, $this->nodeId);
    }
}
