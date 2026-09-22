<?php

namespace App\Http\Requests\Client;

use App\Enums\TriggerType;
use App\Models\AutomationRule;
use Illuminate\Validation\Rules\Exists;

class UpdateAutomationRuleRequest extends StoreAutomationRuleRequest
{
    private ?AutomationRule $existingRule = null;

    protected function prepareForValidation(): void
    {
        $rule = $this->existingRule();
        $current = [
            'facebook_page_id' => $rule->facebook_page_id,
            'name' => $rule->name,
            'trigger_type' => $rule->trigger_type->value,
            'match_type' => $rule->match_type->value,
            'keywords' => $rule->keywords,
            'response_text' => $rule->response_text,
            'is_active' => $rule->is_active,
        ];

        $this->merge(array_diff_key($current, $this->all()));

        if (! $this->exists('private_reply_text') && $this->triggerType() === TriggerType::Comment) {
            $this->merge(['private_reply_text' => $rule->private_reply_text]);
        }

        parent::prepareForValidation();
    }

    protected function pageRule(): Exists
    {
        return (int) $this->input('facebook_page_id') === $this->existingRule()->facebook_page_id
            ? $this->ownedPage()
            : $this->connectedOwnedPage();
    }

    private function existingRule(): AutomationRule
    {
        return $this->existingRule ??= $this->user()->automationRules()->findOrFail($this->route('rule'));
    }
}
