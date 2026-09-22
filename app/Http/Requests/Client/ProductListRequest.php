<?php

namespace App\Http\Requests\Client;

class ProductListRequest extends CatalogListRequest
{
    protected function sortableFields(): array
    {
        return ['name', 'price', 'sort_order', 'is_active', 'in_stock', 'created_at', 'updated_at'];
    }

    protected function filterRules(): array
    {
        return array_merge(parent::filterRules(), [
            'product_category_id' => ['sometimes', 'nullable', 'integer'],
            'product_brand_id' => ['sometimes', 'nullable', 'integer'],
            'in_stock' => ['sometimes', 'nullable', 'boolean'],
        ]);
    }
}
