<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class SimulateBotFlowRequest extends FormRequest
{
    public const MAX_MESSAGES = 20;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'messages' => ['required', 'array', 'min:1', 'max:'.self::MAX_MESSAGES],
            'messages.*' => ['required', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [];
    }

    public function customerMessages(): array
    {
        return array_values((array) $this->validated('messages'));
    }
}
