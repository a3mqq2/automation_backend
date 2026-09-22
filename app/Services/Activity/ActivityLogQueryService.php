<?php

namespace App\Services\Activity;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\ListQuery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class ActivityLogQueryService
{
    public function paginateForClient(User $client, ListQuery $listQuery): LengthAwarePaginator
    {
        $logs = $client->activityLogs()->with('facebookPage');

        return $listQuery->paginate($this->applyFilters($logs, $listQuery));
    }

    public function findForClient(User $client, string $activityLogId): ActivityLog
    {
        return $client->activityLogs()->with('facebookPage')->findOrFail($activityLogId);
    }

    public function paginateForAdmin(ListQuery $listQuery): LengthAwarePaginator
    {
        $logs = ActivityLog::query()->with('facebookPage.user');

        if ($listQuery->hasFilter('user_id')) {
            $logs->whereHas('facebookPage', fn (Builder $page) => $page->where('user_id', (int) $listQuery->filter('user_id')));
        }

        return $listQuery->paginate($this->applyFilters($logs, $listQuery));
    }

    public function detailForAdmin(ActivityLog $activityLog): ActivityLog
    {
        return $activityLog->load('facebookPage.user');
    }

    private function applyFilters(Builder|Relation $logs, ListQuery $listQuery): Builder|Relation
    {
        if ($listQuery->hasFilter('facebook_page_id')) {
            $logs->where('activity_logs.facebook_page_id', (int) $listQuery->filter('facebook_page_id'));
        }

        if ($listQuery->hasFilter('event_type')) {
            $logs->where('activity_logs.event_type', $listQuery->filter('event_type'));
        }

        if ($listQuery->hasFilter('status')) {
            $logs->where('activity_logs.status', $listQuery->filter('status'));
        }

        if ($listQuery->hasFilter('date_from')) {
            $logs->where('activity_logs.created_at', '>=', CarbonImmutable::parse($listQuery->filter('date_from'))->startOfDay());
        }

        if ($listQuery->hasFilter('date_to')) {
            $logs->where('activity_logs.created_at', '<=', CarbonImmutable::parse($listQuery->filter('date_to'))->endOfDay());
        }

        if ($listQuery->hasSearch()) {
            $logs->whereHas('facebookPage', fn (Builder $page) => $page->where('name', 'like', $listQuery->searchPattern()));
        }

        return $logs;
    }
}
