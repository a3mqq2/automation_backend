<?php

namespace App\Console\Commands;

use App\Services\Automation\Engine\CommentPollingService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('comments:poll {--force : Poll even when META_COMMENT_POLLING is disabled}')]
#[Description('Fetch new page comments from the Graph API and run them through the automation engine')]
class PollCommentsCommand extends Command
{
    public function handle(CommentPollingService $polling): int
    {
        if (! $polling->isEnabled() && ! $this->option('force')) {
            $this->components->warn('Comment polling is disabled. Set META_COMMENT_POLLING=true or pass --force.');

            return self::SUCCESS;
        }

        $results = $polling->pollAllPages();

        if ($results === []) {
            $this->components->info('No connected page has an active comment rule.');

            return self::SUCCESS;
        }

        foreach ($results as $result) {
            $this->components->twoColumnDetail($result['page'], $this->describe($result));
        }

        return self::SUCCESS;
    }

    private function describe(array $result): string
    {
        if ($result['error'] !== null) {
            return 'failed: '.$result['error'];
        }

        if ($result['first_run']) {
            return 'watermark set, older comments skipped';
        }

        return $result['dispatched'].' new comment(s)';
    }
}
