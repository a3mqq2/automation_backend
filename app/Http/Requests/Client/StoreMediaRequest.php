<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'image',
                Rule::file()->types(config('meta.media.mime_types'))->max(config('meta.media.max_kilobytes')),
            ],
        ];
    }
}
