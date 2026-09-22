<?php

namespace App\Services\Automation;

use App\Models\BotFlow;
use App\Models\User;
use App\Support\BotFlows\FlowDefinition;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BotFlowService
{
    public function paginate(User $user, ListQuery $listQuery): LengthAwarePaginator
    {
        $flows = $user->botFlows()->with(['facebookPage', 'publishedVersion']);

        if ($listQuery->hasFilter('facebook_page_id')) {
            $flows->where('bot_flows.facebook_page_id', (int) $listQuery->filter('facebook_page_id'));
        }

        if ($listQuery->hasFilter('is_active')) {
            $flows->where('bot_flows.is_active', (bool) $listQuery->filter('is_active'));
        }

        if ($listQuery->hasSearch()) {
            $flows->where('bot_flows.name', 'like', $listQuery->searchPattern());
        }

        return $listQuery->paginate($flows);
    }

    public function find(User $user, string $flowId): BotFlow
    {
        return $user->botFlows()->with(['facebookPage', 'publishedVersion'])->findOrFail($flowId);
    }

    public function create(array $attributes): BotFlow
    {
        return BotFlow::query()
            ->create($this->normalize($attributes))
            ->load(['facebookPage', 'publishedVersion']);
    }

    public function update(User $user, string $flowId, array $attributes): BotFlow
    {
        $flow = $this->find($user, $flowId);
        $flow->update($this->normalize($attributes));

        return $flow->load(['facebookPage', 'publishedVersion']);
    }

    public function delete(User $user, string $flowId): void
    {
        $this->find($user, $flowId)->delete();
    }

    private function normalize(array $attributes): array
    {
        if (isset($attributes['flow_json'])) {
            $attributes['flow_json'] = FlowDefinition::fromArray($attributes['flow_json'])->toArray();
        }

        return $attributes;
    }
}
