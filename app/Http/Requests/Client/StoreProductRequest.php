<?php

namespace App\Http\Requests\Client;

use App\Http\Requests\Client\Concerns\ValidatesOwnedPage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
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
            'product_category_id' => ['sometimes', 'nullable', 'integer', $this->belongsToSamePage('product_categories')],
            'product_brand_id' => ['sometimes', 'nullable', 'integer', $this->belongsToSamePage('product_brands')],
            'name' => ['required', 'string', 'max:160'],
            'sku' => ['sometimes', 'nullable', 'string', 'max:64'],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'currency' => ['sometimes', 'string', 'max:8'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'image_url' => ['sometimes', 'nullable', 'string', 'url:https', 'max:2048'],
            'product_url' => ['sometimes', 'nullable', 'string', 'url:https', 'max:2048'],
            'in_stock' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ];
    }

    private function belongsToSamePage(string $table): Rule|\Illuminate\Validation\Rules\Exists
    {
        return Rule::exists($table, 'id')->where('facebook_page_id', $this->input('facebook_page_id'));
    }
}
