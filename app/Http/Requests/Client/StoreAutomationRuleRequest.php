<?php

namespace App\Http\Requests\Client;

use App\Enums\MatchType;
use App\Enums\TriggerType;
use App\Http\Requests\Client\Concerns\ValidatesOwnedPage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class StoreAutomationRuleRequest extends FormRequest
{
    use ValidatesOwnedPage;

    public const MAX_TEXT_LENGTH = 2000;

    public const MAX_KEYWORDS = 50;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'facebook_page_id' => ['required', 'integer', $this->pageRule()],
            'name' => ['required', 'string', 'max:120'],
            'trigger_type' => ['required', Rule::enum(TriggerType::class)],
            'match_type' => ['required', Rule::enum(MatchType::class)],
            'keywords' => [Rule::requiredIf(fn () => $this->matchType()?->requiresKeywords() ?? true), 'array', 'max:'.self::MAX_KEYWORDS],
            'keywords.*' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
            'response_text' => [
                'nullable',
                'string',
                'max:'.self::MAX_TEXT_LENGTH,
                Rule::requiredIf(fn () => $this->triggerType() === TriggerType::Message
                    || ($this->triggerType() === TriggerType::Comment && blank($this->input('private_reply_text')))),
            ],
            'private_reply_text' => [
                'nullable',
                'string',
                'max:'.self::MAX_TEXT_LENGTH,
                Rule::prohibitedIf(fn () => $this->triggerType() === TriggerType::Message),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $keywords = $this->input('keywords');

        if ($this->matchType() === MatchType::Any) {
            $this->merge(['keywords' => []]);
        } elseif (is_array($keywords)) {
            $this->merge(['keywords' => array_values(array_filter(
                array_map(fn ($keyword) => is_string($keyword) ? trim($keyword) : $keyword, $keywords),
                fn ($keyword) => $keyword !== null && $keyword !== '',
            ))]);
        }
    }

    protected function pageRule(): Exists
    {
        return $this->connectedOwnedPage();
    }

    protected function triggerType(): ?TriggerType
    {
        $value = $this->input('trigger_type');

        return is_string($value) ? TriggerType::tryFrom($value) : null;
    }

    protected function matchType(): ?MatchType
    {
        $value = $this->input('match_type');

        return is_string($value) ? MatchType::tryFrom($value) : null;
    }
}
