<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class FacebookCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'error' => ['sometimes', 'nullable', 'string', 'max:255'],
            'code' => ['required_without:error', 'nullable', 'string', 'max:2048'],
            'state' => ['required_without:error', 'nullable', 'string', 'max:100'],
        ];
    }
}
