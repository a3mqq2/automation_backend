<?php

namespace App\Http\Requests\Client;

use App\Http\Requests\Client\Concerns\ValidatesOwnedPage;
use App\Support\BotFlows\FlowDefinition;
use App\Support\BotFlows\FlowDefinitionValidator;
use App\Support\BotFlows\FlowIssue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Validator;

class StoreBotFlowRequest extends FormRequest
{
    use ValidatesOwnedPage;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'facebook_page_id' => ['required', 'integer', $this->pageRule()],
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
            'flow_json' => ['required', 'array'],
            'flow_json.version' => ['sometimes', 'integer'],
            'flow_json.entry' => ['required', 'array'],
            'flow_json.nodes' => ['required', 'array'],
            'flow_json.edges' => ['present', 'array'],
        ];
    }

    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->ensureDefinitionIsStructurallyValid($validator),
        ];
    }

    public function definition(): array
    {
        return FlowDefinition::fromArray((array) $this->validated('flow_json'))->toArray();
    }

    protected function pageRule(): Exists
    {
        return $this->connectedOwnedPage();
    }

    private function ensureDefinitionIsStructurallyValid(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        foreach (app(FlowDefinitionValidator::class)->structuralErrors((array) $this->input('flow_json')) as $issue) {
            $validator->errors()->add('flow_json', $this->issueMessage($issue));
        }
    }

    private function issueMessage(FlowIssue $issue): string
    {
        return $issue->nodeId === null
            ? $issue->message()
            : $issue->nodeId.': '.$issue->message();
    }
}
