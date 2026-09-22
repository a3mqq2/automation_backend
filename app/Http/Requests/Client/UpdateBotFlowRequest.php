<?php

namespace App\Http\Requests\Client;

use App\Models\BotFlow;
use Illuminate\Validation\Rules\Exists;

class UpdateBotFlowRequest extends StoreBotFlowRequest
{
    private ?BotFlow $existingFlow = null;

    protected function prepareForValidation(): void
    {
        $flow = $this->existingFlow();

        $this->merge(array_diff_key([
            'facebook_page_id' => $flow->facebook_page_id,
            'name' => $flow->name,
            'is_active' => $flow->is_active,
            'flow_json' => $flow->flow_json,
        ], $this->all()));
    }

    protected function pageRule(): Exists
    {
        return (int) $this->input('facebook_page_id') === $this->existingFlow()->facebook_page_id
            ? $this->ownedPage()
            : $this->connectedOwnedPage();
    }

    private function existingFlow(): BotFlow
    {
        return $this->existingFlow ??= $this->user()->botFlows()->findOrFail($this->route('botFlow'));
    }
}
