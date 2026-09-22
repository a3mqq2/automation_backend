<?php

namespace App\Services\Admin;

use App\Enums\SubscriptionStatus;
use App\Models\User;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ClientDirectoryService
{
    public function paginate(ListQuery $listQuery): LengthAwarePaginator
    {
        $clients = User::query()->withCount($this->connectedPagesCount());

        if ($listQuery->hasFilter('subscription_status')) {
            $clients->withSubscriptionStatus(SubscriptionStatus::from($listQuery->filter('subscription_status')));
        }

        if ($listQuery->hasSearch()) {
            $pattern = $listQuery->searchPattern();
            $clients->where(fn (Builder $query) => $query
                ->where('name', 'like', $pattern)
                ->orWhere('email', 'like', $pattern));
        }

        return $listQuery->paginate($clients, ['connected_pages_count' => 'connected_pages_count']);
    }

    public function detail(User $client): User
    {
        return $client
            ->load([
                'activeLicenseKey',
                'facebookPages' => fn ($query) => $query->orderBy('name'),
                'licenseKeys' => fn ($query) => $query->orderByDesc('used_at'),
            ])
            ->loadCount(array_merge($this->connectedPagesCount(), [
                'automationRules',
                'botFlows',
                'activityLogs',
            ]));
    }

    private function connectedPagesCount(): array
    {
        return [
            'facebookPages as connected_pages_count' => fn (Builder $query) => $query->where('is_connected', true),
        ];
    }
}
