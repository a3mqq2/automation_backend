<?php

namespace App\Http\Requests\Client;

use App\Http\Requests\Client\Concerns\ValidatesOwnedPage;
use Illuminate\Foundation\Http\FormRequest;

class StoreCatalogGroupRequest extends FormRequest
{
    use ValidatesOwnedPage;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'facebook_page_id' => ['required', 'integer', $this->ownedPage()],
            'name' => ['required', 'string', 'max:120'],
            'image_url' => ['sometimes', 'nullable', 'string', 'url:https', 'max:2048'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
