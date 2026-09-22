<?php

namespace App\Services\Automation;

use App\Enums\MatchType;
use App\Enums\TriggerType;
use App\Models\AutomationRule;
use App\Models\User;
use App\Support\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AutomationRuleService
{
    public function paginate(User $user, ListQuery $listQuery): LengthAwarePaginator
    {
        $rules = $user->automationRules()->with('facebookPage');

        if ($listQuery->hasFilter('facebook_page_id')) {
            $rules->where('automation_rules.facebook_page_id', (int) $listQuery->filter('facebook_page_id'));
        }

        if ($listQuery->hasFilter('trigger_type')) {
            $rules->where('automation_rules.trigger_type', $listQuery->filter('trigger_type'));
        }

        if ($listQuery->hasFilter('is_active')) {
            $rules->where('automation_rules.is_active', (bool) $listQuery->filter('is_active'));
        }

        if ($listQuery->hasSearch()) {
            $rules->where('automation_rules.name', 'like', $listQuery->searchPattern());
        }

        return $listQuery->paginate($rules);
    }

    public function find(User $user, string $ruleId): AutomationRule
    {
        return $user->automationRules()->with('facebookPage')->findOrFail($ruleId);
    }

    public function create(array $attributes): AutomationRule
    {
        return AutomationRule::query()
            ->create($this->normalize($attributes))
            ->load('facebookPage');
    }

    public function update(User $user, string $ruleId, array $attributes): AutomationRule
    {
        $rule = $this->find($user, $ruleId);
        $rule->update($this->normalize($attributes));

        return $rule->load('facebookPage');
    }

    public function delete(User $user, string $ruleId): void
    {
        $this->find($user, $ruleId)->delete();
    }

    private function normalize(array $attributes): array
    {
        if (($attributes['match_type'] ?? null) === MatchType::Any->value) {
            $attributes['keywords'] = [];
        }

        if (($attributes['trigger_type'] ?? null) === TriggerType::Message->value) {
            $attributes['private_reply_text'] = null;
        }

        return $attributes;
    }
}
