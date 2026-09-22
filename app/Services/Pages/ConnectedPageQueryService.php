<?php

namespace App\Services\Pages;

use App\Models\FacebookPage;
use App\Models\User;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ConnectedPageQueryService
{
    public function paginate(User $user, ListQuery $listQuery): LengthAwarePaginator
    {
        $pages = $user->connectedPages()->withCount($this->ruleAndFlowCounts());

        if ($listQuery->hasSearch()) {
            $pages->where('name', 'like', $listQuery->searchPattern());
        }

        return $listQuery->paginate($pages);
    }

    public function detail(User $user, string $pageId): FacebookPage
    {
        return $user->facebookPages()
            ->where('page_id', $pageId)
            ->withCount(array_merge($this->ruleAndFlowCounts(), ['conversations', 'activityLogs']))
            ->firstOrFail();
    }

    public function withCounts(FacebookPage $page): FacebookPage
    {
        return $page->loadCount($this->ruleAndFlowCounts());
    }

    private function ruleAndFlowCounts(): array
    {
        return [
            'automationRules',
            'automationRules as active_automation_rules_count' => fn (Builder $query) => $query->where('is_active', true),
            'botFlows',
        ];
    }
}
